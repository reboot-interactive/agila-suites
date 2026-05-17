@extends('layouts.app')
@section('breadcrumb', 'Warehousing / Stock Adjustment')

@section('content')
<div class="card">
    <div class="page-header">
        <h2>Stock Adjustment</h2>
        <div class="page-header-actions">
            <a class="btn secondary" href="{{ route('ext.warehousing.inventory.index') }}">Back to Inventory</a>
            <button class="btn" type="submit" form="adjust-form">Save Adjustments</button>
        </div>
    </div>

    <form id="adjust-form" method="POST" action="{{ route('ext.warehousing.inventory.adjust.store') }}">
        @csrf

        <div class="form-grid">
            <div>
                <label class="required">Warehouse</label>
                <select class="input" name="warehouse_id" id="adjust-warehouse">
                    <option value="">-- Select Warehouse --</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" {{ old('warehouse_id') == $w->id ? 'selected' : '' }}>
                            {{ $w->name }} ({{ $w->code }})
                            @if(!$w->is_sellable) — Not Sellable @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label>Reason / Note</label>
                <input class="input" name="note" value="{{ old('note') }}" maxlength="500" placeholder="e.g. Initial stock count, Physical audit correction">
            </div>
        </div>

        <h3 class="section-title mt-24">Products</h3>

        <div class="mb-8" style="display:flex; gap:8px; align-items:end;">
            <div style="position:relative; flex:1; max-width:400px;">
                <label>Search Product</label>
                <input class="input" id="product-search" placeholder="Type product name or SKU..." autocomplete="off">
                <div id="product-search-dd" class="ac-dd hidden" style="position:absolute; top:100%; left:0; right:0; z-index:50;">
                    <ul id="product-search-list"></ul>
                </div>
            </div>
        </div>

        <div class="table-wrap">
        <table id="adjust-table">
            <thead>
            <tr>
                <th>Product</th>
                <th style="width:100px;">SKU</th>
                <th style="width:100px;">Current Qty</th>
                <th style="width:120px;">New Qty</th>
                <th style="width:44px;"></th>
            </tr>
            </thead>
            <tbody id="adjust-body">
            </tbody>
        </table>
        </div>

        <div id="no-items-msg" class="text-secondary text-sm mt-8">
            Select a warehouse and search for products to adjust stock.
        </div>
    </form>
</div>

