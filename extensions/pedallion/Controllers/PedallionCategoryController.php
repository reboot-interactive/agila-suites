<?php

namespace Extensions\pedallion\Controllers;

use App\Http\Controllers\Controller;

use Extensions\pedallion\Models\PedallionCategory;
use Extensions\pedallion\Models\PedallionManufacturer;
use Extensions\pedallion\Models\PedallionOrderStatusMap;
use Extensions\pedallion\Models\PedallionSetting;
use Extensions\pedallion\Services\Pedallion\PedallionClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PedallionCategoryController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $query = PedallionCategory::query();

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', '%' . $q . '%');
                if (ctype_digit($q)) {
                    $sub->orWhere('pedallion_category_id', (int) $q);
                }
            });
        }

        $categories = $query
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $setting = PedallionSetting::query()->first();

        $manufacturers = PedallionManufacturer::query()
            ->orderBy('name')
            ->get();

        $statusMaps = PedallionOrderStatusMap::query()
            ->where('context', 'order')
            ->orderBy('pedallion_status')
            ->get();

        $pfx = (string) config('catalog.prefix');
        $langId = (int) config('catalog.default_language_id');
        $erpOrderStatuses = DB::table($pfx . 'order_status')
            ->where('language_id', $langId)
            ->orderBy('order_status_id')
            ->get(['order_status_id', 'name']);

        return view('ext-pedallion::categories.index', [
            'categories'       => $categories,
            'manufacturers'    => $manufacturers,
            'q'                => $q,
            'setting'          => $setting,
            'statusMaps'       => $statusMaps,
            'erpOrderStatuses' => $erpOrderStatuses,
        ]);
    }

    public function fetch()
    {
        $setting = PedallionSetting::query()->first();
        if (!$setting || !$setting->api_key) {
            return redirect()->route('ext.pedallion.categories.index')
                ->with('error', 'Pedallion not configured. Please set API key first.');
        }

        $client = new PedallionClient($setting);

        // Fetch category tree
        $result = $client->getCategoryTree();

        if (!$result['ok']) {
            $msg = $result['body']['message'] ?? ($result['body']['error'] ?? 'Failed');
            return redirect()->route('ext.pedallion.categories.index')
                ->with('error', 'Category fetch failed: ' . $msg);
        }

        $tree = $result['body']['data'] ?? $result['body'] ?? [];

        if (empty($tree)) {
            return redirect()->route('ext.pedallion.categories.index')
                ->with('error', 'No categories returned from Pedallion.');
        }

        // Flatten tree into rows
        $rows = [];
        $this->flattenTree($tree, $rows, null, 0);

        if (empty($rows)) {
            return redirect()->route('ext.pedallion.categories.index')
                ->with('error', 'No categories parsed from response.');
        }

        DB::transaction(function () use ($rows) {
            DB::table('pedallion_categories')->delete();
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('pedallion_categories')->insert($chunk);
            }
        });

        $setting->update(['last_category_sync_at' => now()]);

        return redirect()->route('ext.pedallion.categories.index')
            ->with('status', 'Categories fetched. Total: ' . count($rows));
    }

    public function fetchManufacturers()
    {
        $setting = PedallionSetting::query()->first();
        if (!$setting || !$setting->api_key) {
            return redirect()->route('ext.pedallion.categories.index')
                ->with('error', 'Pedallion not configured. Please set API key first.');
        }

        $client = new PedallionClient($setting);
        $allBrands = [];
        $page = 1;

        do {
            $result = $client->getManufacturers($page, 100);
            if (!$result['ok']) break;

            $data = $result['body']['data'] ?? [];
            if (empty($data)) break;

            $allBrands = array_merge($allBrands, $data);
            $page++;

            $lastPage = $result['body']['meta']['last_page'] ?? $result['body']['last_page'] ?? $page;
        } while ($page <= $lastPage);

        if (empty($allBrands)) {
            return redirect()->route('ext.pedallion.categories.index')
                ->with('error', 'No manufacturers returned from Pedallion.');
        }

        $rows = [];
        foreach ($allBrands as $b) {
            $rows[] = [
                'pedallion_manufacturer_id' => (int) ($b['id'] ?? 0),
                'name'                      => (string) ($b['name'] ?? ''),
                'created_at'                => now(),
                'updated_at'                => now(),
            ];
        }

        DB::transaction(function () use ($rows) {
            DB::table('pedallion_manufacturers')->delete();
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('pedallion_manufacturers')->insert($chunk);
            }
        });

        $setting->update(['last_manufacturer_sync_at' => now()]);

        return redirect()->route('ext.pedallion.categories.index')
            ->with('status', 'Manufacturers fetched. Total: ' . count($rows));
    }

    private function flattenTree(array $nodes, array &$rows, ?int $parentId, int $level): void
    {
        foreach ($nodes as $n) {
            if (!is_array($n)) continue;

            $catId = (int) ($n['id'] ?? 0);
            $name = (string) ($n['name'] ?? '');
            if (!$catId || $name === '') continue;

            $hasChildren = !empty($n['children']);

            $rows[] = [
                'pedallion_category_id' => $catId,
                'parent_id'             => $parentId,
                'name'                  => $name,
                'level'                 => $level,
                'leaf'                  => !$hasChildren,
                'created_at'            => now(),
                'updated_at'            => now(),
            ];

            if ($hasChildren && is_array($n['children'])) {
                $this->flattenTree($n['children'], $rows, $catId, $level + 1);
            }
        }
    }
}
