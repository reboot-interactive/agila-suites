@extends('layouts.app')
@section('breadcrumb', 'Marketplace / Pedallion / Product Groups / ' . ($mode === 'edit' ? 'Edit' : 'Create'))

@section('content')
<div class="page-header">
    <h2>{{ $mode === 'edit' ? 'Edit' : 'Create' }} Pedallion Product Group</h2>
    <div class="page-header-actions">
        <a class="btn secondary" href="{{ route('ext.pedallion.product-groups.index') }}">Back to Product Groups</a>
    </div>
</div>


<div class="card">
    <form method="POST" action="{{ $mode === 'edit' ? route('ext.pedallion.product-groups.update', $group->id) : route('ext.pedallion.product-groups.store') }}">
        @csrf
        @if($mode === 'edit') @method('PUT') @endif

        @php
            $selectedCatIds = old('catalog_category_ids', $group->catalog_category_ids ?? []) ?? [];
            $selectedMfgIds = old('manufacturer_ids', $group->manufacturer_ids ?? []) ?? [];
            $selectedCatIds = array_map('intval', (array)$selectedCatIds);
            $selectedMfgIds = array_map('intval', (array)$selectedMfgIds);
        @endphp

        <div style="display:flex; flex-direction:column; gap:16px;">
            <div>
                <label>Product Group Name</label>
                <input class="input" name="name" value="{{ old('name', $group->name ?? '') }}">
            </div>

            <div class="hint">Type to search, click to add.</div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                    <label>Catalog Categories</label>
                    <div style="position:relative;">
                        <input type="text" class="input" id="catSearch" placeholder="Search categories..." autocomplete="off">
                        <div id="catDropdown" style="display:none; position:absolute; z-index:10; left:0; right:0; max-height:200px; overflow-y:auto; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-md); margin-top:2px;"></div>
                    </div>
                    <div id="catSelected" style="border:1px solid var(--border); border-radius:var(--radius-md); min-height:40px; margin-top:8px; padding:4px;"></div>
                    <div class="hint">Products in these categories will be included.</div>
                </div>
                <div>
                    <label>Manufacturers</label>
                    <div style="position:relative;">
                        <input type="text" class="input" id="mfgSearch" placeholder="Search manufacturers..." autocomplete="off">
                        <div id="mfgDropdown" style="display:none; position:absolute; z-index:10; left:0; right:0; max-height:200px; overflow-y:auto; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-md); margin-top:2px;"></div>
                    </div>
                    <div id="mfgSelected" style="border:1px solid var(--border); border-radius:var(--radius-md); min-height:40px; margin-top:8px; padding:4px;"></div>
                </div>
            </div>

            <div>
                <label>Pedallion Category</label>
                <select class="input" name="pedallion_category_id">
                    <option value="">— Select —</option>
                    @foreach($pedallionCategories as $pc)
                        <option value="{{ $pc->pedallion_category_id }}" {{ old('pedallion_category_id', $group->pedallion_category_id) == $pc->pedallion_category_id ? 'selected' : '' }}>
                            {{ str_repeat('— ', $pc->level) }}{{ $pc->name }} ({{ $pc->pedallion_category_id }})
                        </option>
                    @endforeach
                </select>
                <div class="hint">Target Pedallion category for products in this group. Fetch categories first if empty.</div>
            </div>

            <div>
                <label>Condition</label>
                <select class="input" name="condition">
                    <option value="">— Select —</option>
                    <option value="new" {{ old('condition', $group->condition) === 'new' ? 'selected' : '' }}>New</option>
                    <option value="used" {{ old('condition', $group->condition) === 'used' ? 'selected' : '' }}>Used</option>
                    <option value="refurbished" {{ old('condition', $group->condition) === 'refurbished' ? 'selected' : '' }}>Refurbished</option>
                </select>
                <div class="hint">Item condition for Pedallion listing.</div>
            </div>

            <div>
                <button class="btn" type="submit">{{ $mode === 'edit' ? 'Update' : 'Create' }} Product Group</button>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
    function initListPicker(config) {
        var items = config.items;
        var selectedIds = config.selectedIds;
        var searchEl = config.searchEl;
        var dropdownEl = config.dropdownEl;
        var listEl = config.listEl;
        var fieldName = config.fieldName;
        var selected = new Map();

        selectedIds.forEach(function(id) {
            var item = items.find(function(i) { return i.id === id; });
            if (item) selected.set(id, item.name);
        });

        function render() {
            listEl.textContent = '';
            if (selected.size === 0) {
                var placeholder = document.createElement('div');
                placeholder.style.cssText = 'padding:8px; color:var(--text-muted); font-size:13px;';
                placeholder.textContent = 'None selected';
                listEl.appendChild(placeholder);
                return;
            }
            selected.forEach(function(name, id) {
                var row = document.createElement('div');
                row.style.cssText = 'display:flex; justify-content:space-between; align-items:center; padding:4px 8px;';
                var span = document.createElement('span');
                span.textContent = name;
                row.appendChild(span);
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = '\u00D7';
                btn.style.cssText = 'background:none; border:none; cursor:pointer; font-size:18px; color:var(--text-muted); padding:0 4px; line-height:1;';
                btn.addEventListener('click', function() { selected.delete(id); render(); });
                row.appendChild(btn);
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = fieldName;
                inp.value = id;
                row.appendChild(inp);
                listEl.appendChild(row);
            });
        }

        function showDropdown(filter) {
            var lower = filter.toLowerCase();
            var filtered = items.filter(function(i) {
                return !selected.has(i.id) && i.name.toLowerCase().indexOf(lower) !== -1;
            });
            if (filtered.length === 0 || filter === '') {
                dropdownEl.style.display = 'none';
                return;
            }
            dropdownEl.textContent = '';
            filtered.slice(0, 50).forEach(function(item) {
                var div = document.createElement('div');
                div.textContent = item.name;
                div.style.cssText = 'padding:6px 10px; cursor:pointer;';
                div.addEventListener('mouseenter', function() { div.style.background = 'var(--surface-hover)'; });
                div.addEventListener('mouseleave', function() { div.style.background = ''; });
                div.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    selected.set(item.id, item.name);
                    searchEl.value = '';
                    dropdownEl.style.display = 'none';
                    render();
                });
                dropdownEl.appendChild(div);
            });
            dropdownEl.style.display = 'block';
        }

        searchEl.addEventListener('input', function() { showDropdown(searchEl.value.trim()); });
        searchEl.addEventListener('focus', function() { if (searchEl.value.trim()) showDropdown(searchEl.value.trim()); });
        searchEl.addEventListener('blur', function() { setTimeout(function() { dropdownEl.style.display = 'none'; }, 150); });

        render();
    }

    initListPicker({
        items: @json($catalogCategories->map(fn($c) => ['id' => (int)$c->category_id, 'name' => $c->name])),
        selectedIds: @json($selectedCatIds),
        searchEl: document.getElementById('catSearch'),
        dropdownEl: document.getElementById('catDropdown'),
        listEl: document.getElementById('catSelected'),
        fieldName: 'catalog_category_ids[]'
    });

    initListPicker({
        items: @json($manufacturers->map(fn($m) => ['id' => (int)$m->manufacturer_id, 'name' => $m->name])),
        selectedIds: @json($selectedMfgIds),
        searchEl: document.getElementById('mfgSearch'),
        dropdownEl: document.getElementById('mfgDropdown'),
        listEl: document.getElementById('mfgSelected'),
        fieldName: 'manufacturer_ids[]'
    });
</script>
@endpush
@endsection
