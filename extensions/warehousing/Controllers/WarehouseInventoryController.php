<?php

namespace Extensions\warehousing\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Extensions\warehousing\Models\Warehouse;
use Extensions\warehousing\Models\WarehouseInventory;
use Extensions\warehousing\Services\WarehouseStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseInventoryController extends Controller
{
    public function index(Request $request)
    {
        $pfx = config('catalog.prefix');
        $langId = (int) config('catalog.default_language_id');

        $search = trim((string) $request->get('search', ''));
        $warehouseId = (int) $request->get('warehouse_id', 0);
        $categoryId = (int) $request->get('category_id', 0);

        // Get all warehouses for column headers
        $warehouses = Warehouse::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'code']);

        // Build query: distinct product+pov combos from warehouse_inventory
        $query = DB::table('warehouse_inventory as wi')
            ->join($pfx . 'product as p', 'wi.product_id', '=', 'p.product_id')
            ->join($pfx . 'product_description as pd', function ($j) use ($langId) {
                $j->on('p.product_id', '=', 'pd.product_id')
                  ->where('pd.language_id', '=', $langId);
            })
            ->leftJoin($pfx . 'product_option_value as pov', function ($j) {
                $j->on('wi.product_option_value_id', '=', 'pov.product_option_value_id')
                  ->where('wi.product_option_value_id', '>', 0);
            })
            ->leftJoin($pfx . 'option_value_description as ovd', function ($j) use ($langId) {
                $j->on('pov.option_value_id', '=', 'ovd.option_value_id')
                  ->where('ovd.language_id', '=', $langId);
            })
            ->select([
                'wi.product_id',
                'wi.product_option_value_id',
                'pd.name as product_name',
                'p.sku as product_sku',
                'p.model as product_model',
                'p.status as product_status',
                'pov.sku as option_sku',
                'ovd.name as option_value_name',
            ])
            ->groupBy(
                'wi.product_id',
                'wi.product_option_value_id',
                'pd.name',
                'p.sku',
                'p.model',
                'p.status',
                'pov.sku',
                'ovd.name'
            );

        // Filter: search
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('pd.name', 'like', '%' . $search . '%')
                  ->orWhere('p.sku', 'like', '%' . $search . '%')
                  ->orWhere('p.model', 'like', '%' . $search . '%')
                  ->orWhere('pov.sku', 'like', '%' . $search . '%');
            });
        }

        // Filter: warehouse — only show products that have stock in this warehouse
        if ($warehouseId > 0) {
            $query->whereExists(function ($sub) use ($warehouseId) {
                $sub->select(DB::raw(1))
                    ->from('warehouse_inventory as wi2')
                    ->whereColumn('wi2.product_id', 'wi.product_id')
                    ->whereColumn('wi2.product_option_value_id', 'wi.product_option_value_id')
                    ->where('wi2.warehouse_id', $warehouseId);
            });
        }

        // Filter: category
        if ($categoryId > 0) {
            $query->whereExists(function ($sub) use ($pfx, $categoryId) {
                $sub->select(DB::raw(1))
                    ->from($pfx . 'product_to_category as ptc')
                    ->whereColumn('ptc.product_id', 'wi.product_id')
                    ->where('ptc.category_id', $categoryId);
            });
        }

        // Sort by product name, then option value name
        $query->orderBy('pd.name')->orderBy('ovd.name');

        // Paginate
        $rows = $query->paginate(50)->withQueryString();

        // Build per-row quantities: fetch all inventory for the paginated product+pov combos
        $rowKeys = $rows->getCollection()->map(function ($r) {
            return ['product_id' => $r->product_id, 'product_option_value_id' => $r->product_option_value_id];
        })->toArray();

        $inventoryMap = [];
        if (!empty($rowKeys)) {
            // Build OR conditions for each product+pov pair
            $invQuery = DB::table('warehouse_inventory')
                ->where(function ($q) use ($rowKeys) {
                    foreach ($rowKeys as $k) {
                        $q->orWhere(function ($sub) use ($k) {
                            $sub->where('product_id', $k['product_id'])
                                ->where('product_option_value_id', $k['product_option_value_id']);
                        });
                    }
                })
                ->get(['warehouse_id', 'product_id', 'product_option_value_id', 'quantity']);

            foreach ($invQuery as $inv) {
                $key = $inv->product_id . ':' . $inv->product_option_value_id;
                $inventoryMap[$key][$inv->warehouse_id] = (int) $inv->quantity;
            }
        }

        // Get categories for the filter dropdown
        $categories = DB::table($pfx . 'category_description')
            ->where('language_id', $langId)
            ->orderBy('name')
            ->pluck('name', 'category_id');

        return view('ext-warehousing::inventory.index', compact(
            'warehouses',
            'rows',
            'inventoryMap',
            'categories',
            'search',
            'warehouseId',
            'categoryId'
        ));
    }

    public function adjustForm()
    {
        $warehouses = Warehouse::orderBy('sort_order')->orderBy('name')->get();

        return view('ext-warehousing::inventory.adjust', compact('warehouses'));
    }

    public function adjustStore(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.product_option_value_id' => 'nullable|integer',
            'items.*.quantity' => 'required|integer',
            'note' => 'nullable|string|max:500',
        ]);

        $warehouseId = (int) $request->warehouse_id;
        $note = $request->note ?: 'Manual stock adjustment';
        $count = 0;

        DB::transaction(function () use ($request, $warehouseId, $note, &$count) {
            foreach ($request->input('items') as $item) {
                $productId = (int) $item['product_id'];
                $povId = (int) ($item['product_option_value_id'] ?? 0);
                $newQty = (int) $item['quantity'];

                $inv = WarehouseStockService::getOrCreateInventory($warehouseId, $productId, $povId);
                $oldQty = $inv->quantity;

                if ($oldQty !== $newQty) {
                    $delta = $newQty - $oldQty;
                    WarehouseStockService::adjustStock(
                        $warehouseId,
                        $productId,
                        $povId,
                        $delta,
                        'manual_adjustment',
                        $note
                    );
                    $count++;
                }
            }
        });

        ActivityLogger::log('adjusted', 'WarehouseInventory', null, "Adjusted {$count} item(s) in warehouse #{$warehouseId}");

        return redirect()->route('ext.warehousing.inventory.adjust')
            ->with('status', "Stock adjusted for {$count} item(s).");
    }
}
