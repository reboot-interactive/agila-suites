<?php

namespace Extensions\warehousing\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Extensions\warehousing\Models\Warehouse;
use Extensions\warehousing\Models\WarehouseInventory;
use Extensions\warehousing\Models\WarehouseTransfer;
use Extensions\warehousing\Models\WarehouseTransferItem;
use Extensions\warehousing\Services\WarehouseStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseTransferController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = (string) $request->get('date_from', '');
        $dateTo = (string) $request->get('date_to', '');
        $warehouseId = (int) $request->get('warehouse_id', 0);
        $status = (string) $request->get('status', '');

        $transfers = WarehouseTransfer::with(['fromWarehouse', 'toWarehouse'])
            ->withCount('items')
            ->when($dateFrom !== '', fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($warehouseId > 0, function ($q) use ($warehouseId) {
                $q->where(function ($sub) use ($warehouseId) {
                    $sub->where('from_warehouse_id', $warehouseId)
                        ->orWhere('to_warehouse_id', $warehouseId);
                });
            })
            ->when($status !== '', fn($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $warehouses = Warehouse::orderBy('sort_order')->orderBy('name')->get(['id', 'name']);

        return view('ext-warehousing::transfers.index', compact(
            'transfers', 'warehouses', 'dateFrom', 'dateTo', 'warehouseId', 'status'
        ));
    }

    public function create()
    {
        $warehouses = Warehouse::orderBy('sort_order')->orderBy('name')->get(['id', 'name']);

        return view('ext-warehousing::transfers.create', compact('warehouses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'note' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.product_option_value_id' => 'nullable|integer',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $transfer = DB::transaction(function () use ($request) {
            $user = auth()->user();

            $transfer = WarehouseTransfer::create([
                'reference' => WarehouseTransfer::generateReference(),
                'from_warehouse_id' => $request->from_warehouse_id,
                'to_warehouse_id' => $request->to_warehouse_id,
                'status' => WarehouseTransfer::STATUS_DRAFT,
                'note' => $request->note,
                'user_id' => $user->id,
                'user_name' => $user->name ?? $user->username ?? 'User #' . $user->id,
            ]);

            foreach ($request->input('items') as $item) {
                WarehouseTransferItem::create([
                    'warehouse_transfer_id' => $transfer->id,
                    'product_id' => (int) $item['product_id'],
                    'product_option_value_id' => (int) ($item['product_option_value_id'] ?? 0),
                    'quantity' => (int) $item['quantity'],
                ]);
            }

            return $transfer;
        });

        ActivityLogger::log(
            'created',
            'WarehouseTransfer',
            $transfer->id,
            $transfer->reference,
        );

        return redirect()->route('ext.warehousing.transfers.show', $transfer->id)
            ->with('status', "Transfer {$transfer->reference} saved as draft.");
    }

    public function show($id)
    {
        $transfer = WarehouseTransfer::with(['fromWarehouse', 'toWarehouse', 'items'])
            ->findOrFail($id);

        $pfx = config('catalog.prefix');
        $langId = (int) config('catalog.default_language_id');

        // Enrich items with product name, SKU, option info
        $enrichedItems = [];
        foreach ($transfer->items as $item) {
            $product = DB::table($pfx . 'product as p')
                ->leftJoin($pfx . 'product_description as pd', function ($j) use ($langId) {
                    $j->on('p.product_id', '=', 'pd.product_id')
                      ->where('pd.language_id', '=', $langId);
                })
                ->where('p.product_id', $item->product_id)
                ->first(['pd.name', 'p.sku']);

            $productName = $product ? html_entity_decode($product->name ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') : 'Product #' . $item->product_id;
            $productSku = $product->sku ?? '';
            $optionLabel = '';

            if ($item->product_option_value_id > 0) {
                $ov = DB::table($pfx . 'product_option_value as pov')
                    ->join($pfx . 'product_option as po', 'pov.product_option_id', '=', 'po.product_option_id')
                    ->join($pfx . 'option_description as od', function ($j) use ($langId) {
                        $j->on('po.option_id', '=', 'od.option_id')->where('od.language_id', '=', $langId);
                    })
                    ->join($pfx . 'option_value_description as ovd', function ($j) use ($langId) {
                        $j->on('pov.option_value_id', '=', 'ovd.option_value_id')->where('ovd.language_id', '=', $langId);
                    })
                    ->where('pov.product_option_value_id', $item->product_option_value_id)
                    ->first(['od.name as option_name', 'ovd.name as value_name', 'pov.sku as ov_sku']);

                if ($ov) {
                    $optName = html_entity_decode($ov->option_name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $valName = html_entity_decode($ov->value_name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $optionLabel = "{$optName}: {$valName}";
                    if ($ov->ov_sku) {
                        $productSku = $ov->ov_sku;
                    }
                }
            }

            $enrichedItems[] = (object) [
                'product_name' => $productName,
                'sku' => $productSku,
                'option_label' => $optionLabel,
                'quantity' => $item->quantity,
            ];
        }

        return view('ext-warehousing::transfers.show', compact('transfer', 'enrichedItems'));
    }

    public function edit($id)
    {
        $transfer = WarehouseTransfer::with('items')->findOrFail($id);

        if ($transfer->status !== WarehouseTransfer::STATUS_DRAFT) {
            return redirect()->route('ext.warehousing.transfers.show', $id)
                ->with('error', 'Only draft transfers can be edited.');
        }

        $warehouses = Warehouse::orderBy('sort_order')->orderBy('name')->get(['id', 'name']);

        $pfx = config('catalog.prefix');
        $langId = (int) config('catalog.default_language_id');

        // Enrich items for the form
        $existingItems = [];
        foreach ($transfer->items as $item) {
            $product = DB::table($pfx . 'product as p')
                ->leftJoin($pfx . 'product_description as pd', function ($j) use ($langId) {
                    $j->on('p.product_id', '=', 'pd.product_id')
                      ->where('pd.language_id', '=', $langId);
                })
                ->where('p.product_id', $item->product_id)
                ->first(['pd.name', 'p.sku']);

            $productName = $product ? html_entity_decode($product->name ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') : 'Product #' . $item->product_id;
            $productSku = $product->sku ?? '';
            $optionLabel = '';

            if ($item->product_option_value_id > 0) {
                $ov = DB::table($pfx . 'product_option_value as pov')
                    ->join($pfx . 'product_option as po', 'pov.product_option_id', '=', 'po.product_option_id')
                    ->join($pfx . 'option_description as od', function ($j) use ($langId) {
                        $j->on('po.option_id', '=', 'od.option_id')->where('od.language_id', '=', $langId);
                    })
                    ->join($pfx . 'option_value_description as ovd', function ($j) use ($langId) {
                        $j->on('pov.option_value_id', '=', 'ovd.option_value_id')->where('ovd.language_id', '=', $langId);
                    })
                    ->where('pov.product_option_value_id', $item->product_option_value_id)
                    ->first(['od.name as option_name', 'ovd.name as value_name', 'pov.sku as ov_sku']);

                if ($ov) {
                    $optName = html_entity_decode($ov->option_name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $valName = html_entity_decode($ov->value_name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $productName .= " — {$optName}: {$valName}";
                    $optionLabel = "{$optName}: {$valName}";
                    if ($ov->ov_sku) {
                        $productSku = $ov->ov_sku;
                    }
                }
            }

            // Get available qty at source warehouse
            $availableQty = (int) (WarehouseInventory::where('warehouse_id', $transfer->from_warehouse_id)
                ->where('product_id', $item->product_id)
                ->where('product_option_value_id', $item->product_option_value_id)
                ->value('quantity') ?? 0);

            $existingItems[] = [
                'product_id' => $item->product_id,
                'product_option_value_id' => $item->product_option_value_id,
                'name' => $productName,
                'sku' => $productSku,
                'available_qty' => $availableQty,
                'quantity' => $item->quantity,
            ];
        }

        return view('ext-warehousing::transfers.create', compact('warehouses', 'transfer', 'existingItems'));
    }

    public function update($id, Request $request)
    {
        $transfer = WarehouseTransfer::findOrFail($id);

        if ($transfer->status !== WarehouseTransfer::STATUS_DRAFT) {
            return redirect()->route('ext.warehousing.transfers.show', $id)
                ->with('error', 'Only draft transfers can be updated.');
        }

        $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'note' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.product_option_value_id' => 'nullable|integer',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($transfer, $request) {
            $transfer->update([
                'from_warehouse_id' => $request->from_warehouse_id,
                'to_warehouse_id' => $request->to_warehouse_id,
                'note' => $request->note,
            ]);

            // Delete old items and create new ones
            WarehouseTransferItem::where('warehouse_transfer_id', $transfer->id)->delete();

            foreach ($request->input('items') as $item) {
                WarehouseTransferItem::create([
                    'warehouse_transfer_id' => $transfer->id,
                    'product_id' => (int) $item['product_id'],
                    'product_option_value_id' => (int) ($item['product_option_value_id'] ?? 0),
                    'quantity' => (int) $item['quantity'],
                ]);
            }
        });

        ActivityLogger::log(
            'updated',
            'WarehouseTransfer',
            $transfer->id,
            $transfer->reference,
        );

        return redirect()->route('ext.warehousing.transfers.show', $transfer->id)
            ->with('status', "Transfer {$transfer->reference} updated.");
    }

    public function markInProgress($id)
    {
        $transfer = WarehouseTransfer::findOrFail($id);

        if ($transfer->status !== WarehouseTransfer::STATUS_DRAFT) {
            return redirect()->route('ext.warehousing.transfers.show', $id)
                ->with('error', 'Only draft transfers can be set to In Progress.');
        }

        $transfer->update(['status' => WarehouseTransfer::STATUS_IN_PROGRESS]);

        ActivityLogger::log('updated', 'WarehouseTransfer', $transfer->id, $transfer->reference, ['status' => 'draft → in_progress']);

        return redirect()->route('ext.warehousing.transfers.show', $transfer->id)
            ->with('status', "Transfer {$transfer->reference} is now In Progress.");
    }

    public function complete($id)
    {
        $transfer = WarehouseTransfer::with('items')->findOrFail($id);

        if ($transfer->status !== WarehouseTransfer::STATUS_IN_PROGRESS) {
            return redirect()->route('ext.warehousing.transfers.show', $id)
                ->with('error', 'Only In Progress transfers can be completed.');
        }

        // Validate stock availability
        foreach ($transfer->items as $item) {
            $available = (int) (WarehouseInventory::where('warehouse_id', $transfer->from_warehouse_id)
                ->where('product_id', $item->product_id)
                ->where('product_option_value_id', $item->product_option_value_id ?? 0)
                ->value('quantity') ?? 0);

            if ($item->quantity > $available) {
                $pfx = config('catalog.prefix');
                $langId = (int) config('catalog.default_language_id');
                $product = DB::table($pfx . 'product_description')
                    ->where('product_id', $item->product_id)
                    ->where('language_id', $langId)
                    ->value('name');
                $productName = $product ? html_entity_decode($product, ENT_QUOTES | ENT_HTML5, 'UTF-8') : 'Product #' . $item->product_id;

                return redirect()->route('ext.warehousing.transfers.show', $id)
                    ->with('error', "Cannot transfer {$item->quantity} units of {$productName} — only {$available} available at source.");
            }
        }

        DB::transaction(function () use ($transfer) {
            WarehouseStockService::executeTransfer($transfer);
            $transfer->update(['status' => WarehouseTransfer::STATUS_COMPLETED]);
        });

        ActivityLogger::log('completed', 'WarehouseTransfer', $transfer->id, $transfer->reference);

        return redirect()->route('ext.warehousing.transfers.show', $transfer->id)
            ->with('status', "Transfer {$transfer->reference} completed. Stock has been moved.");
    }

    public function cancel($id)
    {
        $transfer = WarehouseTransfer::findOrFail($id);

        if ($transfer->status !== WarehouseTransfer::STATUS_IN_PROGRESS) {
            return redirect()->route('ext.warehousing.transfers.show', $id)
                ->with('error', 'Only In Progress transfers can be cancelled.');
        }

        $transfer->update(['status' => WarehouseTransfer::STATUS_CANCELLED]);

        ActivityLogger::log('cancelled', 'WarehouseTransfer', $transfer->id, $transfer->reference);

        return redirect()->route('ext.warehousing.transfers.show', $transfer->id)
            ->with('status', "Transfer {$transfer->reference} has been cancelled.");
    }

    public function void($id)
    {
        $transfer = WarehouseTransfer::with('items')->findOrFail($id);

        if ($transfer->status !== WarehouseTransfer::STATUS_COMPLETED) {
            return redirect()->route('ext.warehousing.transfers.show', $id)
                ->with('error', 'Only completed transfers can be voided.');
        }

        DB::transaction(function () use ($transfer) {
            foreach ($transfer->items as $item) {
                WarehouseStockService::adjustStock(
                    $transfer->to_warehouse_id,
                    $item->product_id,
                    $item->product_option_value_id ?? 0,
                    -$item->quantity,
                    'transfer_void',
                    "Void {$transfer->reference} — reversed out"
                );
                WarehouseStockService::adjustStock(
                    $transfer->from_warehouse_id,
                    $item->product_id,
                    $item->product_option_value_id ?? 0,
                    $item->quantity,
                    'transfer_void',
                    "Void {$transfer->reference} — reversed in"
                );
            }

            $transfer->update(['status' => WarehouseTransfer::STATUS_VOIDED]);
        });

        ActivityLogger::log('voided', 'WarehouseTransfer', $transfer->id, $transfer->reference);

        return redirect()->route('ext.warehousing.transfers.show', $transfer->id)
            ->with('status', "Transfer {$transfer->reference} has been voided. Stock reversed.");
    }

    public function destroy($id)
    {
        $transfer = WarehouseTransfer::findOrFail($id);

        if ($transfer->status !== WarehouseTransfer::STATUS_DRAFT) {
            return redirect()->route('ext.warehousing.transfers.show', $id)
                ->with('error', 'Only draft transfers can be deleted.');
        }

        $reference = $transfer->reference;

        DB::transaction(function () use ($transfer) {
            WarehouseTransferItem::where('warehouse_transfer_id', $transfer->id)->delete();
            $transfer->delete();
        });

        ActivityLogger::log(
            'deleted',
            'WarehouseTransfer',
            null,
            $reference,
        );

        return redirect()->route('ext.warehousing.transfers.index')
            ->with('status', "Transfer {$reference} deleted.");
    }

    public function pdf($id)
    {
        $transfer = WarehouseTransfer::with(['fromWarehouse', 'toWarehouse', 'items'])
            ->findOrFail($id);

        $pfx = config('catalog.prefix');
        $langId = (int) config('catalog.default_language_id');

        $enrichedItems = [];
        foreach ($transfer->items as $item) {
            $product = DB::table($pfx . 'product as p')
                ->leftJoin($pfx . 'product_description as pd', function ($j) use ($langId) {
                    $j->on('p.product_id', '=', 'pd.product_id')
                      ->where('pd.language_id', '=', $langId);
                })
                ->where('p.product_id', $item->product_id)
                ->first(['pd.name', 'p.sku']);

            $productName = $product ? html_entity_decode($product->name ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') : 'Product #' . $item->product_id;
            $productSku = $product->sku ?? '';
            $optionLabel = '';

            if ($item->product_option_value_id > 0) {
                $ov = DB::table($pfx . 'product_option_value as pov')
                    ->join($pfx . 'product_option as po', 'pov.product_option_id', '=', 'po.product_option_id')
                    ->join($pfx . 'option_description as od', function ($j) use ($langId) {
                        $j->on('po.option_id', '=', 'od.option_id')->where('od.language_id', '=', $langId);
                    })
                    ->join($pfx . 'option_value_description as ovd', function ($j) use ($langId) {
                        $j->on('pov.option_value_id', '=', 'ovd.option_value_id')->where('ovd.language_id', '=', $langId);
                    })
                    ->where('pov.product_option_value_id', $item->product_option_value_id)
                    ->first(['od.name as option_name', 'ovd.name as value_name', 'pov.sku as ov_sku']);

                if ($ov) {
                    $optName = html_entity_decode($ov->option_name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $valName = html_entity_decode($ov->value_name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $optionLabel = "{$optName}: {$valName}";
                    if ($ov->ov_sku) {
                        $productSku = $ov->ov_sku;
                    }
                }
            }

            $enrichedItems[] = (object) [
                'product_name' => $productName,
                'sku' => $productSku,
                'option_label' => $optionLabel,
                'quantity' => $item->quantity,
            ];
        }

        return view('ext-warehousing::transfers.pdf', compact('transfer', 'enrichedItems'));
    }

    /**
     * Product search API for the transfer form typeahead.
     * GET /api/warehousing/products?term=X&warehouse_id=Y
     */
    public function searchProducts(Request $request)
    {
        $term = trim((string) $request->get('term', ''));
        $warehouseId = (int) $request->get('warehouse_id', 0);

        if ($term === '' || mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $pfx = config('catalog.prefix');
        $langId = (int) config('catalog.default_language_id');

        // Support lookup by product ID (used when refreshing available qty)
        $lookupById = false;
        $lookupProductId = 0;
        if (str_starts_with($term, '__id:')) {
            $lookupById = true;
            $lookupProductId = (int) substr($term, 5);
            if ($lookupProductId <= 0) {
                return response()->json([]);
            }
        }

        if ($lookupById) {
            $products = DB::table($pfx . 'product as p')
                ->leftJoin($pfx . 'product_description as pd', function ($j) use ($langId) {
                    $j->on('p.product_id', '=', 'pd.product_id')
                      ->where('pd.language_id', '=', $langId);
                })
                ->where('p.product_id', $lookupProductId)
                ->limit(1)
                ->get(['p.product_id', 'pd.name', 'p.sku', 'p.model']);
        } else {
            // Find products whose option value SKU matches the search term
            $optSkuProductIds = DB::table($pfx . 'product_option_value')
                ->where('sku', 'like', '%' . $term . '%')
                ->where('sku', '!=', '')
                ->pluck('product_id')
                ->unique()
                ->toArray();

            $products = DB::table($pfx . 'product as p')
                ->leftJoin($pfx . 'product_description as pd', function ($j) use ($langId) {
                    $j->on('p.product_id', '=', 'pd.product_id')
                      ->where('pd.language_id', '=', $langId);
                })
                ->where(function ($sub) use ($term, $optSkuProductIds) {
                    $sub->where('pd.name', 'like', '%' . $term . '%')
                        ->orWhere('p.sku', 'like', '%' . $term . '%')
                        ->orWhere('p.model', 'like', '%' . $term . '%');
                    if (!empty($optSkuProductIds)) {
                        $sub->orWhereIn('p.product_id', $optSkuProductIds);
                    }
                })
                ->where('p.status', 1)
                ->limit(20)
                ->get(['p.product_id', 'pd.name', 'p.sku', 'p.model']);
        }

        $results = [];

        foreach ($products as $p) {
            $productName = html_entity_decode($p->name ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // Check if product has option values
            $optVals = DB::table($pfx . 'product_option_value as pov')
                ->join($pfx . 'product_option as po', 'pov.product_option_id', '=', 'po.product_option_id')
                ->join($pfx . 'option_description as od', function ($j) use ($langId) {
                    $j->on('po.option_id', '=', 'od.option_id')->where('od.language_id', '=', $langId);
                })
                ->join($pfx . 'option_value_description as ovd', function ($j) use ($langId) {
                    $j->on('pov.option_value_id', '=', 'ovd.option_value_id')->where('ovd.language_id', '=', $langId);
                })
                ->where('pov.product_id', $p->product_id)
                ->get(['pov.product_option_value_id', 'od.name as option_name', 'ovd.name as value_name', 'pov.sku']);

            if ($optVals->isEmpty()) {
                // Product without options
                $availableQty = null;
                if ($warehouseId > 0) {
                    $availableQty = (int) (DB::table('warehouse_inventory')
                        ->where('warehouse_id', $warehouseId)
                        ->where('product_id', $p->product_id)
                        ->where('product_option_value_id', 0)
                        ->value('quantity') ?? 0);
                }

                $results[] = [
                    'product_id' => (int) $p->product_id,
                    'product_option_value_id' => 0,
                    'name' => $productName,
                    'sku' => $p->sku,
                    'has_options' => false,
                    'option_group' => null,
                    'option_count' => 0,
                    'available_qty' => $availableQty,
                ];
            } else {
                // Product with options — each option value as separate result
                $optCount = $optVals->count();

                // Batch-load inventory for all option values if warehouse specified
                $inventoryMap = collect();
                if ($warehouseId > 0) {
                    $povIds = $optVals->pluck('product_option_value_id')->toArray();
                    $inventoryMap = DB::table('warehouse_inventory')
                        ->where('warehouse_id', $warehouseId)
                        ->where('product_id', $p->product_id)
                        ->whereIn('product_option_value_id', $povIds)
                        ->pluck('quantity', 'product_option_value_id');
                }

                foreach ($optVals as $ov) {
                    $optName = html_entity_decode($ov->option_name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $valName = html_entity_decode($ov->value_name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $optSku = $ov->sku ?: $p->sku;

                    $availableQty = null;
                    if ($warehouseId > 0) {
                        $availableQty = (int) ($inventoryMap->get($ov->product_option_value_id) ?? 0);
                    }

                    $results[] = [
                        'product_id' => (int) $p->product_id,
                        'product_option_value_id' => (int) $ov->product_option_value_id,
                        'name' => "{$productName} \u{2014} {$optName}: {$valName}",
                        'sku' => $optSku,
                        'has_options' => true,
                        'option_group' => (int) $p->product_id,
                        'option_count' => $optCount,
                        'available_qty' => $availableQty,
                    ];
                }
            }
        }

        return response()->json($results);
    }
}
