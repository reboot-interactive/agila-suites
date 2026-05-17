<?php

namespace Extensions\pedallion\Controllers;

use App\Http\Controllers\Controller;

use Extensions\pedallion\Models\PedallionProductLink;
use Extensions\pedallion\Models\PedallionProductGroup;
use Extensions\pedallion\Models\PedallionProductGroupProduct;
use Extensions\pedallion\Models\PedallionSetting;
use Extensions\pedallion\Services\Pedallion\PedallionClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PedallionProductController extends Controller
{
    public function index(Request $request)
    {
        $pfx = (string) config('catalog.prefix');
        $langId = (int) config('catalog.default_language_id');
        $q = trim((string) $request->get('q', ''));
        $syncStatus = (string) $request->get('sync_status', 'all');
        $manufacturerFilter = (string) $request->get('manufacturer', 'all');
        $groupFilter = (string) $request->get('product_group', 'all');
        $erpStatus = (string) $request->get('erp_status', 'all');

        // Build product group → product mapping
        $groupMapping = $this->getGroupProductMapping();

        $query_builder = DB::table($pfx . 'product as p')
            ->leftJoin($pfx . 'product_description as pd', function ($j) use ($langId) {
                $j->on('p.product_id', '=', 'pd.product_id')
                    ->where('pd.language_id', '=', $langId);
            })
            ->leftJoin($pfx . 'manufacturer as m', 'p.manufacturer_id', '=', 'm.manufacturer_id')
            ->select(
                'p.product_id',
                'pd.name',
                'p.image',
                'p.model',
                'p.sku',
                'p.price',
                'p.quantity',
                'p.status',
                'm.name as manufacturer_name'
            );

        // Product group filter
        if ($groupFilter === 'none') {
            $allMatchedIds = $groupMapping['ids'];
            if (!empty($allMatchedIds)) {
                $query_builder->whereNotIn('p.product_id', $allMatchedIds);
            }
        } elseif ($groupFilter !== 'all') {
            $specificIds = $this->getProductIdsByGroup((int) $groupFilter);
            if (empty($specificIds)) {
                $query_builder->whereRaw('1=0');
            } else {
                $query_builder->whereIn('p.product_id', $specificIds);
            }
        } else {
            // Default: show products matching at least one product group + products with existing links
            $allMatchedIds = $groupMapping['ids'] ?? [];
            $linkedIds = PedallionProductLink::query()->pluck('product_id')->unique()->toArray();
            $allVisibleIds = array_values(array_unique(array_merge($allMatchedIds, $linkedIds)));
            if (empty($allVisibleIds)) {
                $query_builder->whereRaw('1=0');
            } else {
                $query_builder->whereIn('p.product_id', $allVisibleIds);
            }
        }

        // Manufacturer filter
        if ($manufacturerFilter !== 'all') {
            $query_builder->where('p.manufacturer_id', (int) $manufacturerFilter);
        }

        // ERP Status filter
        if ($erpStatus === 'enabled') {
            $query_builder->where('p.status', 1);
        } elseif ($erpStatus === 'disabled') {
            $query_builder->where('p.status', 0);
        }

        // Text search
        if ($q !== '') {
            $query_builder->where(function ($sub) use ($q) {
                $sub->where('pd.name', 'like', '%' . $q . '%')
                    ->orWhere('p.model', 'like', '%' . $q . '%')
                    ->orWhere('p.sku', 'like', '%' . $q . '%');
            });
        }

        // Sync status filter
        if ($syncStatus !== 'all') {
            $filteredLinkIds = PedallionProductLink::query();
            if ($syncStatus === 'linked') {
                $filteredProductIds = $filteredLinkIds->pluck('product_id')->unique()->toArray();
                if (empty($filteredProductIds)) {
                    $query_builder->whereRaw('1=0');
                } else {
                    $query_builder->whereIn('p.product_id', $filteredProductIds);
                }
            } elseif ($syncStatus === 'not_linked') {
                $linkedProductIds = PedallionProductLink::pluck('product_id')->unique()->toArray();
                if (!empty($linkedProductIds)) {
                    $query_builder->whereNotIn('p.product_id', $linkedProductIds);
                }
            } elseif ($syncStatus === 'synced') {
                $syncedIds = PedallionProductLink::where('sync_status', 'synced')->pluck('product_id')->unique()->toArray();
                if (empty($syncedIds)) {
                    $query_builder->whereRaw('1=0');
                } else {
                    $query_builder->whereIn('p.product_id', $syncedIds);
                }
            } elseif ($syncStatus === 'error') {
                $errorIds = PedallionProductLink::where('sync_status', 'error')->pluck('product_id')->unique()->toArray();
                if (empty($errorIds)) {
                    $query_builder->whereRaw('1=0');
                } else {
                    $query_builder->whereIn('p.product_id', $errorIds);
                }
            } elseif ($syncStatus === 'pending') {
                $pendingIds = PedallionProductLink::where('sync_status', 'pending')->pluck('product_id')->unique()->toArray();
                if (empty($pendingIds)) {
                    $query_builder->whereRaw('1=0');
                } else {
                    $query_builder->whereIn('p.product_id', $pendingIds);
                }
            }
        }

        $products = $query_builder->orderBy('pd.name')->paginate(50)->withQueryString();

        // Get Pedallion links keyed by product_id
        $productIds = $products->pluck('product_id')->unique()->all();
        $pedallionLinks = collect();
        if (!empty($productIds)) {
            $pedallionLinks = PedallionProductLink::whereIn('product_id', $productIds)
                ->get()
                ->groupBy('product_id');
        }

        // Product group names by product ID
        $groupsByProductId = [];
        foreach ($groupMapping['map'] ?? [] as $groupId => $pIds) {
            $groupName = $groupMapping['names'][$groupId] ?? "Group #{$groupId}";
            foreach ($pIds as $pid) {
                $groupsByProductId[$pid][] = $groupName;
            }
        }

        // Manufacturer list for filter dropdown
        $allManufacturers = DB::table($pfx . 'manufacturer')
            ->orderBy('name')
            ->pluck('name', 'manufacturer_id');

        // Product group list for filter dropdown
        $allGroups = PedallionProductGroup::orderBy('name')->pluck('name', 'id');

        return view('ext-pedallion::products.index', compact(
            'products', 'pedallionLinks', 'groupsByProductId',
            'q', 'syncStatus', 'manufacturerFilter', 'groupFilter', 'erpStatus',
            'allManufacturers', 'allGroups'
        ));
    }

    private function getGroupProductMapping(): array
    {
        $groups = PedallionProductGroup::all();
        $pfx = (string) config('catalog.prefix');
        $allIds = [];
        $map = [];
        $names = [];

        foreach ($groups as $group) {
            $names[$group->id] = $group->name;
            $catIds = $group->catalog_category_ids ?? [];
            $mfgIds = $group->manufacturer_ids ?? [];

            $groupProductIds = [];

            if (!empty($catIds) || !empty($mfgIds)) {
                $q = DB::table($pfx . 'product as p');

                if (!empty($catIds)) {
                    $q->join($pfx . 'product_to_category as ptc', 'p.product_id', '=', 'ptc.product_id')
                        ->whereIn('ptc.category_id', $catIds);
                }

                if (!empty($mfgIds)) {
                    $q->whereIn('p.manufacturer_id', $mfgIds);
                }

                $groupProductIds = $q->distinct()->pluck('p.product_id')->all();
            }

            // Also include manually added products
            $manualIds = PedallionProductGroupProduct::where('pedallion_product_group_id', $group->id)
                ->pluck('product_id')->all();

            $merged = array_values(array_unique(array_merge($groupProductIds, $manualIds)));
            $map[$group->id] = $merged;
            $allIds = array_merge($allIds, $merged);
        }

        return [
            'ids'   => array_values(array_unique($allIds)),
            'map'   => $map,
            'names' => $names,
        ];
    }

    private function getProductIdsByGroup(int $groupId): array
    {
        $group = PedallionProductGroup::find($groupId);
        if (!$group) return [];

        $pfx = (string) config('catalog.prefix');
        $catIds = $group->catalog_category_ids ?? [];
        $mfgIds = $group->manufacturer_ids ?? [];
        $ids = [];

        if (!empty($catIds) || !empty($mfgIds)) {
            $q = DB::table($pfx . 'product as p');
            if (!empty($catIds)) {
                $q->join($pfx . 'product_to_category as ptc', 'p.product_id', '=', 'ptc.product_id')
                    ->whereIn('ptc.category_id', $catIds);
            }
            if (!empty($mfgIds)) {
                $q->whereIn('p.manufacturer_id', $mfgIds);
            }
            $ids = $q->distinct()->pluck('p.product_id')->all();
        }

        $manualIds = PedallionProductGroupProduct::where('pedallion_product_group_id', $groupId)
            ->pluck('product_id')->all();

        return array_values(array_unique(array_merge($ids, $manualIds)));
    }

    public function link(Request $request)
    {
        $data = $request->validate([
            'product_id'    => ['required', 'integer'],
            'pedallion_sku' => ['required', 'string', 'max:128'],
        ]);

        PedallionProductLink::updateOrCreate(
            [
                'product_id'    => $data['product_id'],
                'pedallion_sku' => $data['pedallion_sku'],
            ],
            ['sync_status' => 'pending']
        );

        return redirect()->route('ext.pedallion.products.index')
            ->with('status', "Product #{$data['product_id']} linked to SKU {$data['pedallion_sku']}.");
    }

    public function unlink(int $id)
    {
        PedallionProductLink::findOrFail($id)->delete();
        return redirect()->route('ext.pedallion.products.index')->with('status', 'Product unlinked.');
    }

    /**
     * Push a product to Pedallion by product_id.
     * Auto-links using catalog SKU if not already linked.
     */
    public function push(int $productId)
    {
        $payload = $this->buildPayload($productId);

        if (is_string($payload)) {
            return back()->with('error', $payload);
        }

        $link = $payload['_link'];
        $imageUrls = $payload['_image_urls'] ?? [];
        $sku = $link->pedallion_sku;
        $productName = $payload['name'];
        unset($payload['_link'], $payload['_image_urls']);

        $setting = PedallionSetting::query()->firstOrFail();
        $client = new PedallionClient($setting);

        // Try update first, fall back to create
        $result = $client->updateProduct($sku, $payload);

        if (!$result['ok'] && $result['status'] === 404) {
            $result = $client->createProduct($payload);
        }

        if ($result['ok']) {
            $link->update([
                'sync_status'    => 'synced',
                'sync_error'     => null,
                'last_pushed_at' => now(),
            ]);

            // Upload images (max 8)
            $imgMsg = '';
            if (!empty($imageUrls)) {
                $imgResult = $client->uploadImages($sku, array_slice($imageUrls, 0, 8));
                if ($imgResult['ok']) {
                    $imgMsg = ' + ' . min(count($imageUrls), 8) . ' image(s) uploaded.';
                } else {
                    $imgMsg = ' (images failed: ' . ($imgResult['body']['message'] ?? 'unknown error') . ')';
                }
            }

            return back()->with('status', "Product \"{$productName}\" pushed to Pedallion (SKU: {$sku}).{$imgMsg}");
        }

        $error = $result['body']['message'] ?? json_encode($result['body']);
        $link->update(['sync_status' => 'error', 'sync_error' => $error]);
        return back()->with('error', "Push failed: {$error}");
    }

    /**
     * Build Pedallion API payload from catalog product.
     * Returns array on success, error string on failure.
     */
    private function buildPayload(int $productId): array|string
    {
        $pfx = (string) config('catalog.prefix');
        $langId = (int) config('catalog.default_language_id');

        $product = DB::table($pfx . 'product as p')
            ->join($pfx . 'product_description as pd', function ($j) use ($langId) {
                $j->on('p.product_id', '=', 'pd.product_id')->where('pd.language_id', $langId);
            })
            ->leftJoin($pfx . 'manufacturer as m', 'p.manufacturer_id', '=', 'm.manufacturer_id')
            ->where('p.product_id', $productId)
            ->select('p.*', 'pd.name', 'pd.description', 'm.name as manufacturer_name')
            ->first();

        if (!$product) {
            return "Product #{$productId} not found in catalog.";
        }

        $sku = trim((string) ($product->sku ?: $product->model));
        if ($sku === '') {
            return "Product #{$productId} has no SKU or model — cannot push to Pedallion.";
        }

        // Auto-link if not already linked
        $link = PedallionProductLink::firstOrCreate(
            ['product_id' => $productId],
            ['pedallion_sku' => $sku, 'sync_status' => 'pending']
        );

        // Find the Pedallion product group for this product (for category_id and condition)
        $group = $this->findGroupForProduct($productId);

        $payload = [
            'sku'            => $link->pedallion_sku,
            'name'           => $product->name,
            'description'    => trim(strip_tags(html_entity_decode($product->description ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?: $product->name,
            'price'          => (float) $product->price,
            'stock_quantity' => max(0, (int) $product->quantity),
            'condition'      => $group->condition ?? 'new',
            'weight'         => max(0.01, (float) ($product->weight ?? 0.01)),
            'length'         => max(0.01, (float) ($product->length ?? 0.01)),
            'width'          => max(0.01, (float) ($product->width ?? 0.01)),
            'height'         => max(0.01, (float) ($product->height ?? 0.01)),
        ];

        // Category: from product group's pedallion_category_id
        if ($group && $group->pedallion_category_id) {
            $payload['category_id'] = (int) $group->pedallion_category_id;
        } else {
            // Fallback: use first Pedallion category available
            $firstCat = \App\Models\PedallionCategory::where('leaf', true)->first();
            if ($firstCat) {
                $payload['category_id'] = (int) $firstCat->pedallion_category_id;
            }
        }

        // Manufacturer: from catalog
        if (!empty($product->manufacturer_name)) {
            $payload['manufacturer'] = $product->manufacturer_name;
        }

        // Build variations from product_option_combinations
        $combos = DB::table('product_option_combinations as c')
            ->where('c.product_id', $productId)
            ->orderBy('c.sort_order')
            ->get(['c.id', 'c.sku', 'c.quantity', 'c.absolute_price']);

        if ($combos->isNotEmpty()) {
            // Load pivot → option value names grouped by option group
            $comboIds = $combos->pluck('id')->all();
            $pivots = DB::table('product_option_combination_values as cv')
                ->join($pfx . 'product_option_value as pov', 'cv.product_option_value_id', '=', 'pov.product_option_value_id')
                ->join($pfx . 'option_description as od', function ($j) use ($langId) {
                    $j->on('pov.option_id', '=', 'od.option_id')
                        ->where('od.language_id', '=', $langId);
                })
                ->join($pfx . 'option_value_description as ovd', function ($j) use ($langId) {
                    $j->on('pov.option_value_id', '=', 'ovd.option_value_id')
                        ->where('ovd.language_id', '=', $langId);
                })
                ->join($pfx . 'product_option as po', 'pov.product_option_id', '=', 'po.product_option_id')
                ->whereIn('cv.combination_id', $comboIds)
                ->orderBy('po.product_option_id')
                ->get(['cv.combination_id', 'od.name as option_name', 'ovd.name as value_name']);

            // Group pivot data by combination_id
            $pivotByCombo = [];
            foreach ($pivots as $pv) {
                $pivotByCombo[(int) $pv->combination_id][] = [
                    'option_name' => html_entity_decode((string) $pv->option_name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'value_name' => html_entity_decode((string) $pv->value_name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                ];
            }

            // Determine option group names from first combo
            $firstPivot = $pivotByCombo[$combos->first()->id] ?? [];
            $optGroupCount = count($firstPivot);

            $variations = [];
            $variations['option1_name'] = $firstPivot[0]['option_name'] ?? '';
            $variations['option1_values'] = [];
            if ($optGroupCount >= 2) {
                $variations['option2_name'] = $firstPivot[1]['option_name'] ?? '';
                $variations['option2_values'] = [];
            }
            $variations['variants'] = [];

            foreach ($combos as $c) {
                $pv = $pivotByCombo[(int) $c->id] ?? [];
                $val1 = $pv[0]['value_name'] ?? '';
                $val2 = $pv[1]['value_name'] ?? null;

                if ($val1 && !in_array($val1, $variations['option1_values'])) {
                    $variations['option1_values'][] = $val1;
                }
                if ($val2 && isset($variations['option2_values']) && !in_array($val2, $variations['option2_values'])) {
                    $variations['option2_values'][] = $val2;
                }

                $variant = [
                    'opt1'  => $val1,
                    'price' => max(0, (float) ($c->absolute_price ?? $product->price)),
                    'stock' => max(0, (int) $c->quantity),
                ];
                if ($val2) {
                    $variant['opt2'] = $val2;
                }
                if (!empty($c->sku)) {
                    $variant['sku'] = $c->sku;
                }
                $variations['variants'][] = $variant;
            }

            if (!empty($variations['variants'])) {
                $payload['variations'] = $variations;
            }
        }

        // Collect image URLs
        $imageUrls = [];
        $baseUrl = rtrim((string) config('catalog.public_url'), '/');
        $imgPrefix = config('catalog.image_prefix', 'image');

        if (!empty($product->image)) {
            $imageUrls[] = $baseUrl . '/' . $imgPrefix . '/' . ltrim($product->image, '/');
        }

        $additionalImages = DB::table($pfx . 'product_image')
            ->where('product_id', $productId)
            ->orderBy('sort_order')
            ->pluck('image')
            ->all();

        foreach ($additionalImages as $img) {
            if (!empty($img)) {
                $imageUrls[] = $baseUrl . '/' . $imgPrefix . '/' . ltrim($img, '/');
            }
        }

        // Stash link and images for caller
        $payload['_link'] = $link;
        $payload['_image_urls'] = $imageUrls;

        return $payload;
    }

    /**
     * Find the first Pedallion product group that matches this product.
     */
    private function findGroupForProduct(int $productId): ?PedallionProductGroup
    {
        $pfx = (string) config('catalog.prefix');

        // Check manual product group assignments first
        $manualGroupId = PedallionProductGroupProduct::where('product_id', $productId)
            ->value('pedallion_product_group_id');
        if ($manualGroupId) {
            return PedallionProductGroup::find($manualGroupId);
        }

        // Check product groups by category/manufacturer filters
        $productCatIds = DB::table($pfx . 'product_to_category')
            ->where('product_id', $productId)
            ->pluck('category_id')
            ->all();

        $productMfgId = DB::table($pfx . 'product')
            ->where('product_id', $productId)
            ->value('manufacturer_id');

        $groups = PedallionProductGroup::all();

        foreach ($groups as $group) {
            $catIds = $group->catalog_category_ids ?? [];
            $mfgIds = $group->manufacturer_ids ?? [];

            if (empty($catIds) && empty($mfgIds)) continue;

            $catMatch = empty($catIds) || !empty(array_intersect($catIds, $productCatIds));
            $mfgMatch = empty($mfgIds) || in_array($productMfgId, $mfgIds);

            if ($catMatch && $mfgMatch) {
                return $group;
            }
        }

        return null;
    }

    public function syncQty(int $productId)
    {
        $link = PedallionProductLink::where('product_id', $productId)->first();
        if (!$link) {
            return back()->with('error', "Product #{$productId} is not linked to Pedallion. Push it first.");
        }

        $setting = PedallionSetting::query()->firstOrFail();
        $client = new PedallionClient($setting);
        $pfx = (string) config('catalog.prefix');
        $langId = (int) config('catalog.default_language_id');
        $sku = $link->pedallion_sku;

        // Check if product has combinations — if so, update variant stock
        $combos = DB::table('product_option_combinations')
            ->where('product_id', $productId)
            ->get(['sku', 'quantity']);

        if ($combos->isNotEmpty()) {
            $updated = 0;
            $errors = [];
            foreach ($combos as $c) {
                $variantSku = $c->sku ?: null;
                if (!$variantSku) continue;

                $qty = max(0, (int) $c->quantity);
                $result = $client->updateVariantStock($sku, $variantSku, $qty);
                if ($result['ok']) {
                    $updated++;
                } else {
                    $errors[] = $variantSku . ': ' . ($result['body']['message'] ?? 'failed');
                }
            }

            $msg = "Variant stock synced: {$updated} updated.";
            if (!empty($errors)) {
                $msg .= ' Errors: ' . implode(', ', $errors);
            }
            return back()->with($errors ? 'error' : 'status', $msg);
        }

        // No options — update parent stock
        $qty = max(0, (int) DB::table($pfx . 'product')
            ->where('product_id', $productId)
            ->value('quantity'));

        $result = $client->updateStock($sku, $qty);

        if ($result['ok']) {
            return back()->with('status', "Qty synced ({$qty}) for SKU {$sku}.");
        }

        $error = $result['body']['message'] ?? json_encode($result['body']);
        return back()->with('error', "Sync qty failed: {$error}");
    }

    public function syncPrice(int $productId)
    {
        $link = PedallionProductLink::where('product_id', $productId)->first();
        if (!$link) {
            return back()->with('error', "Product #{$productId} is not linked to Pedallion. Push it first.");
        }

        $setting = PedallionSetting::query()->firstOrFail();
        $client = new PedallionClient($setting);
        $pfx = (string) config('catalog.prefix');
        $langId = (int) config('catalog.default_language_id');
        $sku = $link->pedallion_sku;

        $basePrice = (float) DB::table($pfx . 'product')
            ->where('product_id', $productId)
            ->value('price');

        $updateData = ['price' => $basePrice];

        // Check for combinations — rebuild variations block for price sync
        $combos = DB::table('product_option_combinations as c')
            ->where('c.product_id', $productId)
            ->orderBy('c.sort_order')
            ->get(['c.id', 'c.sku', 'c.quantity', 'c.absolute_price']);

        if ($combos->isNotEmpty()) {
            $comboIds = $combos->pluck('id')->all();
            $pivots = DB::table('product_option_combination_values as cv')
                ->join($pfx . 'product_option_value as pov', 'cv.product_option_value_id', '=', 'pov.product_option_value_id')
                ->join($pfx . 'option_description as od', function ($j) use ($langId) {
                    $j->on('pov.option_id', '=', 'od.option_id')->where('od.language_id', '=', $langId);
                })
                ->join($pfx . 'option_value_description as ovd', function ($j) use ($langId) {
                    $j->on('pov.option_value_id', '=', 'ovd.option_value_id')->where('ovd.language_id', '=', $langId);
                })
                ->join($pfx . 'product_option as po', 'pov.product_option_id', '=', 'po.product_option_id')
                ->whereIn('cv.combination_id', $comboIds)
                ->orderBy('po.product_option_id')
                ->get(['cv.combination_id', 'od.name as option_name', 'ovd.name as value_name']);

            $pivotByCombo = [];
            foreach ($pivots as $pv) {
                $pivotByCombo[(int) $pv->combination_id][] = [
                    'option_name' => html_entity_decode((string) $pv->option_name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'value_name' => html_entity_decode((string) $pv->value_name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                ];
            }

            $firstPivot = $pivotByCombo[$combos->first()->id] ?? [];
            $variations = [
                'option1_name' => $firstPivot[0]['option_name'] ?? '',
                'option1_values' => [],
                'variants' => [],
            ];
            if (count($firstPivot) >= 2) {
                $variations['option2_name'] = $firstPivot[1]['option_name'] ?? '';
                $variations['option2_values'] = [];
            }

            foreach ($combos as $c) {
                $pv = $pivotByCombo[(int) $c->id] ?? [];
                $val1 = $pv[0]['value_name'] ?? '';
                $val2 = $pv[1]['value_name'] ?? null;

                if ($val1 && !in_array($val1, $variations['option1_values'])) {
                    $variations['option1_values'][] = $val1;
                }
                if ($val2 && isset($variations['option2_values']) && !in_array($val2, $variations['option2_values'])) {
                    $variations['option2_values'][] = $val2;
                }

                $variant = [
                    'opt1'  => $val1,
                    'price' => max(0, (float) ($c->absolute_price ?? $basePrice)),
                    'stock' => max(0, (int) $c->quantity),
                ];
                if ($val2) $variant['opt2'] = $val2;
                if (!empty($c->sku)) $variant['sku'] = $c->sku;
                $variations['variants'][] = $variant;
            }

            $updateData['variations'] = $variations;
        }

        $result = $client->updateProduct($sku, $updateData);

        if ($result['ok']) {
            return back()->with('status', "Price synced for SKU {$sku}." . ($optRows->isNotEmpty() ? " ({$optRows->count()} variant prices updated)" : ''));
        }

        $error = $result['body']['message'] ?? json_encode($result['body']);
        return back()->with('error', "Sync price failed: {$error}");
    }

    public function deleteFromPedallion(int $productId)
    {
        $link = PedallionProductLink::where('product_id', $productId)->first();
        if (!$link) {
            return back()->with('error', "Product #{$productId} is not linked to Pedallion.");
        }

        $setting = PedallionSetting::query()->firstOrFail();
        $client = new PedallionClient($setting);

        $result = $client->deleteProduct($link->pedallion_sku);

        if ($result['ok'] || $result['status'] === 404) {
            $link->delete();
            return back()->with('status', 'Product deleted from Pedallion and unlinked.');
        }

        $error = $result['body']['message'] ?? json_encode($result['body']);
        return back()->with('error', "Delete failed: {$error}");
    }

    /**
     * Sync a single product: check if it exists on Pedallion by SKU and link it.
     */
    public function sync(int $productId)
    {
        $pfx = (string) config('catalog.prefix');
        $product = DB::table($pfx . 'product')->where('product_id', $productId)->first();

        if (!$product) {
            return back()->with('error', "Product #{$productId} not found.");
        }

        $sku = trim((string) ($product->sku ?: $product->model));
        if ($sku === '') {
            return back()->with('error', "Product #{$productId} has no SKU or model.");
        }

        $setting = PedallionSetting::query()->firstOrFail();
        $client = new PedallionClient($setting);
        $result = $client->getProduct($sku);

        if (!$result['ok']) {
            return back()->with('error', "SKU \"{$sku}\" not found on Pedallion.");
        }

        PedallionProductLink::updateOrCreate(
            ['product_id' => $productId],
            ['pedallion_sku' => $sku, 'sync_status' => 'synced', 'sync_error' => null, 'last_pushed_at' => now()]
        );

        return back()->with('status', "Product #{$productId} synced — linked to Pedallion SKU \"{$sku}\".");
    }

    public function bulkSync(Request $request)
    {
        $productIds = $request->input('product_ids', []);
        if (empty($productIds)) {
            return back()->with('error', 'No products selected.');
        }

        $pfx = (string) config('catalog.prefix');
        $setting = PedallionSetting::query()->firstOrFail();
        $client = new PedallionClient($setting);

        $products = DB::table($pfx . 'product')
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'sku', 'model']);

        $synced = 0;
        $notFound = 0;

        foreach ($products as $p) {
            $sku = trim((string) ($p->sku ?: $p->model));
            if ($sku === '') { $notFound++; continue; }

            $result = $client->getProduct($sku);
            if (!$result['ok']) { $notFound++; continue; }

            PedallionProductLink::updateOrCreate(
                ['product_id' => $p->product_id],
                ['pedallion_sku' => $sku, 'sync_status' => 'synced', 'sync_error' => null, 'last_pushed_at' => now()]
            );
            $synced++;
        }

        $msg = "{$synced} product(s) synced from Pedallion.";
        if ($notFound > 0) $msg .= " {$notFound} not found or missing SKU.";

        return redirect()->route('ext.pedallion.products.index')->with('status', $msg);
    }

    public function bulkUnlink(Request $request)
    {
        $linkIds = $request->input('link_ids', []);
        if (empty($linkIds)) {
            return back()->with('error', 'No products selected.');
        }

        $count = PedallionProductLink::whereIn('id', $linkIds)->delete();

        return redirect()->route('ext.pedallion.products.index')
            ->with('status', "{$count} product(s) unlinked from Pedallion.");
    }

    public function bulkPush(Request $request)
    {
        $productIds = $request->input('product_ids', []);
        $linkIds = $request->input('link_ids', []);
        $setting = PedallionSetting::query()->firstOrFail();
        $client = new PedallionClient($setting);

        // Get product IDs: from direct selection, from links, or all linked
        if (!empty($productIds)) {
            // Already have product IDs directly
        } elseif (!empty($linkIds)) {
            $productIds = PedallionProductLink::whereIn('id', $linkIds)->pluck('product_id')->unique()->all();
        } else {
            $productIds = PedallionProductLink::pluck('product_id')->unique()->all();
        }

        $pushed = 0;
        $errors = 0;
        $batch = [];
        $linkMap = [];

        foreach ($productIds as $pid) {
            $payload = $this->buildPayload($pid);
            if (is_string($payload)) {
                $errors++;
                continue;
            }

            $link = $payload['_link'];
            unset($payload['_link']);

            $batch[] = $payload;
            $linkMap[$link->pedallion_sku] = $link;

            if (count($batch) >= 100) {
                $this->sendBatch($client, $batch, $linkMap);
                $pushed += count($batch);
                $batch = [];
                $linkMap = [];
            }
        }

        if (!empty($batch)) {
            $this->sendBatch($client, $batch, $linkMap);
            $pushed += count($batch);
        }

        $msg = "Bulk push completed: {$pushed} product(s) pushed.";
        if ($errors > 0) $msg .= " {$errors} skipped due to errors.";

        return redirect()->route('ext.pedallion.products.index')->with('status', $msg);
    }

    public function bulkSyncQty(Request $request)
    {
        $linkIds = $request->input('link_ids', []);
        $setting = PedallionSetting::query()->firstOrFail();
        $client = new PedallionClient($setting);
        $pfx = (string) config('catalog.prefix');

        $links = empty($linkIds)
            ? PedallionProductLink::all()
            : PedallionProductLink::whereIn('id', $linkIds)->get();

        $productIds = $links->pluck('product_id')->unique()->all();
        $quantities = DB::table($pfx . 'product')
            ->whereIn('product_id', $productIds)
            ->pluck('quantity', 'product_id');

        $items = [];
        foreach ($links as $link) {
            $qty = max(0, (int) ($quantities[$link->product_id] ?? 0));
            $items[] = ['sku' => $link->pedallion_sku, 'quantity' => $qty];

            if (count($items) >= 100) {
                $client->batchUpdateStock($items);
                $items = [];
            }
        }

        if (!empty($items)) {
            $client->batchUpdateStock($items);
        }

        $setting->update(['last_stock_push_at' => now()]);

        return redirect()->route('ext.pedallion.products.index')
            ->with('status', 'Stock quantities synced for ' . $links->count() . ' products.');
    }

    public function bulkSyncPrice(Request $request)
    {
        $linkIds = $request->input('link_ids', []);
        $setting = PedallionSetting::query()->firstOrFail();
        $client = new PedallionClient($setting);
        $pfx = (string) config('catalog.prefix');

        $links = empty($linkIds)
            ? PedallionProductLink::all()
            : PedallionProductLink::whereIn('id', $linkIds)->get();

        $productIds = $links->pluck('product_id')->unique()->all();
        $prices = DB::table($pfx . 'product')
            ->whereIn('product_id', $productIds)
            ->pluck('price', 'product_id');

        foreach ($links as $link) {
            $price = (float) ($prices[$link->product_id] ?? 0);
            $client->updateProduct($link->pedallion_sku, ['price' => $price]);
        }

        return redirect()->route('ext.pedallion.products.index')
            ->with('status', 'Prices synced for ' . $links->count() . ' products.');
    }

    public function bulkDelete(Request $request)
    {
        $linkIds = $request->input('link_ids', []);
        if (empty($linkIds)) {
            return back()->with('error', 'No products selected.');
        }

        $setting = PedallionSetting::query()->firstOrFail();
        $client = new PedallionClient($setting);

        $links = PedallionProductLink::whereIn('id', $linkIds)->get();
        $deleted = 0;

        foreach ($links as $link) {
            $result = $client->deleteProduct($link->pedallion_sku);
            if ($result['ok'] || $result['status'] === 404) {
                $link->delete();
                $deleted++;
            }
        }

        return redirect()->route('ext.pedallion.products.index')
            ->with('status', "{$deleted} product(s) deleted from Pedallion.");
    }

    public function pushQty()
    {
        $setting = PedallionSetting::query()->firstOrFail();
        $client = new PedallionClient($setting);
        $pfx = (string) config('catalog.prefix');

        $links = PedallionProductLink::all();
        $productIds = $links->pluck('product_id')->unique()->all();

        $quantities = DB::table($pfx . 'product')
            ->whereIn('product_id', $productIds)
            ->pluck('quantity', 'product_id');

        $items = [];
        foreach ($links as $link) {
            $qty = max(0, (int) ($quantities[$link->product_id] ?? 0));
            $items[] = ['sku' => $link->pedallion_sku, 'quantity' => $qty];

            if (count($items) >= 100) {
                $client->batchUpdateStock($items);
                $items = [];
            }
        }

        if (!empty($items)) {
            $client->batchUpdateStock($items);
        }

        $setting->update(['last_stock_push_at' => now()]);

        return redirect()->route('ext.pedallion.products.index')
            ->with('status', 'Stock quantities pushed for ' . $links->count() . ' products.');
    }

    private function sendBatch(PedallionClient $client, array $batch, array $linkMap): void
    {
        $result = $client->batchSync($batch);

        if ($result['ok']) {
            foreach ($linkMap as $sku => $link) {
                $link->update([
                    'sync_status'    => 'synced',
                    'sync_error'     => null,
                    'last_pushed_at' => now(),
                ]);
            }

            // Upload images for each product in the batch
            foreach ($batch as $payload) {
                $sku = $payload['sku'] ?? null;
                $imageUrls = $payload['_image_urls'] ?? [];
                if ($sku && !empty($imageUrls)) {
                    $client->uploadImages($sku, array_slice($imageUrls, 0, 8));
                }
            }
            return;
        }

        // Batch failed — fall back to individual pushes so one bad product doesn't block the rest
        foreach ($batch as $payload) {
            $sku = $payload['sku'] ?? null;
            $link = $linkMap[$sku] ?? null;
            if (!$link) continue;

            $imageUrls = $payload['_image_urls'] ?? [];
            unset($payload['_image_urls']);

            $individual = $client->updateProduct($sku, $payload);
            if (!$individual['ok'] && $individual['status'] === 404) {
                $individual = $client->createProduct($payload);
            }

            if ($individual['ok']) {
                $link->update([
                    'sync_status'    => 'synced',
                    'sync_error'     => null,
                    'last_pushed_at' => now(),
                ]);

                // Upload images
                if (!empty($imageUrls)) {
                    $client->uploadImages($sku, array_slice($imageUrls, 0, 8));
                }
            } else {
                $error = $individual['body']['message'] ?? json_encode($individual['body']);
                $link->update(['sync_status' => 'error', 'sync_error' => $error]);
            }
        }
    }
}
