@extends('layouts.app')
@php
    $isEdit = isset($transfer);
    $pageTitle = $isEdit ? 'Edit Transfer ' . $transfer->reference : 'Create Transfer';
    $formAction = $isEdit
        ? route('ext.warehousing.transfers.update', $transfer->id)
        : route('ext.warehousing.transfers.store');
@endphp
@section('breadcrumb', 'Warehousing / Transfers / ' . ($isEdit ? 'Edit' : 'Create'))

@section('content')
<div class="card">
    <div class="page-header">
        <div class="d-flex gap-10 items-center flex-wrap">
            <h2>{{ $pageTitle }}</h2>
            <span class="badge badge-gray" style="font-size:1.2em; padding:6px 18px;">Draft</span>
        </div>
        <div class="page-header-actions">
            <a class="btn secondary" href="{{ $isEdit ? route('ext.warehousing.transfers.show', $transfer->id) : route('ext.warehousing.transfers.index') }}">Back</a>
            <button class="btn" type="submit" form="transfer-form">{{ $isEdit ? 'Update Transfer' : 'Create Transfer' }}</button>
        </div>
    </div>

    <form id="transfer-form" method="POST" action="{{ $formAction }}">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="form-grid">
            <div>
                <label class="required">From Location</label>
                <select class="input" name="from_warehouse_id" id="from-warehouse">
                    <option value="">-- Select Source --</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" {{ old('from_warehouse_id', $isEdit ? $transfer->from_warehouse_id : '') == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="required">To Location</label>
                <select class="input" name="to_warehouse_id" id="to-warehouse">
                    <option value="">-- Select Destination --</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" {{ old('to_warehouse_id', $isEdit ? $transfer->to_warehouse_id : '') == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="full">
                <label>Note</label>
                <textarea class="input" name="note" rows="2" maxlength="500">{{ old('note', $isEdit ? $transfer->note : '') }}</textarea>
            </div>
        </div>

        <h3 class="section-title mt-24">Line Items</h3>

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
        <table id="transfer-items-table">
            <thead>
            <tr>
                <th>Product</th>
                <th style="width:100px;">SKU</th>
                <th style="width:100px;">Available</th>
                <th style="width:100px;">Transfer Qty</th>
                <th style="width:44px;"></th>
            </tr>
            </thead>
            <tbody id="transfer-items-body">
            </tbody>
        </table>
        </div>

        <div id="no-items-msg" class="text-secondary text-sm mt-8">
            Search and add products above to create transfer line items.
        </div>
    </form>
</div>

