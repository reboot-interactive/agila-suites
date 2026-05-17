@extends('layouts.app')
@section('breadcrumb', 'Marketplace / Pedallion / Product Groups / ' . $group->name . ' / Products')

@section('content')
<div class="page-header">
    <div>
        <h2>{{ $group->name }} <span class="text-secondary text-sm">({{ $products->count() }} products)</span></h2>
        <div class="text-muted text-sm">Products matched by this product group's category and manufacturer filters.</div>
    </div>
    <div class="page-header-actions">
        <a class="btn secondary" href="{{ route('ext.pedallion.product-groups.index') }}">Back to Product Groups</a>
        <form method="POST" action="{{ route('ext.pedallion.product-groups.sync_products', $group->id) }}" style="display:inline;">
            @csrf
            <button class="btn" type="submit">Sync Products</button>
        </form>
    </div>
</div>

{{-- Product group summary --}}
<div class="card mb-12">
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px;">
        <div>
            <div class="text-xs text-muted">Catalog Categories</div>
            <div class="text-sm">
                @forelse(($group->catalog_category_ids ?? []) as $cid)
                    <span class="badge badge-gray" style="margin:0 2px 2px 0;">{{ $categoryNames[$cid] ?? "#{$cid}" }}</span>
                @empty
                    <span class="text-muted">Any</span>
                @endforelse
            </div>
        </div>
        <div>
            <div class="text-xs text-muted">Manufacturers</div>
            <div class="text-sm">
                @forelse(($group->manufacturer_ids ?? []) as $mid)
                    <span class="badge badge-gray" style="margin:0 2px 2px 0;">{{ $manufacturerNames[$mid] ?? "#{$mid}" }}</span>
                @empty
                    <span class="text-muted">Any</span>
                @endforelse
            </div>
        </div>
        <div>
            <div class="text-xs text-muted">Pedallion Category</div>
            <div class="text-sm">{{ $pedallionCategoryName ?? '—' }}</div>
        </div>
        <div>
            <div class="text-xs text-muted">Condition</div>
            <div>
                @if($group->condition)
                    <span class="badge badge-green">{{ ucfirst($group->condition) }}</span>
                @else
                    <span class="text-muted">—</span>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:60px;">ID</th>
                    <th>SKU</th>
                    <th>Name</th>
                    <th style="text-align:right;">Price</th>
                    <th style="text-align:center;">Qty</th>
                    <th style="width:100px;">ERP Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $p)
                <tr>
                    <td>{{ $p->product_id }}</td>
                    <td><code class="text-sm">{{ $p->sku ?: '-' }}</code></td>
                    <td>
                        <a href="{{ route('products.edit', $p->product_id) }}">{{ $p->name }}</a>
                    </td>
                    <td style="text-align:right;">{{ number_format($p->price, 2) }}</td>
                    <td style="text-align:center;">{{ (int)$p->quantity }}</td>
                    <td>
                        @if((int)($p->status ?? 0) === 1)
                            <span class="badge badge-green">Enabled</span>
                        @else
                            <span class="badge badge-red">Disabled</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-muted" style="padding:24px; text-align:center;">
                        No matching products. Adjust the product group filters or click <strong>Sync Products</strong>.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