<script>
(function() {
    var tbody = document.getElementById('adjust-body');
    var rowIdx = 0;
    var searchInput = document.getElementById('product-search');
    var searchDD = document.getElementById('product-search-dd');
    var searchList = document.getElementById('product-search-list');
    var searchTimer = null;
    var warehouseSelect = document.getElementById('adjust-warehouse');
    var noItemsMsg = document.getElementById('no-items-msg');

    function updateNoItemsMsg() {
        noItemsMsg.style.display = tbody.querySelectorAll('tr').length === 0 ? '' : 'none';
    }
    updateNoItemsMsg();

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimer);
        var term = this.value.trim();
        if (term.length < 2) { searchDD.classList.add('hidden'); return; }
        var whId = warehouseSelect.value || 0;
        searchTimer = setTimeout(function() {
            fetch('{{ route("ext.warehousing.api.products") }}?term=' + encodeURIComponent(term) + '&warehouse_id=' + whId)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    searchList.textContent = '';
                    if (data.length === 0) {
                        var emptyLi = document.createElement('li');
                        emptyLi.textContent = 'No results';
                        emptyLi.style.cssText = 'padding:8px 12px; color:#999;';
                        searchList.appendChild(emptyLi);
                        searchDD.classList.remove('hidden');
                        return;
                    }

                    data.forEach(function(p) {
                        var li = document.createElement('li');
                        var label = p.name + (p.sku ? ' [' + p.sku + ']' : '');
                        if (p.available_qty !== null) {
                            label += ' (Current: ' + p.available_qty + ')';
                        }
                        li.textContent = label;
                        li.addEventListener('click', function() {
                            addItemRow(p);
                            searchDD.classList.add('hidden');
                            searchInput.value = '';
                        });
                        searchList.appendChild(li);
                    });
                    searchDD.classList.remove('hidden');
                });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!searchDD.contains(e.target) && e.target !== searchInput) searchDD.classList.add('hidden');
    });

    function addItemRow(product) {
        // Prevent duplicates
        var existingRows = tbody.querySelectorAll('tr');
        for (var k = 0; k < existingRows.length; k++) {
            var row = existingRows[k];
            var existPid = row.querySelector('input[name$="[product_id]"]');
            var existPov = row.querySelector('input[name$="[product_option_value_id]"]');
            if (existPid && existPov
                && parseInt(existPid.value) === product.product_id
                && parseInt(existPov.value) === (product.product_option_value_id || 0)) {
                var qtyInput = row.querySelector('input[name$="[quantity]"]');
                if (qtyInput) qtyInput.focus();
                return;
            }
        }

        var i = rowIdx++;
        var currentQty = product.available_qty !== null ? product.available_qty : 0;
        var tr = document.createElement('tr');

        // Product name + hidden fields
        var tdName = document.createElement('td');
        var hidPid = document.createElement('input');
        hidPid.type = 'hidden'; hidPid.name = 'items[' + i + '][product_id]'; hidPid.value = product.product_id;
        tdName.appendChild(hidPid);
        var hidPov = document.createElement('input');
        hidPov.type = 'hidden'; hidPov.name = 'items[' + i + '][product_option_value_id]'; hidPov.value = product.product_option_value_id || 0;
        tdName.appendChild(hidPov);
        var nameSpan = document.createElement('span');
        nameSpan.textContent = product.name;
        tdName.appendChild(nameSpan);
        tr.appendChild(tdName);

        // SKU
        var tdSku = document.createElement('td');
        tdSku.textContent = product.sku || '';
        tr.appendChild(tdSku);

        // Current qty (read-only)
        var tdCurrent = document.createElement('td');
        tdCurrent.className = 'font-bold';
        tdCurrent.textContent = currentQty;
        tr.appendChild(tdCurrent);

        // New qty (editable)
        var tdQty = document.createElement('td');
        var qtyInput = document.createElement('input');
        qtyInput.className = 'input';
        qtyInput.name = 'items[' + i + '][quantity]';
        qtyInput.type = 'number';
        qtyInput.value = currentQty;
        qtyInput.required = true;
        tdQty.appendChild(qtyInput);
        tr.appendChild(tdQty);

        // Remove button
        var tdDel = document.createElement('td');
        var delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'btn danger small remove-item-btn';
        delBtn.textContent = '\u00D7';
        tdDel.appendChild(delBtn);
        tr.appendChild(tdDel);

        tbody.appendChild(tr);
        updateNoItemsMsg();
        qtyInput.focus();
        qtyInput.select();
    }

    // Remove item row
    tbody.addEventListener('click', function(e) {
        var btn = e.target.closest('.remove-item-btn');
        if (btn) {
            btn.closest('tr').remove();
            reindexItems();
            updateNoItemsMsg();
        }
    });

    function reindexItems() {
        var rows = tbody.querySelectorAll('tr');
        rows.forEach(function(tr, i) {
            tr.querySelectorAll('input').forEach(function(el) {
                if (el.name) {
                    el.name = el.name.replace(/items\[\d+\]/, 'items[' + i + ']');
                }
            });
        });
        rowIdx = rows.length;
    }

    // Form validation
    document.getElementById('adjust-form').addEventListener('submit', function(e) {
        if (!warehouseSelect.value) {
            e.preventDefault();
            showFlashError('Please select a warehouse.');
            return;
        }
        if (tbody.querySelectorAll('tr').length === 0) {
            e.preventDefault();
            showFlashError('Please add at least one product to adjust.');
        }
    });
})();
</script>
@endsection
