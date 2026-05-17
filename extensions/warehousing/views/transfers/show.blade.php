@extends('layouts.app')
@section('breadcrumb', 'Warehousing / Transfers / Detail')

@section('content')
@php
    $isDraft = $transfer->status === 'draft';
    $isInProgress = $transfer->status === 'in_progress';
    $isCompleted = $transfer->status === 'completed';
    $isCancelled = $transfer->status === 'cancelled';
    $isVoided = $transfer->status === 'voided';
    $totalQty = collect($enrichedItems)->sum('quantity');

    $badgeClass = match($transfer->status) {
        'draft' => 'badge-gray',
        'in_progress' => 'badge-blue',
        'completed' => 'badge-green',
        'cancelled' => 'badge-red',
        'voided' => 'badge-red',
        default => '',
    };
    $statusLabel = match($transfer->status) {
        'in_progress' => 'In Progress',
        default => ucfirst($transfer->status),
    };
@endphp

<div class="page-header">
    <div class="d-flex gap-10 items-center flex-wrap">
        <h2>Transfer {{ $transfer->reference }}</h2>
        <span class="badge {{ $badgeClass }}" style="font-size:1.2em; padding:6px 18px;">{{ $statusLabel }}</span>
    </div>
    <div class="page-header-actions">
        <a class="btn secondary" href="{{ route('ext.warehousing.transfers.index') }}">Back to Transfers</a>

        @if(!$isDraft)
            <a class="btn secondary" href="{{ route('ext.warehousing.transfers.pdf', $transfer->id) }}" target="_blank">Print PDF</a>
        @endif

        @if($isDraft)
            <a class="btn secondary" href="{{ route('ext.warehousing.transfers.edit', $transfer->id) }}">Edit</a>

            <form method="POST" action="{{ route('ext.warehousing.transfers.in_progress', $transfer->id) }}" data-confirm="Approve this transfer? Stock will be validated.">
                @csrf
                <button type="submit" class="btn">Approve</button>
            </form>

            <form method="POST" action="{{ route('ext.warehousing.transfers.destroy', $transfer->id) }}" data-confirm="Delete this draft transfer?">
                @csrf @method('DELETE')
                <button type="submit" class="btn danger">Delete</button>
            </form>
        @endif

        @if($isInProgress)
            <form method="POST" action="{{ route('ext.warehousing.transfers.complete', $transfer->id) }}" data-confirm="Complete this transfer? Stock will be moved from source to destination.">
                @csrf
                <button type="submit" class="btn">Complete & Move Stock</button>
            </form>

            <form method="POST" action="{{ route('ext.warehousing.transfers.cancel', $transfer->id) }}" data-confirm="Cancel this transfer? No stock will be moved.">
                @csrf
                <button type="submit" class="btn danger">Cancel</button>
            </form>
        @endif

        @if($isCompleted)
            <form method="POST" action="{{ route('ext.warehousing.transfers.void', $transfer->id) }}" data-confirm="Void this transfer? Stock will be reversed.">
                @csrf
                <button type="submit" class="btn danger">Void</button>
            </form>
        @endif
    </div>
</div>

{{-- ── Status Flow ── --}}
<div class="transfer-flow">
    <div class="flow-step {{ $isDraft || $isInProgress || $isCompleted ? 'done' : '' }}">
        <div class="flow-dot"></div>
        <div class="flow-label">Draft</div>
        <div class="flow-date">{{ $transfer->created_at->format('M d, H:i') }}</div>
    </div>
    <div class="flow-line {{ $isInProgress || $isCompleted ? 'done' : '' }}"></div>
    <div class="flow-step {{ $isInProgress || $isCompleted ? 'done' : ($isDraft ? 'current' : '') }}">
        <div class="flow-dot"></div>
        <div class="flow-label">Approved</div>
    </div>
    <div class="flow-line {{ $isCompleted ? 'done' : '' }}"></div>
    <div class="flow-step {{ $isCompleted ? 'done' : ($isInProgress ? 'current' : '') }}">
        <div class="flow-dot"></div>
        <div class="flow-label">Completed</div>
    </div>
</div>

@if($isCancelled)
    <div class="alert warning"><strong>Cancelled</strong> — no stock was moved.</div>
@endif

@if($isVoided)
    <div class="alert danger"><strong>Voided</strong> — stock has been reversed.</div>
@endif

{{-- ── Transfer Details ── --}}
<div class="detail-grid">
    <div class="detail-section">
        <h4>Transfer Details</h4>
        <div class="detail-body">
            <div><span class="text-secondary">From:</span> <strong>{{ $transfer->fromWarehouse->name ?? '—' }}</strong></div>
            <div><span class="text-secondary">To:</span> <strong>{{ $transfer->toWarehouse->name ?? '—' }}</strong></div>
            <div><span class="text-secondary">Created By:</span> <strong>{{ $transfer->user_name ?? '—' }}</strong></div>
            <div><span class="text-secondary">Date:</span> <strong>{{ $transfer->created_at->format('Y-m-d H:i') }}</strong></div>
        </div>
    </div>

    @if($transfer->note)
    <div class="detail-section">
        <h4>Note</h4>
        <div class="detail-body"><div>{{ $transfer->note }}</div></div>
    </div>
    @endif
</div>

{{-- ── Items ── --}}
<div class="card">
    <h3 class="section-title mt-0">Items</h3>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th>SKU</th>
                <th>Option</th>
                <th style="text-align:right;">Quantity</th>
            </tr>
            </thead>
            <tbody>
            @foreach($enrichedItems as $idx => $item)
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td class="font-bold">{{ $item->product_name }}</td>
                    <td>{{ $item->sku ?: '—' }}</td>
                    <td>{{ $item->option_label ?: '—' }}</td>
                    <td style="text-align:right;">{{ $item->quantity }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr>
                <td colspan="4" class="text-right font-bold">Total</td>
                <td style="text-align:right;" class="font-bold">{{ $totalQty }}</td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
