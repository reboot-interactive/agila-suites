@extends('layouts.app')
@section('breadcrumb', 'Marketplace / Pedallion / Orders')

@section('content')
@include('integrations.partials._tab_strip', ['activeTabId' => 'pedallion'])
<div class="page-header">
    <div>
        <h2>Pedallion Orders <span class="text-secondary text-sm">({{ $orders->total() }})</span></h2>
    </div>
    <div class="page-header-actions">
        <a class="btn secondary" href="{{ route('ext.pedallion.index') }}">Back to Pedallion</a>
    </div>
</div>

{{-- Filter toolbar --}}
<div class="card mb-12">
    <form method="GET" action="{{ route('ext.pedallion.orders.index') }}" style="display:grid; grid-template-columns:1fr auto auto auto auto; gap:10px; align-items:end;">
        <div>
            <label class="text-xs text-muted">Search</label>
            <input class="input" name="q" value="{{ request('q') }}" placeholder="Order # or buyer name...">
        </div>
        <div>
            <label class="text-xs text-muted">Status</label>
            <input class="input" name="status" value="{{ request('status') }}" placeholder="e.g. pending" style="width:140px;">
        </div>
        <div>
            <label class="text-xs text-muted">From</label>
            <input class="input" type="date" name="from" value="{{ request('from') }}">
        </div>
        <div>
            <label class="text-xs text-muted">To</label>
            <input class="input" type="date" name="to" value="{{ request('to') }}">
        </div>
        <div class="d-flex gap-6">
            <button class="btn" type="submit">Filter</button>
            @if(request('q') || request('status') || request('from') || request('to'))
                <a class="btn secondary" href="{{ route('ext.pedallion.orders.index') }}">Clear</a>
            @endif
        </div>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Buyer</th>
                    <th>Status</th>
                    <th style="text-align:right;">Total</th>
                    <th>Date</th>
                    <th style="width:80px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $o)
                <tr>
                    <td><strong>{{ $o->order_number }}</strong></td>
                    <td>{{ $o->buyer_name ?? '-' }}</td>
                    <td>
                        @php
                            $sBadge = match(strtolower($o->status ?? '')) {
                                'completed', 'delivered', 'paid' => 'badge-green',
                                'cancelled', 'refunded', 'failed' => 'badge-red',
                                'shipped', 'processing', 'ready_to_ship' => 'badge-yellow',
                                default => 'badge-gray',
                            };
                        @endphp
                        <span class="badge {{ $sBadge }}">{{ $o->status ?? '-' }}</span>
                    </td>
                    <td style="text-align:right; white-space:nowrap;">{{ number_format($o->total, 2) }} {{ $o->currency }}</td>
                    <td class="text-sm text-muted">{{ $o->order_date?->format('M d, Y H:i') ?? '-' }}</td>
                    <td>
                        <a class="btn btn-sm secondary" href="{{ route('ext.pedallion.orders.show', $o->id) }}">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-muted" style="padding:24px; text-align:center;">
                        No orders found. Run <code>php artisan pedallion:sync-orders</code> to fetch orders from Pedallion.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($orders->hasPages())
    <div class="d-flex justify-between items-center mt-12">
        <div class="text-muted text-xs">
            Showing {{ $orders->firstItem() ?? 0 }} to {{ $orders->lastItem() ?? 0 }} of {{ $orders->total() }}
        </div>
        <div>
            {{ $orders->links('vendor.pagination.simple') }}
        </div>
    </div>
    @endif
</div>
@endsection
