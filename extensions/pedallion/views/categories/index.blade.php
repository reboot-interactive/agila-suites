@extends('layouts.app')
@section('breadcrumb', 'Marketplace / Pedallion / Reference Data')

@section('content')
    <div class="page-header">
        <div>
            <h2>Pedallion Reference Data</h2>
            <div class="text-muted text-sm">Categories, manufacturers, and order status mapping from Pedallion.</div>
        </div>
        <div class="page-header-actions">
            <a class="btn secondary" href="{{ route('ext.pedallion.index') }}">Back to Pedallion</a>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="tabs mb-12" style="justify-content:flex-start;">
        <button class="tab active" data-tab="ped-cat-tab" type="button">
            Categories
            <span style="margin-left:6px; background:var(--surface); color:var(--text-secondary); font-size:11px; padding:1px 7px; border-radius:99px; font-weight:600;">{{ $categories->total() }}</span>
        </button>
        <button class="tab" data-tab="ped-mfg-tab" type="button">
            Manufacturers
            <span style="margin-left:6px; background:var(--surface); color:var(--text-secondary); font-size:11px; padding:1px 7px; border-radius:99px; font-weight:600;">{{ $manufacturers->count() }}</span>
        </button>
        <button class="tab" data-tab="ped-status-tab" type="button">
            Order Statuses
            <span style="margin-left:6px; background:var(--surface); color:var(--text-secondary); font-size:11px; padding:1px 7px; border-radius:99px; font-weight:600;">{{ $statusMaps->count() }}</span>
        </button>
    </div>

    {{-- ═══ TAB 1 — CATEGORIES ═══ --}}
    <div id="ped-cat-tab">
        {{-- Action bar --}}
        <div class="card mb-12">
            <div class="d-flex justify-between items-center flex-wrap gap-10">
                <div class="d-flex gap-10 items-end" style="flex:1;">
                    <form method="GET" action="{{ route('ext.pedallion.categories.index') }}" class="d-flex gap-10 items-end" style="flex:1;">
                        <div style="flex:1; max-width:320px;">
                            <label class="text-xs text-muted">Search</label>
                            <input type="text" name="q" value="{{ $q }}" placeholder="Search by name or ID..." class="input">
                        </div>
                        <button class="btn" type="submit">Search</button>
                        @if($q)
                            <a class="btn secondary" href="{{ route('ext.pedallion.categories.index') }}">Clear</a>
                        @endif
                    </form>
                </div>
                <div class="d-flex gap-8 items-center">
                    @if($setting?->last_category_sync_at)
                        <span class="text-xs text-muted">Synced {{ $setting->last_category_sync_at->diffForHumans() }}</span>
                    @endif
                    <form method="POST" action="{{ route('ext.pedallion.categories.fetch') }}" style="display:inline;">
                        @csrf
                        <button class="btn" type="submit">Fetch Categories</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th style="width:100px;">Category ID</th>
                        <th>Name</th>
                        <th style="width:60px;">Leaf</th>
                        <th style="width:100px;">Parent ID</th>
                        <th style="width:60px;">Level</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($categories as $c)
                        <tr>
                            <td>{{ $c->pedallion_category_id }}</td>
                            <td>
                                @if((int)$c->level > 0)
                                    <span class="text-muted" style="margin-right:4px;">{{ str_repeat('—', (int)$c->level) }}</span>
                                @endif
                                {{ $c->name }}
                                @if($c->leaf)
                                    <span class="badge badge-green" style="font-size:9px; margin-left:6px;">Leaf</span>
                                @endif
                            </td>
                            <td>
                                @if($c->leaf)
                                    <span class="badge badge-green">Yes</span>
                                @else
                                    <span class="text-muted">No</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $c->parent_id ?? '-' }}</td>
                            <td class="text-muted">{{ $c->level }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-muted" style="padding:24px; text-align:center;">
                                No categories cached yet. Click <strong>Fetch Categories</strong> to sync from Pedallion.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($categories->hasPages())
            <div class="d-flex justify-between items-center mt-12">
                <div class="text-muted text-xs">
                    Showing {{ $categories->firstItem() ?? 0 }} to {{ $categories->lastItem() ?? 0 }} of {{ $categories->total() }}
                </div>
                <div>
                    {{ $categories->links('vendor.pagination.simple') }}
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- ═══ TAB 2 — MANUFACTURERS ═══ --}}
    <div id="ped-mfg-tab" class="hidden">
        {{-- Action bar --}}
        <div class="card mb-12">
            <div class="d-flex justify-between items-center flex-wrap gap-10">
                <div class="d-flex gap-10 items-end" style="flex:1;">
                    <div style="flex:1; max-width:320px;">
                        <label class="text-xs text-muted">Search</label>
                        <input type="text" id="mfgSearchInput" placeholder="Filter manufacturers..." class="input">
                    </div>
                </div>
                <div class="d-flex gap-8 items-center">
                    @if($setting?->last_manufacturer_sync_at)
                        <span class="text-xs text-muted">Synced {{ $setting->last_manufacturer_sync_at->diffForHumans() }}</span>
                    @endif
                    <form method="POST" action="{{ route('ext.pedallion.manufacturers.fetch') }}" style="display:inline;">
                        @csrf
                        <button class="btn" type="submit">Fetch Manufacturers</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table class="table" id="mfgTable">
                    <thead>
                    <tr>
                        <th style="width:100px;">ID</th>
                        <th>Name</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($manufacturers as $m)
                        <tr>
                            <td>{{ $m->pedallion_manufacturer_id }}</td>
                            <td>{{ $m->name }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="text-muted" style="padding:24px; text-align:center;">
                                No manufacturers cached yet. Click <strong>Fetch Manufacturers</strong> to sync from Pedallion.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══ TAB 3 — ORDER STATUSES ═══ --}}
    <div id="ped-status-tab" class="hidden">
        <div class="card mb-12">
            <div class="d-flex justify-between items-center flex-wrap gap-10">
                <div>
                    <span class="font-semibold text-sm">Order Status Mapping</span>
                    <div class="hint">Map Pedallion order statuses to ERP order statuses. Fetched from <code>GET /references</code>.</div>
                </div>
                <form method="POST" action="{{ route('ext.pedallion.fetch_order_statuses') }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn">Fetch Statuses from API</button>
                </form>
            </div>
        </div>

        @if($statusMaps->isEmpty())
            <div class="card">
                <div class="text-sm text-muted" style="padding:20px 0; text-align:center;">
                    No order statuses found. Click <strong>Fetch Statuses from API</strong> to load them from Pedallion.
                </div>
            </div>
        @else
            <div class="card">
                <form method="POST" action="{{ route('ext.pedallion.order_status_map') }}">
                    @csrf
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width:45%;">Pedallion Status</th>
                                    <th>ERP Order Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($statusMaps as $map)
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <code style="font-size:11.5px; background:var(--surface-alt); padding:2px 8px; border-radius:4px; border:1px solid var(--border-light);">{{ $map->pedallion_status }}</code>
                                        @if($map->pedallion_status_label && $map->pedallion_status_label !== $map->pedallion_status)
                                            <span class="text-muted" style="margin-left:6px;">{{ $map->pedallion_status_label }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <select name="map[{{ $map->pedallion_status }}]" class="input" style="max-width:260px;">
                                            @foreach($erpOrderStatuses as $s)
                                                <option value="{{ $s->order_status_id }}" {{ (int)$map->order_status_id === (int)$s->order_status_id ? 'selected' : '' }}>
                                                    {{ $s->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top:14px;">
                        <button class="btn" type="submit">Save Mapping</button>
                    </div>
                </form>
            </div>
        @endif
    </div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tabIds = ['ped-cat-tab', 'ped-mfg-tab', 'ped-status-tab'];
    var tabs = document.querySelectorAll('[data-tab]');
    var storageKey = 'ped-ref-tab';

    function switchTab(tabId) {
        tabs.forEach(function(t) { t.classList.toggle('active', t.dataset.tab === tabId); });
        tabIds.forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.classList.toggle('hidden', id !== tabId);
        });
    }

    tabs.forEach(function(btn) {
        btn.addEventListener('click', function() {
            switchTab(this.dataset.tab);
            localStorage.setItem(storageKey, this.dataset.tab);
        });
    });

    // Restore tab: URL param > localStorage > default
    var urlTab = new URLSearchParams(window.location.search).get('tab');
    var saved = urlTab ? ('ped-' + urlTab + '-tab') : localStorage.getItem(storageKey);
    if (saved && tabIds.indexOf(saved) !== -1) switchTab(saved);

    // Client-side manufacturer filter
    var mfgInput = document.getElementById('mfgSearchInput');
    var mfgTable = document.getElementById('mfgTable');
    if (mfgInput && mfgTable) {
        mfgInput.addEventListener('input', function() {
            var filter = this.value.toLowerCase();
            var rows = mfgTable.querySelectorAll('tbody tr');
            rows.forEach(function(row) {
                var name = row.textContent.toLowerCase();
                row.style.display = name.indexOf(filter) !== -1 ? '' : 'none';
            });
        });
    }
});
</script>
@endpush
@endsection
