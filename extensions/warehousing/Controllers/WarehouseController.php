<?php

namespace Extensions\warehousing\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Extensions\warehousing\Models\Warehouse;
use Extensions\warehousing\Models\WarehouseInventory;
use Extensions\warehousing\Services\WarehouseStockService;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::orderBy('sort_order')->orderBy('name')->get();

        return view('ext-warehousing::locations.index', compact('warehouses'));
    }

    public function create()
    {
        return view('ext-warehousing::locations.form');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:128',
            'code' => 'required|string|max:32|alpha_dash|unique:warehouses,code',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $data = $request->only(['name', 'code', 'sort_order']);
        $data['code'] = strtoupper($data['code']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_default'] = $request->boolean('is_default');
        $data['is_sellable'] = $request->boolean('is_sellable');

        // If this is the first warehouse, force it as default and sellable
        if (Warehouse::count() === 0) {
            $data['is_default'] = true;
            $data['is_sellable'] = true;
        }

        // If setting as default, unset any existing default
        if ($data['is_default']) {
            Warehouse::where('is_default', true)->update(['is_default' => false]);
        }

        $warehouse = Warehouse::create($data);

        ActivityLogger::log('created', 'Warehouse', $warehouse->id, $warehouse->name);

        return redirect()->route('ext.warehousing.locations.index')->with('status', 'Location created.');
    }

    public function edit($id)
    {
        $warehouse = Warehouse::findOrFail($id);

        return view('ext-warehousing::locations.form', compact('warehouse'));
    }

    public function update(Request $request, $id)
    {
        $warehouse = Warehouse::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:128',
            'code' => 'required|string|max:32|alpha_dash|unique:warehouses,code,' . $warehouse->id,
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $original = $warehouse->getAttributes();

        $data = $request->only(['name', 'code', 'sort_order']);
        $data['code'] = strtoupper($data['code']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_default'] = $request->boolean('is_default');
        $data['is_sellable'] = $request->boolean('is_sellable');

        // Prevent unsetting default if no other warehouse is default
        if (!$data['is_default'] && $warehouse->is_default) {
            $otherDefault = Warehouse::where('is_default', true)
                ->where('id', '!=', $warehouse->id)
                ->exists();

            if (!$otherDefault) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['is_default' => 'Cannot unset default — at least one location must be the default.']);
            }
        }

        // If setting as default, unset any existing default
        if ($data['is_default'] && !$warehouse->is_default) {
            Warehouse::where('is_default', true)->update(['is_default' => false]);
        }

        $sellableChanged = (bool) $original['is_sellable'] !== $data['is_sellable'];

        $warehouse->update($data);

        // Re-sync all product totals when sellable flag changes
        if ($sellableChanged) {
            $productIds = WarehouseInventory::where('warehouse_id', $warehouse->id)
                ->distinct()
                ->pluck('product_id');

            foreach ($productIds as $productId) {
                WarehouseStockService::syncProductTotal($productId);
            }
        }

        $changes = ActivityLogger::diff($original, $warehouse->getAttributes(), [
            'name', 'code', 'is_default', 'is_sellable', 'sort_order',
        ]);
        ActivityLogger::log('updated', 'Warehouse', $id, $warehouse->name, $changes);

        return redirect()->route('ext.warehousing.locations.index')->with('status', 'Location updated.' . ($sellableChanged ? ' Product quantities re-synced.' : ''));
    }

    public function destroy($id)
    {
        $warehouse = Warehouse::findOrFail($id);

        // Cannot delete the default warehouse
        if ($warehouse->is_default) {
            return redirect()->route('ext.warehousing.locations.index')
                ->with('error', 'Cannot delete the default location. Set another location as default first.');
        }

        // Cannot delete if warehouse has non-zero inventory
        $hasStock = $warehouse->inventory()->where('quantity', '!=', 0)->exists();
        if ($hasStock) {
            return redirect()->route('ext.warehousing.locations.index')
                ->with('error', 'Cannot delete this location — it has non-zero inventory. Transfer stock out first.');
        }

        $name = $warehouse->name;

        // Clean up zero-quantity inventory rows
        $warehouse->inventory()->delete();
        $warehouse->delete();

        ActivityLogger::log('deleted', 'Warehouse', $id, $name);

        return redirect()->route('ext.warehousing.locations.index')->with('status', 'Location deleted.');
    }
}
