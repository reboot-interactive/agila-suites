@extends('layouts.app')
@section('breadcrumb', 'Warehousing / Transfers')

@section('content')
<div class="page-header">
    <h2>Transfers <span class="text-secondary text-sm">({{ $transfers->total() }})</span></h2>
    <div class="page-header-actions">
        <a class="btn" href="{{ route('ext.warehousing.transfers.create') }}">Create Transfer</a>
    </div>
</div>

<div class="card mb-16">
    <form method="GET" action="{{ route('ext.warehousing.transfers.index') }}" class="d-flex gap-12 flex-wrap items-center">
        <div>
            <label class="text-xs text-secondary">From</label>
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="input">
        </div>
        <div>
            <label class="text-xs text-secondary">To</label>
            <input type="date" name="date_to" value="{{ $dateTo }}" class="input">
        </div>
        <div>
            <label class="text-xs text-secondary">Location</label>
            <select name="warehouse_id" class="input">
                <option value="">All Locations</option>
                @foreach($warehouses as $w)
                    <option value="{{ $w->id }}" {{ $warehouseId == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-secondary">Status</label>
            <select name="status" class="input">
                <option value="">All</option>
                <option value="draft" {{ $status === 'draft' ? 'selected' : '' }}>Draft</option>
                <option value="in_progress" {{ $status === 'in_progress' ? 'selected' : '' }}>Approved</option>
                <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                <option value="voided" {{ $status === 'voided' ? 'selected' : '' }}>Voided</option>
            </select>
        </div>
        <div class="d-flex gap-8" style="align-self:flex-end;">
            <button type="submit" class="btn">Search</button>
            <a href="{{ route('ext.warehousing.transfers.index') }}" class="btn secondary">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Reference</th>
            <th>Status</th>
            <th>From</th>
            <th>To</th>
            <th style="text-align:right;">Items</th>
            <th>Note</th>
            <th>Created By</th>
            <th>Date</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse($transfers as $t)
            @php
                $badgeClass = match($t->status) {
                    'in_progress' => 'badge-blue',
                    'completed' => 'badge-green',
                    'cancelled' => 'badge-red',
                    'voided' => 'badge-red',
                    default => 'badge-gray',
                };
                $statusLabel = match($t->status) {
                    'in_progress' => 'Approved',
                    default => ucfirst($t->status),
                };
            @endphp
            <tr>
                <td><a href="{{ route('ext.warehousing.transfers.show', $t->id) }}" class="font-bold" style="text-decoration:underline; text-underline-offset:2px;">{{ $t->reference }}</a></td>
                <td><span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span></td>
                <td>{{ $t->fromWarehouse->name ?? '—' }}</td>
                <td>{{ $t->toWarehouse->name ?? '—' }}</td>
                <td style="text-align:right;">{{ $t->items_count }}</td>
                <td title="{{ $t->note }}">{{ Str::limit($t->note, 40) }}</td>
                <td>{{ $t->user_name }}</td>
                <td>{{ $t->created_at->format('Y-m-d H:i') }}</td>
                <td>
                    <a href="{{ route('ext.warehousing.transfers.show', $t->id) }}" class="btn small secondary">View</a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9">No transfers found.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
    </div>

    @if($transfers->hasPages())
        <div class="mt-16">
            {{ $transfers->onEachSide(1)->links('vendor.pagination.simple') }}
        </div>
    @endif
</div>
@endsection