<script>
(function() {
    var tbody = document.getElementById('transfer-items-body');
    var rowIdx = 0;
    var searchInput = document.getElementById('product-search');
    var searchDD = document.getElementById('product-search-dd');
    var searchList = document.getElementById('product-search-list');
    var searchTimer = null;
    var fromWarehouse = document.getElementById('from-warehouse');
    var noItemsMsg = document.getElementById('no-items-msg');

    function updateNoItemsMsg() {
        noItemsMsg.style.display = tbody.querySelectorAll('tr').length === 0 ? '' : 'none';
    }
    updateNoItemsMsg();

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimer);
        var term = this.value.trim();
        if (term.length < 2) { searchDD.classList.add('hidden'); return; }
        searchTimer = setTimeout(function() {
            var whId = fromWarehouse.value || 0;
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

                    var seenGroups = {};
                    data.forEach(function(p) {
                        // "Add all options" row for grouped option products
                        if (p.has_options && p.option_group && !seenGroups[p.option_group]) {
                            seenGroups[p.option_group] = data.filter(function(v) { return v.option_group === p.option_group; });
                            var allLi = document.createElement('li');
                            allLi.style.cssText = 'font-size:12px; font-weight:600; color:var(--accent, #2563eb); border-bottom:1px solid var(--border-color, #e2e8f0);';
                            var baseName = p.name.split(' \u2014 ')[0];
                            allLi.textContent = '+ Add all ' + p.option_count + ' options of ' + baseName;
                            var groupItems = seenGroups[p.option_group];
                            allLi.addEventListener('click', function() {
                                groupItems.forEach(function(v) { addItemRow(v); });
                                searchDD.classList.add('hidden');
                                searchInput.value = '';
                            });
                            searchList.appendChild(allLi);
                        }

                        var li = document.createElement('li');
                        if (p.has_options) {
                            li.style.paddingLeft = '24px';
                            li.style.fontSize = '13px';
                        }
                        var label = p.name + (p.sku ? ' [' + p.sku + ']' : '');
                        if (p.available_qty !== null) {
                            label += ' (Avail: ' + p.available_qty + ')';
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
        // Prevent duplicate: same product_id + product_option_value_id
        var existingRows = tbody.querySelectorAll('tr');
        for (var k = 0; k < existingRows.length; k++) {
            var row = existingRows[k];
            var existPid = row.querySelector('input[name$="[product_id]"]');
            var existPov = row.querySelector('input[name$="[product_option_value_id]"]');
            if (existPid && existPov
                && parseInt(existPid.value) === product.product_id
                && parseInt(existPov.value) === (product.product_option_value_id || 0)) {
                // Already in the list — focus its qty input
                var qtyInput = row.querySelector('input[name$="[quantity]"]');
                if (qtyInput) qtyInput.focus();
                return;
            }
        }

        var i = rowIdx++;
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

        // Available qty
        var tdAvail = document.createElement('td');
        tdAvail.className = 'item-available';
        tdAvail.textContent = product.available_qty !== null ? product.available_qty : '—';
        tr.appendChild(tdAvail);

        // Transfer qty
        var tdQty = document.createElement('td');
        var qtyInput = document.createElement('input');
        qtyInput.className = 'input';
        qtyInput.name = 'items[' + i + '][quantity]';
        qtyInput.type = 'number';
        qtyInput.min = '1';
        qtyInput.value = '1';
        qtyInput.required = true;
        qtyInput.dataset.available = product.available_qty !== null ? product.available_qty : '';
        qtyInput.addEventListener('input', function() { validateQty(this); });
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

    // Refresh available qty when source warehouse changes
    fromWarehouse.addEventListener('change', function() {
        var whId = this.value;
        var rows = tbody.querySelectorAll('tr');
        if (rows.length === 0 || !whId) return;

        rows.forEach(function(tr) {
            var pid = tr.querySelector('input[name$="[product_id]"]').value;
            var pov = tr.querySelector('input[name$="[product_option_value_id]"]').value;
            var availCell = tr.querySelector('.item-available');

            fetch('{{ route("ext.warehousing.api.products") }}?term=__id:' + pid + '&warehouse_id=' + whId)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var found = data.find(function(d) {
                        return d.product_id === parseInt(pid)
                            && d.product_option_value_id === parseInt(pov);
                    });
                    var qty = found && found.available_qty !== null ? found.available_qty : null;
                    availCell.textContent = qty !== null ? qty : '—';
                    // Update the qty input's data-available for validation
                    var qtyInput = tr.querySelector('input[name$="[quantity]"]');
                    if (qtyInput) {
                        qtyInput.dataset.available = qty !== null ? qty : '';
                        validateQty(qtyInput);
                    }
                })
                .catch(function() { availCell.textContent = '—'; });
        });
    });

    // Validate qty against available stock — real-time on input
    function validateQty(input) {
        var avail = parseInt(input.dataset.available);
        var qty = parseInt(input.value) || 0;
        if (!isNaN(avail) && qty > avail) {
            input.classList.add('input-error');
            input.title = 'Exceeds available stock (' + avail + ')';
        } else {
            input.classList.remove('input-error');
            input.title = '';
        }
    }

    // Form validation — ensure at least one item and no qty exceeds available
    document.getElementById('transfer-form').addEventListener('submit', function(e) {
        if (tbody.querySelectorAll('tr').length === 0) {
            e.preventDefault();
            showFlashError('Please add at least one product to transfer.');
            return;
        }

        // Validate from != to
        var fromVal = fromWarehouse.value;
        var toVal = document.getElementById('to-warehouse').value;
        if (fromVal && toVal && fromVal === toVal) {
            e.preventDefault();
            showFlashError('Source and destination locations must be different.');
            return;
        }

        // Validate no qty exceeds available
        var hasOverage = false;
        tbody.querySelectorAll('input[name$="[quantity]"]').forEach(function(input) {
            var avail = parseInt(input.dataset.available);
            var qty = parseInt(input.value) || 0;
            if (!isNaN(avail) && qty > avail) {
                hasOverage = true;
                input.classList.add('input-error');
            }
        });
        if (hasOverage) {
            e.preventDefault();
            showFlashError('Transfer quantity exceeds available stock for one or more items.');
        }
    });

    // Pre-populate existing items in edit mode
    @if(isset($existingItems) && !empty($existingItems))
        var editItems = @json($existingItems);
        editItems.forEach(function(item) {
            addItemRow({
                product_id: item.product_id,
                product_option_value_id: item.product_option_value_id,
                name: item.name,
                sku: item.sku,
                available_qty: item.available_qty,
                has_options: item.product_option_value_id > 0,
            });
            // Set the qty to the saved value
            var lastRow = tbody.querySelector('tr:last-child');
            if (lastRow) {
                var qtyInput = lastRow.querySelector('input[name$="[quantity]"]');
                if (qtyInput) {
                    qtyInput.value = item.quantity;
                    qtyInput.dataset.available = item.available_qty;
                    validateQty(qtyInput);
                }
            }
        });
    @endif

    // Lock from/to dropdowns — disable selected warehouse in the other
    var toWarehouse = document.getElementById('to-warehouse');

    function lockDropdowns() {
        var fromVal = fromWarehouse.value;
        var toVal = toWarehouse.value;

        // Enable all options first
        Array.from(fromWarehouse.options).forEach(function(opt) { opt.disabled = false; });
        Array.from(toWarehouse.options).forEach(function(opt) { opt.disabled = false; });

        // Disable selected from in to, and vice versa
        if (fromVal) {
            var toOpt = toWarehouse.querySelector('option[value="' + fromVal + '"]');
            if (toOpt) toOpt.disabled = true;
        }
        if (toVal) {
            var fromOpt = fromWarehouse.querySelector('option[value="' + toVal + '"]');
            if (fromOpt) fromOpt.disabled = true;
        }
    }

    fromWarehouse.addEventListener('change', lockDropdowns);
    toWarehouse.addEventListener('change', lockDropdowns);
    lockDropdowns(); // init on load
})();
</script>
@endsection
