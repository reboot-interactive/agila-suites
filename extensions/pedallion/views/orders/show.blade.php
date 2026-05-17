@extends('layouts.app')
@section('breadcrumb', 'Marketplace / Pedallion / Orders / ' . $order->order_number)

@section('content')
<div class="page-header">
    <div>
        <h2>
            Order {{ $order->order_number }}
            @php
                $sBadge = match(strtolower($order->status ?? '')) {
                    'completed', 'delivered', 'paid' => 'badge-green',
                    'cancelled', 'refunded', 'failed' => 'badge-red',
                    'shipped', 'processing', 'ready_to_ship' => 'badge-yellow',
                    default => 'badge-gray',
                };
            @endphp
            <span class="badge {{ $sBadge }}" style="font-size:12px; vertical-align:middle; margin-left:8px;">{{ $order->status ?? '-' }}</span>
        </h2>
    </div>
    <div class="page-header-actions">
        <a class="btn secondary" href="{{ route('ext.pedallion.orders.index') }}">Back to Orders</a>
    </div>
</div>

{{-- Order Info --}}
<div class="card mb-12">
    <h3 class="section-title mt-0">Order Details</h3>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px;">
        <div>
            <div class="text-xs text-muted">Order #</div>
            <div class="font-bold">{{ $order->order_number }}</div>
        </div>
        <div>
            <div class="text-xs text-muted">Buyer</div>
            <div>{{ $order->buyer_name ?? '-' }}</div>
        </div>
        <div>
            <div class="text-xs text-muted">Total</div>
            <div class="font-bold">{{ number_format($order->total, 2) }} {{ $order->currency }}</div>
        </div>
        <div>
            <div class="text-xs text-muted">Order Date</div>
            <div>{{ $order->order_date?->format('M d, Y H:i') ?? '-' }}</div>
        </div>
        <div>
            <div class="text-xs text-muted">Paid</div>
            <div>{{ $order->paid_at?->format('M d, Y H:i') ?? '-' }}</div>
        </div>
        <div>
            <div class="text-xs text-muted">Shipped</div>
            <div>{{ $order->shipped_at?->format('M d, Y H:i') ?? '-' }}</div>
        </div>
    </div>

    @if($order->shipping_address)
    <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border);">
        <div class="text-xs text-muted" style="margin-bottom:4px;">Shipping Address</div>
        <div class="text-sm">{{ $order->shipping_address }}</div>
    </div>
    @endif
</div>

{{-- Line Items --}}
<div class="card mb-12">
    <h3 class="section-title mt-0">Line Items <span class="text-secondary text-sm">({{ $order->products->count() }})</span></h3>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Product</th>
                    <th style="text-align:center;">Qty</th>
                    <th style="text-align:right;">Price</th>
                    <th style="text-align:right;">Total</th>
                    <th>ERP Product</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->products as $item)
                <tr>
                    <td><code class="text-sm">{{ $item->pedallion_sku }}</code></td>
                    <td>{{ $item->product_name }}</td>
                    <td style="text-align:center;">{{ $item->quantity }}</td>
                    <td style="text-align:right;">{{ number_format($item->price, 2) }}</td>
                    <td style="text-align:right; font-weight:600;">{{ number_format($item->total, 2) }}</td>
                    <td>
                        @if($item->product_id)
                            <a href="{{ route('products.edit', $item->product_id) }}" class="text-sm">#{{ $item->product_id }}</a>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Raw Payload --}}
@if($order->raw_payload)
<div class="card">
    <details>
        <summary style="cursor:pointer; font-weight:600; font-size:14px; padding:4px 0;">Raw API Payload</summary>
        <pre style="background:var(--surface); padding:12px; border-radius:var(--radius-md); font-size:11px; max-height:400px; overflow:auto; white-space:pre-wrap; margin-top:12px;">{{ json_encode($order->raw_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </details>
</div>
@endif
@endsection
