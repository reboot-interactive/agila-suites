<?php

namespace Extensions\warehousing\Services;

use App\Services\StockHistoryLogger;
use Extensions\warehousing\Models\Warehouse;
use Extensions\warehousing\Models\WarehouseInventory;
use Extensions\warehousing\Models\WarehouseTransfer;
use Illuminate\Support\Facades\DB;

class WarehouseStockService
{
    /**
     * Get the default warehouse.
     */
    public static function getDefaultWarehouse(): ?Warehouse
    {
        return Warehouse::default()->first();
    }

    /**
     * Get or create an inventory row for the given warehouse/product/option-value.
     */
    public static function getOrCreateInventory(int $warehouseId, int $productId, int $povId = 0): WarehouseInventory
    {
        return WarehouseInventory::firstOrCreate(
            [
                'warehouse_id'            => $warehouseId,
                'product_id'              => $productId,
                'product_option_value_id' => $povId,
            ],
            [
                'quantity' => 0,
            ]
        );
    }

    /**
     * Adjust stock for a specific warehouse/product/option-value and sync totals.
     */
    public static function adjustStock(
        int $warehouseId,
        int $productId,
        int $povId,
        int $delta,
        string $source,
        ?string $note = null,
        ?int $orderId = null,
    ): void {
        $inv = self::getOrCreateInventory($warehouseId, $productId, $povId);

        $before = (int) $inv->quantity;

        if ($delta >= 0) {
            $inv->increment('quantity', $delta);
        } else {
            $inv->decrement('quantity', abs($delta));
        }

        $after = (int) $inv->fresh()->quantity;

        StockHistoryLogger::log(
            productId: $productId,
            optionValueId: $povId > 0 ? $povId : null,
            orderId: $orderId,
            type: $delta >= 0 ? 'set' : 'deduct',
            qtyBefore: $before,
            qtyAfter: $after,
            source: $source,
            note: $note,
            warehouseId: $warehouseId,
        );

        self::syncProductTotal($productId);
    }

    /**
     * Sync catalog product/option-value quantities from warehouse inventory totals.
     */
    public static function syncProductTotal(int $productId): void
    {
        $pfx = config('catalog.prefix');

        // Only sellable warehouses contribute to catalog quantity (marketplace push)
        $sellableIds = Warehouse::sellable()->pluck('id')->toArray();

        $hasOptions = DB::table($pfx . 'product_option_value')
            ->where('product_id', $productId)
            ->exists();

        if ($hasOptions) {
            $povIds = DB::table('warehouse_inventory')
                ->where('product_id', $productId)
                ->where('product_option_value_id', '>', 0)
                ->distinct()
                ->pluck('product_option_value_id');

            $parentTotal = 0;

            foreach ($povIds as $povId) {
                $sellableQty = empty($sellableIds) ? 0 : (int) DB::table('warehouse_inventory')
                    ->where('product_id', $productId)
                    ->where('product_option_value_id', $povId)
                    ->whereIn('warehouse_id', $sellableIds)
                    ->sum('quantity');

                DB::table($pfx . 'product_option_value')
                    ->where('product_option_value_id', $povId)
                    ->update(['quantity' => $sellableQty]);

                DB::table('product_option_combination_values as cv')
                    ->join('product_option_combinations as c', 'c.id', '=', 'cv.combination_id')
                    ->where('cv.product_option_value_id', $povId)
                    ->where('c.product_id', $productId)
                    ->update(['c.quantity' => $sellableQty]);

                $parentTotal += $sellableQty;
            }

            DB::table($pfx . 'product')
                ->where('product_id', $productId)
                ->update(['quantity' => $parentTotal]);
        } else {
            $sellableQty = empty($sellableIds) ? 0 : (int) DB::table('warehouse_inventory')
                ->where('product_id', $productId)
                ->where('product_option_value_id', 0)
                ->whereIn('warehouse_id', $sellableIds)
                ->sum('quantity');

            DB::table($pfx . 'product')
                ->where('product_id', $productId)
                ->update(['quantity' => $sellableQty]);
        }
    }

    /**
     * Execute a warehouse transfer: decrement source, increment destination.
     */
    public static function executeTransfer(WarehouseTransfer $transfer): void
    {
        $transfer->load('items');

        $affectedProducts = [];

        foreach ($transfer->items as $item) {
            // Decrement source warehouse
            self::adjustStock(
                warehouseId: $transfer->from_warehouse_id,
                productId: $item->product_id,
                povId: (int) $item->product_option_value_id,
                delta: -$item->quantity,
                source: 'transfer',
                note: "Transfer {$transfer->reference} out",
            );

            // Increment destination warehouse
            self::adjustStock(
                warehouseId: $transfer->to_warehouse_id,
                productId: $item->product_id,
                povId: (int) $item->product_option_value_id,
                delta: $item->quantity,
                source: 'transfer',
                note: "Transfer {$transfer->reference} in",
            );

            $affectedProducts[$item->product_id] = true;
        }

        // Final sync for all affected products (idempotent, ensures consistency)
        foreach ($affectedProducts as $productId => $_) {
            self::syncProductTotal($productId);
        }
    }

    /**
     * Backfill warehouse inventory from existing catalog quantities.
     * Assigns all current stock to the default warehouse.
     * Safe to run multiple times (uses updateOrCreate).
     */
    public static function backfillFromExisting(): void
    {
        $warehouse = self::getDefaultWarehouse();

        if (!$warehouse) {
            throw new \RuntimeException('No default warehouse found. Create a default warehouse first.');
        }

        $pfx = config('catalog.prefix');

        // Products WITHOUT options: not in product_option_value table
        $productsWithoutOptions = DB::table($pfx . 'product')
            ->whereNotIn('product_id', function ($query) use ($pfx) {
                $query->select('product_id')
                    ->distinct()
                    ->from($pfx . 'product_option_value');
            })
            ->select('product_id', 'quantity')
            ->get();

        foreach ($productsWithoutOptions as $product) {
            WarehouseInventory::updateOrCreate(
                [
                    'warehouse_id'            => $warehouse->id,
                    'product_id'              => $product->product_id,
                    'product_option_value_id' => 0,
                ],
                [
                    'quantity' => (int) $product->quantity,
                ]
            );
        }

        // Products WITH options: each option value gets its own inventory row
        $optionValues = DB::table($pfx . 'product_option_value')
            ->select('product_id', 'product_option_value_id', 'quantity')
            ->get();

        foreach ($optionValues as $pov) {
            WarehouseInventory::updateOrCreate(
                [
                    'warehouse_id'            => $warehouse->id,
                    'product_id'              => $pov->product_id,
                    'product_option_value_id' => $pov->product_option_value_id,
                ],
                [
                    'quantity' => (int) $pov->quantity,
                ]
            );
        }
    }
}
