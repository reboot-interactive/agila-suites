@extends('layouts.app')
@section('breadcrumb', 'Warehousing / Stock by Location')

@section('content')
<div class="page-header">
    <h2>Stock by Location <span class="text-secondary text-sm">({{ $rows->total() }})</span></h2>
</div>

<div class="card mb-16">
    <form method="GET" action="{{ route('ext.warehousing.inventory.index') }}" class="d-flex gap-12 flex-wrap items-center">
        <div>
            <label class="text-xs text-secondary">Search</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="Product name, SKU, model..." class="input">
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
            <label class="text-xs text-secondary">Category</label>
            <select name="category_id" class="input">
                <option value="">All Categories</option>
                @foreach($categories as $catId => $catName)
                    <option value="{{ $catId }}" {{ $categoryId == $catId ? 'selected' : '' }}>{{ $catName }}</option>
                @endforeach
            </select>
        </div>
        <div class="d-flex gap-8" style="align-self:flex-end;">
            <button type="submit" class="btn">Search</button>
            <a href="{{ route('ext.warehousing.inventory.index') }}" class="btn secondary">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Product</th>
            <th>SKU</th>
            <th>Option</th>
            @foreach($warehouses as $w)
                <th class="text-right">{{ $w->name }}</th>
            @endforeach
            <th class="text-right">Total</th>
        </tr>
        </thead>
        <tbody>
        @forelse($rows as $row)
            @php
                $key = $row->product_id . ':' . $row->product_option_value_id;
                $whQtys = $inventoryMap[$key] ?? [];
                $total = array_sum($whQtys);
                $sku = ($row->product_option_value_id > 0 && !empty($row->option_sku))
                    ? $row->option_sku
                    : $row->product_sku;
                $productName = html_entity_decode($row->product_name ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $optionName = html_entity_decode($row->option_value_name ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            @endphp
            <tr>
                <td>
                    <div class="font-semibold truncate">{{ $productName }}</div>
                </td>
                <td class="text-xs text-secondary">{{ $sku ?: '—' }}</td>
                <td>
                    @if($row->product_option_value_id > 0 && $optionName)
                        <span class="badge badge-default">{{ $optionName }}</span>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                @foreach($warehouses as $w)
                    @php
                        $qty = $whQtys[$w->id] ?? 0;
                    @endphp
                    <td class="text-right{{ $qty <= 0 ? ' inventory-zero' : '' }}">{{ $qty }}</td>
                @endforeach
                <td class="text-right font-semibold{{ $total <= 0 ? ' inventory-zero' : '' }}">{{ $total }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ 3 + $warehouses->count() + 1 }}">No inventory records found.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
    </div>

    @if($rows->hasPages())
        <div class="mt-16">
            {{ $rows->onEachSide(1)->links('vendor.pagination.simple') }}
        </div>
    @endif
</div>
@endsection
