<?php

namespace Extensions\warehousing;

use App\Extensions\ExtensionProvider;
use App\Integrations\Contracts\ProductInventoryDetailContributor;
use Extensions\warehousing\Models\Warehouse;
use Extensions\warehousing\Models\WarehouseInventory;
use Illuminate\Support\Facades\Schema;

class WarehousingExtension extends ExtensionProvider implements ProductInventoryDetailContributor
{
    protected string $id = 'warehousing';

    /**
     * Per-warehouse quantity breakdown rendered below the product edit
     * form's qty field. Returns null if the product isn't tracked in any
     * warehouse, otherwise a string like "Warehouse A: 5 | Warehouse B: 3".
     */
    public function inventoryBreakdownFor(int $productId): ?string
    {
        if (!Schema::hasTable('warehouses') || !Schema::hasTable('warehouse_inventory')) {
            return null;
        }

        $warehouses = Warehouse::orderBy('sort_order')->get();
        if ($warehouses->isEmpty()) {
            return null;
        }

        $inventory = WarehouseInventory::where('product_id', $productId)
            ->where('product_option_value_id', 0)
            ->get()
            ->keyBy('warehouse_id');

        $parts = [];
        foreach ($warehouses as $wh) {
            $qty = $inventory->has($wh->id) ? (int) $inventory[$wh->id]->quantity : 0;
            $parts[] = $wh->name . ': ' . $qty;
        }

        return empty($parts) ? null : implode(' | ', $parts);
    }
}
