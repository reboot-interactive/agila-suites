@extends('layouts.app')
@section('breadcrumb', 'Marketplace / Pedallion / Products')

@section('title', 'Pedallion Products')

@section('content')
    <div class="page-header">
        <div>
            <h2>Pedallion Products</h2>
            <div class="text-muted text-sm">Push catalog products to Pedallion and sync stock/price.</div>
        </div>
        <div class="page-header-actions">
            <a class="btn secondary" href="{{ route('ext.pedallion.index') }}">Back to Pedallion</a>
            <a class="btn secondary" href="{{ route('ext.pedallion.product-groups.index') }}">Product Groups</a>
        </div>
    </div>

    {{-- Link Product --}}
    <div class="card mb-12">
        <h3 class="section-title mt-0">Link Product</h3>
        <form method="POST" action="{{ route('ext.pedallion.products.link') }}" class="d-flex gap-10 items-end">
            @csrf
            <div>
                <label class="text-xs text-muted">Catalog Product ID</label>
                <input class="input" name="product_id" type="number" style="width:140px;">
            </div>
            <div>
                <label class="text-xs text-muted">Pedallion SKU</label>
                <input class="input" name="pedallion_sku" style="width:200px;">
            </div>
            <button class="btn" type="submit">Link</button>
        </form>
    </div>

    {{-- Filter toolbar --}}
    <div class="card mb-12">
        <form method="GET" action="{{ route('ext.pedallion.products.index') }}" style="display:grid; grid-template-columns:1fr auto auto auto auto auto; gap:10px; align-items:end;">
            <div>
                <label class="text-xs text-muted">Search</label>
                <input type="text" name="q" class="input" value="{{ $q ?? '' }}" placeholder="Name, model or SKU...">
            </div>
            <div>
                <label class="text-xs text-muted">Manufacturer</label>
                <select name="manufacturer" class="input">
                    <option value="all">All Manufacturers</option>
                    @foreach(($allManufacturers ?? collect()) as $mId => $mName)
                        <option value="{{ $mId }}" {{ (string)($manufacturerFilter ?? '') === (string)$mId ? 'selected' : '' }}>{{ $mName }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-muted">Product Group</label>
                <select name="product_group" class="input">
                    <option value="all" {{ ($groupFilter ?? 'all') === 'all' ? 'selected' : '' }}>All Groups</option>
                    <option value="none" {{ ($groupFilter ?? '') === 'none' ? 'selected' : '' }}>No Group</option>
                    @foreach(($allGroups ?? collect()) as $gId => $gName)
                        <option value="{{ $gId }}" {{ (string)($groupFilter ?? '') === (string)$gId ? 'selected' : '' }}>{{ $gName }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-muted">ERP Status</label>
                <select name="erp_status" class="input">
                    <option value="all" {{ ($erpStatus ?? 'all') === 'all' ? 'selected' : '' }}>All</option>
                    <option value="enabled" {{ ($erpStatus ?? '') === 'enabled' ? 'selected' : '' }}>Enabled</option>
                    <option value="disabled" {{ ($erpStatus ?? '') === 'disabled' ? 'selected' : '' }}>Disabled</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-muted">Sync Status</label>
                <select name="sync_status" class="input">
                    <option value="all" {{ ($syncStatus ?? 'all') === 'all' ? 'selected' : '' }}>All</option>
                    <option value="linked" {{ ($syncStatus ?? '') === 'linked' ? 'selected' : '' }}>Linked</option>
                    <option value="not_linked" {{ ($syncStatus ?? '') === 'not_linked' ? 'selected' : '' }}>Not Linked</option>
                    <option value="synced" {{ ($syncStatus ?? '') === 'synced' ? 'selected' : '' }}>Synced</option>
                    <option value="pending" {{ ($syncStatus ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="error" {{ ($syncStatus ?? '') === 'error' ? 'selected' : '' }}>Error</option>
                </select>
            </div>
            <div class="d-flex gap-6">
                <button type="submit" class="btn small">Filter</button>
                @if(($q ?? '') !== '' || ($syncStatus ?? 'all') !== 'all' || ($manufacturerFilter ?? 'all') !== 'all' || ($groupFilter ?? 'all') !== 'all' || ($erpStatus ?? 'all') !== 'all')
                    <a href="{{ route('ext.pedallion.products.index') }}" class="btn small secondary">Clear</a>
                @endif
            </div>
        </form>
    </div>

    <div class="card">
        {{-- Bulk action bar --}}
        <div id="bulk-bar" style="display:none; padding:10px 16px; margin-bottom:12px; background:var(--accent-light); border:1px solid rgba(59,130,246,.2); border-radius:var(--radius-md); align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
            <span style="font-size:13px; font-weight:600; color:var(--accent);"><span id="bulk-count">0</span> selected</span>
            <div class="d-flex gap-6 flex-wrap">
                <form method="POST" action="{{ route('ext.pedallion.products.bulk_sync') }}" class="bulk-action-form bulk-push-form" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn small secondary">Sync from Pedallion</button>
                </form>
                <form method="POST" action="{{ route('ext.pedallion.products.bulk_push') }}" class="bulk-action-form bulk-push-form" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn small">Push to Pedallion</button>
                </form>
                <form method="POST" action="{{ route('ext.pedallion.products.bulk_sync_qty') }}" class="bulk-action-form" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn small">Sync Qty</button>
                </form>
                <form method="POST" action="{{ route('ext.pedallion.products.bulk_sync_price') }}" class="bulk-action-form" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn small">Sync Price</button>
                </form>
                <form method="POST" action="{{ route('ext.pedallion.products.bulk_unlink') }}" class="bulk-action-form bulk-unlink-form" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn small danger">Unlink</button>
                </form>
                <form method="POST" action="{{ route('ext.pedallion.products.bulk_delete') }}" class="bulk-action-form bulk-delete-form" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn small danger">Delete from Pedallion</button>
                </form>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table" id="pedallionProductsTable">
                <thead>
                <tr>
                    <th style="width:36px;"><input type="checkbox" id="checkAll" title="Select all"></th>
                    <th style="width:60px;">ID</th>
                    <th style="width:56px;">Image</th>
                    <th>Product Name</th>
                    <th>SKU</th>
                    <th>Manufacturer</th>
                    <th style="width:120px;">Group</th>
                    <th style="width:60px;">Stock</th>
                    <th style="width:80px;">Price</th>
                    <th>Pedallion SKU</th>
                    <th style="width:90px;">ERP Status</th>
                    <th style="width:100px;">Sync Status</th>
                    <th style="width:160px;">Error</th>
                    <th style="width:50px;"></th>
                </tr>
                </thead>
                <tbody>
                @forelse($products as $p)
                    @php
                        $links = $pedallionLinks->get($p->product_id, collect());
                        $link = $links->first();
                        $pGroups = $groupsByProductId[$p->product_id] ?? [];
                    @endphp
                    <tr>
                        <td><input type="checkbox" class="row-check" value="{{ $link->id ?? '' }}" data-product-id="{{ $p->product_id }}"></td>
                        <td>{{ $p->product_id }}</td>
                        <td>
                            @if($p->image)
                                <img src="{{ asset('storage/' . ltrim($p->image, '/')) }}" alt="" style="width:40px; height:40px; object-fit:cover; border-radius:4px;">
                            @else
                                <span class="text-muted text-xs">-</span>
                            @endif
                        </td>
                        <td>
                            <div class="font-bold">{{ $p->name }}</div>
                        </td>
                        <td><code>{{ $p->sku ?: '-' }}</code></td>
                        <td>{{ $p->manufacturer_name ?? '-' }}</td>
                        <td>
                            @if(count($pGroups) > 0)
                                <span class="text-xs">{{ $pGroups[0] }}@if(count($pGroups) > 1) <span class="text-muted">(+{{ count($pGroups) - 1 }})</span>@endif</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>{{ (int)$p->quantity }}</td>
                        <td>{{ number_format((float)$p->price, 2) }}</td>
                        <td>
                            @if($link)
                                <code class="text-xs">{{ $link->pedallion_sku }}</code>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if((int)$p->status === 1)
                                <span class="badge badge-green">Enabled</span>
                            @else
                                <span class="badge badge-red">Disabled</span>
                            @endif
                        </td>
                        <td>
                            @if($link)
                                @if($link->sync_status === 'synced')
                                    <span class="badge badge-green">Synced</span>
                                @elseif($link->sync_status === 'error')
                                    <span class="badge badge-red">Error</span>
                                @else
                                    <span class="badge badge-yellow">Pending</span>
                                @endif
                            @else
                                <span class="badge badge-gray">Not Linked</span>
                            @endif
                        </td>
                        <td>
                            @if($link && $link->sync_status === 'error' && $link->sync_error)
                                <div class="text-xs" style="color:var(--danger); word-break:break-word; line-height:1.4;">
                                    {{ Str::limit($link->sync_error, 80) }}
                                </div>
                                @if($link->last_pushed_at)
                                    <div class="text-xs text-muted" style="margin-top:2px;">{{ $link->last_pushed_at->diffForHumans() }}</div>
                                @endif
                            @elseif($link && $link->sync_status === 'synced')
                                <span class="badge badge-green">OK</span>
                                @if($link->last_pushed_at)
                                    <div class="text-xs text-muted" style="margin-top:2px;">{{ $link->last_pushed_at->diffForHumans() }}</div>
                                @endif
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            <div style="position:relative;" class="row-actions">
                                <button type="button" class="btn small secondary action-toggle" style="padding:0 8px; min-width:32px;" title="Actions">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                                </button>
                                <div class="action-menu" style="display:none; position:absolute; right:0; top:100%; margin-top:4px; z-index:50; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-md); box-shadow:var(--shadow-lg); min-width:170px; padding:4px 0;">
                                    <a href="{{ route('products.edit', $p->product_id) }}" class="action-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        Edit Product
                                    </a>
                                    <form method="POST" action="{{ route('ext.pedallion.products.sync', $p->product_id) }}" style="margin:0;">
                                        @csrf
                                        <button type="submit" class="action-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                                            Sync from Pedallion
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('ext.pedallion.products.push', $p->product_id) }}" style="margin:0;">
                                        @csrf
                                        <button type="submit" class="action-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
                                            Push to Pedallion
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('ext.pedallion.products.sync_qty', $p->product_id) }}" style="margin:0;">
                                        @csrf
                                        <button type="submit" class="action-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                                            Sync Qty
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('ext.pedallion.products.sync_price', $p->product_id) }}" style="margin:0;">
                                        @csrf
                                        <button type="submit" class="action-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                            Sync Price
                                        </button>
                                    </form>
                                    @if($link)
                                        <div style="border-top:1px solid var(--border); margin:4px 0;"></div>
                                        <form method="POST" action="{{ route('ext.pedallion.products.unlink', $link->id) }}" style="margin:0;" data-confirm="Unlink this product from Pedallion? The Pedallion listing is not deleted.">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="action-item" style="color:var(--danger);">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                                                Unlink from Pedallion
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('ext.pedallion.products.delete', $p->product_id) }}" style="margin:0;" data-confirm="Delete this product from Pedallion? This cannot be undone.">
                                            @csrf
                                            <button type="submit" class="action-item" style="color:var(--danger);">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                Delete from Pedallion
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="14" class="text-muted">No products found. Create a <a href="{{ route('ext.pedallion.product-groups.index') }}">Product Group</a> to filter catalog products.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-between items-center mt-12">
            <div class="text-muted text-xs">
                Showing {{ $products->firstItem() ?? 0 }} to {{ $products->lastItem() ?? 0 }} of {{ $products->total() }}
            </div>
            <div>
                {{ $products->links('vendor.pagination.simple') }}
            </div>
        </div>
    </div>

