@extends('layouts.app')
@section('breadcrumb', 'Marketplace / Pedallion / Product Groups')

@section('content')
<div class="page-header">
    <div>
        <h2>Pedallion Product Groups <span class="text-secondary text-sm">({{ $groups->count() }})</span></h2>
        <div class="text-muted text-sm">Map catalog categories and manufacturers to Pedallion categories.</div>
    </div>
    <div class="page-header-actions">
        <a class="btn secondary" href="{{ route('ext.pedallion.index') }}">Back to Pedallion</a>
        <a class="btn" href="{{ route('ext.pedallion.product-groups.create') }}">+ New Product Group</a>
    </div>
</div>

@forelse($groups as $g)
<div class="card mb-12">
    <div class="d-flex justify-between items-start flex-wrap gap-10">
        <div style="flex:1;">
            <h3 class="section-title mt-0 mb-8">{{ $g->name }}</h3>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px;">
                <div>
                    <div class="text-xs text-muted" style="margin-bottom:4px;">Catalog Categories</div>
                    <div>
                        @forelse(($g->catalog_category_ids ?? []) as $cid)
                            <span class="badge badge-gray" style="margin:0 2px 2px 0;">{{ $categoryNames[$cid] ?? "#{$cid}" }}</span>
                        @empty
                            <span class="text-muted text-sm">Any</span>
                        @endforelse
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted" style="margin-bottom:4px;">Manufacturers</div>
                    <div>
                        @forelse(($g->manufacturer_ids ?? []) as $mid)
                            <span class="badge badge-gray" style="margin:0 2px 2px 0;">{{ $manufacturerNames[$mid] ?? "#{$mid}" }}</span>
                        @empty
                            <span class="text-muted text-sm">Any</span>
                        @endforelse
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted" style="margin-bottom:4px;">Pedallion Category</div>
                    <div class="text-sm">{{ $pedallionCategories[$g->pedallion_category_id] ?? ($g->pedallion_category_id ? "#{$g->pedallion_category_id}" : '—') }}</div>
                </div>
                <div>
                    <div class="text-xs text-muted" style="margin-bottom:4px;">Condition</div>
                    <div>
                        @if($g->condition)
                            <span class="badge badge-green">{{ ucfirst($g->condition) }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex gap-6" style="padding-top:4px;">
            <a class="btn btn-sm" href="{{ route('ext.pedallion.product-groups.edit', $g->id) }}">Edit</a>
            <a class="btn btn-sm secondary" href="{{ route('ext.pedallion.product-groups.products', $g->id) }}">Products</a>
            <form method="POST" action="{{ route('ext.pedallion.product-groups.destroy', $g->id) }}" style="display:inline;" data-confirm="Delete this product group?">
                @csrf @method('DELETE')
                <button class="btn btn-sm danger" type="submit">Delete</button>
            </form>
        </div>
    </div>
</div>
@empty
<div class="card">
    <div style="padding:32px; text-align:center;">
        <div class="text-muted" style="margin-bottom:12px;">No product groups created yet.</div>
        <a class="btn" href="{{ route('ext.pedallion.product-groups.create') }}">+ Create First Product Group</a>
    </div>
</div>
@endforelse
@endsection
