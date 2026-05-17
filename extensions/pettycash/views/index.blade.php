@extends('layouts.app')
@section('breadcrumb', 'Petty Cash')

@section('content')
{{-- Page Header --}}
<div class="page-header">
    <h2>Petty Cash</h2>
    <div class="page-header-actions d-flex gap-8">
        @if($userRole === 'admin')
            <button type="button" class="btn" id="btn-add-credit">Add Credit</button>
        @endif
        <button type="button" class="btn" id="btn-add-expense">Add Expense</button>
    </div>
</div>

{{-- Balance Cards --}}
<div class="pc-balance-cards">
    @foreach($staffBalances as $sb)
        @php
            $balance = $sb->total_credits - $sb->total_expenses;
        @endphp
        <div class="pc-balance-card">
            @if($userRole === 'admin')
                <div class="pc-name">{{ $sb->user_name }}</div>
            @else
                <div class="pc-name">Your Petty Cash Balance</div>
            @endif
            <div class="pc-amount {{ $balance >= 0 ? 'positive' : 'negative' }}">
                {{ $balance < 0 ? '-' : '' }}₱{{ number_format(abs($balance), 2) }}
            </div>
            <div class="pc-detail">
                Credits: ₱{{ number_format($sb->total_credits, 2) }} &middot; Expenses: ₱{{ number_format($sb->total_expenses, 2) }}
            </div>
        </div>
    @endforeach
</div>

{{-- Filters --}}
<div class="card mb-16">
    <form method="GET" action="{{ route('ext.pettycash.index') }}" class="d-flex gap-12 flex-wrap items-center">
        <div>
            <label class="text-xs text-secondary">Date From</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="input">
        </div>
        <div>
            <label class="text-xs text-secondary">Date To</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="input">
        </div>
        <div>
            <label class="text-xs text-secondary">Category</label>
            <select name="category_id" class="input">
                <option value="">All Categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        @if($userRole === 'admin')
        <div>
            <label class="text-xs text-secondary">Staff</label>
            <select name="user_id" class="input">
                <option value="">All Staff</option>
                @foreach($staffList as $staff)
                    <option value="{{ $staff->id }}" {{ request('user_id') == $staff->id ? 'selected' : '' }}>{{ $staff->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="d-flex gap-8" style="align-self:flex-end;">
            <button type="submit" class="btn">Filter</button>
            <a href="{{ route('ext.pettycash.index') }}" class="btn secondary">Clear</a>
        </div>
    </form>
</div>

{{-- Transactions Table --}}
<div class="card">
    <div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Date</th>
            @if($userRole === 'admin')<th>Staff</th>@endif
            <th>Type</th>
            <th>Category</th>
            <th>Description</th>
            <th style="text-align:right;">Amount</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse($transactions as $txn)
            <tr>
                <td>{{ $txn->transaction_date->format('Y-m-d') }}</td>
                @if($userRole === 'admin')<td>{{ $txn->user->name ?? '—' }}</td>@endif
                <td>
                    <span class="badge {{ $txn->type === 'credit' ? 'badge-green' : 'badge-red' }}">
                        {{ ucfirst($txn->type) }}
                    </span>
                </td>
                <td>{{ $txn->category ?? '—' }}</td>
                <td title="{{ $txn->description }}">{{ Str::limit($txn->description, 50) }}</td>
                <td style="text-align:right; font-variant-numeric:tabular-nums;">{{ number_format($txn->amount, 2) }}</td>
                <td>
                    <div class="d-flex gap-8">
                        @if($userRole === 'admin' || ($txn->type === 'expense' && $txn->user_id === auth()->id()))
                            <button type="button" class="btn small secondary btn-edit-txn"
                                data-id="{{ $txn->id }}"
                                data-type="{{ $txn->type }}"
                                data-user-id="{{ $txn->user_id }}"
                                data-amount="{{ $txn->amount }}"
                                data-category="{{ $txn->category }}"
                                data-description="{{ e($txn->description) }}"
                                data-notes="{{ e($txn->notes) }}"
                                data-date="{{ $txn->transaction_date->format('Y-m-d') }}"
                            >Edit</button>
                            <form method="POST" action="{{ route('ext.pettycash.destroy', $txn->id) }}" data-confirm="Delete this transaction?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn small secondary">Delete</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $userRole === 'admin' ? 7 : 6 }}">No transactions found.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
    </div>

    @if($transactions->hasPages())
        <div class="mt-16">
            {{ $transactions->onEachSide(1)->links('vendor.pagination.simple') }}
        </div>
    @endif
</div>

