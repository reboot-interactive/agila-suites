<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer {{ $transfer->reference }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 13px; color: #1a1a1a; padding: 20px 30px; }
        .header { text-align: center; margin-bottom: 24px; border-bottom: 2px solid #333; padding-bottom: 16px; }
        .header h1 { font-size: 20px; margin-bottom: 4px; }
        .header .ref { font-size: 15px; color: #555; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 13px; }
        .meta-col { line-height: 1.8; }
        .meta-col .label { color: #666; display: inline-block; width: 100px; }
        .meta-col .value { font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #ccc; padding: 6px 10px; text-align: left; }
        th { background: #f3f4f6; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }
        td { font-size: 13px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row td { font-weight: 700; border-top: 2px solid #333; }
        .footer { margin-top: 24px; font-size: 11px; color: #999; text-align: center; }
        .status-badge { display: inline-block; padding: 2px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .status-draft { background: #e2e8f0; color: #475569; }
        .status-confirmed { background: #dcfce7; color: #166534; }
        .status-voided { background: #fee2e2; color: #991b1b; }

        @media print {
            body { padding: 0; }
            @page { margin: 15mm; }
        }
    </style>
</head>
<body>
@php
    $totalQty = collect($enrichedItems)->sum('quantity');
@endphp

<div class="header">
    <h1>Warehouse Transfer</h1>
    <div class="ref">
        {{ $transfer->reference }}
        <span class="status-badge status-{{ $transfer->status }}">{{ ucfirst($transfer->status) }}</span>
    </div>
</div>

<div class="meta">
    <div class="meta-col">
        <div><span class="label">From:</span> <span class="value">{{ $transfer->fromWarehouse->name ?? '—' }}</span></div>
        <div><span class="label">To:</span> <span class="value">{{ $transfer->toWarehouse->name ?? '—' }}</span></div>
    </div>
    <div class="meta-col">
        <div><span class="label">Date:</span> <span class="value">{{ $transfer->created_at->format('Y-m-d H:i') }}</span></div>
        <div><span class="label">Created By:</span> <span class="value">{{ $transfer->user_name ?? '—' }}</span></div>
    </div>
</div>

@if($transfer->note)
    <div style="margin-bottom: 16px; font-size: 13px;">
        <strong>Note:</strong> {{ $transfer->note }}
    </div>
@endif

<table>
    <thead>
    <tr>
        <th style="width: 36px;">#</th>
        <th>Product</th>
        <th style="width: 120px;">SKU</th>
        <th style="width: 140px;">Option</th>
        <th style="width: 70px;" class="text-right">Qty</th>
    </tr>
    </thead>
    <tbody>
    @foreach($enrichedItems as $idx => $item)
        <tr>
            <td class="text-center">{{ $idx + 1 }}</td>
            <td>{{ $item->product_name }}</td>
            <td>{{ $item->sku ?: '—' }}</td>
            <td>{{ $item->option_label ?: '—' }}</td>
            <td class="text-right">{{ $item->quantity }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
    <tr class="total-row">
        <td colspan="4" class="text-right">Total Quantity</td>
        <td class="text-right">{{ $totalQty }}</td>
    </tr>
    </tfoot>
</table>

<div class="footer">
    Printed on {{ now()->format('Y-m-d H:i') }}
</div>

<script>window.print();</script>
</body>
</html>