<div class="bulk-loading-overlay" id="loadingOverlay">
    <div class="bulk-loading-box">
        <div class="spinner"></div>
        <div class="msg" id="loadingMsg">Processing...</div>
    </div>
</div>

@push('scripts')
<script>
(function(){
    var table = document.getElementById('pedallionProductsTable');
    if (!table) return;

    var checkAll = document.getElementById('checkAll');
    var bulkBar = document.getElementById('bulk-bar');
    var bulkCount = document.getElementById('bulk-count');

    function rowChecks() { return Array.from(table.querySelectorAll('.row-check')); }

    function updateState() {
        var boxes = rowChecks();
        var checked = boxes.filter(function(b){ return b.checked; }).length;

        if (checkAll) {
            if (checked === 0) {
                checkAll.indeterminate = false;
                checkAll.checked = false;
            } else if (checked === boxes.length) {
                checkAll.indeterminate = false;
                checkAll.checked = true;
            } else {
                checkAll.indeterminate = true;
                checkAll.checked = false;
            }
        }

        if (bulkBar) bulkBar.style.display = checked > 0 ? 'flex' : 'none';
        if (bulkCount) bulkCount.textContent = checked;
    }

    if (checkAll) {
        checkAll.addEventListener('change', function(){
            rowChecks().forEach(function(b){ b.checked = checkAll.checked; });
            updateState();
        });
    }

    rowChecks().forEach(function(b){ b.addEventListener('change', updateState); });

    // Bulk action forms: inject selected IDs as hidden inputs
    function doBulkSubmit(form, boxes) {
        form.querySelectorAll('input[name="link_ids[]"], input[name="product_ids[]"]').forEach(function(n){ n.remove(); });

        if (form.classList.contains('bulk-push-form')) {
            boxes.forEach(function(b){
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'product_ids[]';
                inp.value = b.dataset.productId;
                form.appendChild(inp);
            });
        } else {
            var ids = boxes.filter(function(b){ return b.value; }).map(function(b){ return b.value; });
            ids.forEach(function(id){
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'link_ids[]';
                inp.value = id;
                form.appendChild(inp);
            });
        }

        var overlay = document.getElementById('loadingOverlay');
        var msg = document.getElementById('loadingMsg');
        if (overlay) {
            var count = form.querySelectorAll('input[name="link_ids[]"], input[name="product_ids[]"]').length;
            if (msg) msg.textContent = 'Processing ' + count + ' item(s)...';
            overlay.classList.add('active');
        }
        form.querySelector('button[type="submit"]').disabled = true;
        form._confirmed = true;
        form.requestSubmit ? form.requestSubmit() : form.submit();
    }

    document.querySelectorAll('form.bulk-action-form').forEach(function(form){
        form.addEventListener('submit', function(e){
            if (form._confirmed) { form._confirmed = false; return; }

            e.preventDefault();

            var boxes = rowChecks().filter(function(b){ return b.checked; });
            if (!boxes.length) {
                showFlashError('Please select at least 1 product.');
                return;
            }

            var confirmMsg = null;
            if (form.classList.contains('bulk-push-form')) {
                confirmMsg = 'Process ' + boxes.length + ' selected product(s)?';
            } else if (form.classList.contains('bulk-delete-form')) {
                confirmMsg = 'Delete ' + boxes.length + ' selected product(s) from Pedallion? This cannot be undone.';
            } else if (form.classList.contains('bulk-unlink-form')) {
                confirmMsg = 'Unlink ' + boxes.length + ' selected product(s) from Pedallion?';
            }

            if (!form.classList.contains('bulk-push-form')) {
                var ids = boxes.filter(function(b){ return b.value; }).map(function(b){ return b.value; });
                if (!ids.length) {
                    showFlashError('Selected products must be linked to Pedallion for this action.');
                    return;
                }
            }

            if (confirmMsg) {
                confirmModal(confirmMsg).then(function(ok) {
                    if (ok) doBulkSubmit(form, boxes);
                });
            } else {
                doBulkSubmit(form, boxes);
            }
        });
    });

    // Loading overlay for individual action forms
    document.querySelectorAll('.action-menu form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (e.defaultPrevented) return;
            var overlay = document.getElementById('loadingOverlay');
            var msg = document.getElementById('loadingMsg');
            if (overlay) {
                if (msg) msg.textContent = 'Processing...';
                overlay.classList.add('active');
            }
        });
    });

    // Action dropdown toggle
    var openMenu = null;
    var tableWrap = table ? table.closest('.table-wrap') : null;

    function closeMenu() {
        if (openMenu) {
            openMenu.style.display = 'none';
            openMenu = null;
        }
        if (tableWrap) tableWrap.classList.remove('menu-open');
    }

    document.addEventListener('click', function(e) {
        var toggle = e.target.closest('.action-toggle');
        if (toggle) {
            e.stopPropagation();
            var menu = toggle.nextElementSibling;
            if (!menu) return;

            if (openMenu && openMenu !== menu) {
                openMenu.style.display = 'none';
            }

            if (menu.style.display === 'none') {
                menu.style.display = 'block';
                openMenu = menu;
                if (tableWrap) tableWrap.classList.add('menu-open');
            } else {
                closeMenu();
            }
            return;
        }

        if (openMenu && !e.target.closest('.action-menu')) {
            closeMenu();
        }
    });
})();
</script>
@endpush
@endsection