{{-- Add/Edit Modal --}}
<div class="modal-backdrop" id="txn-modal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modal-title">Add Transaction</h3>
            <button type="button" class="modal-close" id="modal-close-btn">&times;</button>
        </div>
        <form method="POST" action="{{ route('ext.pettycash.store') }}" id="txn-form">
            @csrf
            <input type="hidden" name="_method" value="POST" id="txn-method">

            <div class="form-grid" style="gap:12px;">
                {{-- Type --}}
                <div>
                    <label class="text-xs text-secondary">Type</label>
                    <div class="d-flex gap-12" style="margin-top:4px;">
                        @if($userRole === 'admin')
                        <label class="d-flex gap-8 items-center">
                            <input type="radio" name="type" value="credit" id="type-credit"> Credit
                        </label>
                        @endif
                        <label class="d-flex gap-8 items-center">
                            <input type="radio" name="type" value="expense" id="type-expense" checked> Expense
                        </label>
                    </div>
                </div>

                {{-- Staff (credit + admin only) --}}
                @if($userRole === 'admin')
                <div id="field-staff" style="display:none;">
                    <label class="text-xs text-secondary">Staff</label>
                    <select name="user_id" class="input" id="txn-user-id">
                        <option value="">Select Staff</option>
                        @foreach($staffList as $staff)
                            <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                {{-- Amount --}}
                <div>
                    <label class="text-xs text-secondary">Amount</label>
                    <input type="number" name="amount" step="0.01" min="0.01" class="input" id="txn-amount" required>
                </div>

                {{-- Category (expense only) --}}
                <div id="field-category">
                    <label class="text-xs text-secondary">Category</label>
                    <select name="category" class="input" id="txn-category">
                        <option value="">Select Category</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->name }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Date --}}
                <div>
                    <label class="text-xs text-secondary">Date</label>
                    <input type="date" name="transaction_date" class="input" id="txn-date" required>
                </div>

                {{-- Description --}}
                <div>
                    <label class="text-xs text-secondary">Description</label>
                    <textarea name="description" class="input" id="txn-description" rows="3"></textarea>
                </div>

                {{-- Notes --}}
                <div>
                    <label class="text-xs text-secondary">Notes</label>
                    <textarea name="notes" class="input" id="txn-notes" rows="2" style="font-size:.85rem;"></textarea>
                </div>
            </div>

            <div style="margin-top:16px; text-align:right;">
                <button type="submit" class="btn" id="txn-submit-btn">Save</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('txn-modal');
    var form = document.getElementById('txn-form');
    var methodInput = document.getElementById('txn-method');
    var modalTitle = document.getElementById('modal-title');
    var typeCredit = document.getElementById('type-credit');
    var typeExpense = document.getElementById('type-expense');
    var fieldStaff = document.getElementById('field-staff');
    var fieldCategory = document.getElementById('field-category');
    var isAdmin = {{ $userRole === 'admin' ? 'true' : 'false' }};
    var storeUrl = @json(route('ext.pettycash.store'));
    var updateUrlBase = @json(url('/petty-cash'));
    var today = new Date().toISOString().slice(0, 10);

    function openModal() {
        modal.classList.add('active');
    }

    function closeModal() {
        modal.classList.remove('active');
    }

    function resetForm() {
        form.reset();
        form.action = storeUrl;
        methodInput.value = 'POST';
        modalTitle.textContent = 'Add Transaction';
        document.getElementById('txn-date').value = today;
        if (typeExpense) typeExpense.checked = true;
        toggleTypeFields();
    }

    function toggleTypeFields() {
        var isCredit = typeCredit && typeCredit.checked;
        if (fieldStaff) {
            fieldStaff.style.display = isCredit ? '' : 'none';
        }
        if (fieldCategory) {
            fieldCategory.style.display = isCredit ? 'none' : '';
        }
    }

    // Type radio change
    if (typeCredit) {
        typeCredit.addEventListener('change', toggleTypeFields);
    }
    if (typeExpense) {
        typeExpense.addEventListener('change', toggleTypeFields);
    }

    // Add Credit button
    var btnAddCredit = document.getElementById('btn-add-credit');
    if (btnAddCredit) {
        btnAddCredit.addEventListener('click', function () {
            resetForm();
            modalTitle.textContent = 'Add Credit';
            if (typeCredit) typeCredit.checked = true;
            toggleTypeFields();
            openModal();
        });
    }

    // Add Expense button
    var btnAddExpense = document.getElementById('btn-add-expense');
    if (btnAddExpense) {
        btnAddExpense.addEventListener('click', function () {
            resetForm();
            modalTitle.textContent = 'Add Expense';
            if (typeExpense) typeExpense.checked = true;
            toggleTypeFields();
            openModal();
        });
    }

    // Edit buttons
    document.querySelectorAll('.btn-edit-txn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            resetForm();
            modalTitle.textContent = 'Edit Transaction';

            var id = btn.getAttribute('data-id');
            form.action = updateUrlBase + '/' + id;
            methodInput.value = 'PUT';

            var type = btn.getAttribute('data-type');
            if (type === 'credit' && typeCredit) {
                typeCredit.checked = true;
            } else if (typeExpense) {
                typeExpense.checked = true;
            }
            toggleTypeFields();

            document.getElementById('txn-amount').value = btn.getAttribute('data-amount');
            document.getElementById('txn-category').value = btn.getAttribute('data-category') || '';
            document.getElementById('txn-date').value = btn.getAttribute('data-date');
            document.getElementById('txn-description').value = btn.getAttribute('data-description');
            document.getElementById('txn-notes').value = btn.getAttribute('data-notes');

            var userIdSelect = document.getElementById('txn-user-id');
            if (userIdSelect) {
                userIdSelect.value = btn.getAttribute('data-user-id') || '';
            }

            openModal();
        });
    });

    // Close modal
    document.getElementById('modal-close-btn').addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('active')) closeModal();
    });
});
</script>
@endpush
