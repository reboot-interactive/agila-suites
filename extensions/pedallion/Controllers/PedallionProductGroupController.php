<?php

namespace Extensions\pedallion\Controllers;

use App\Http\Controllers\Controller;

use Extensions\pedallion\Models\PedallionCategory;
use Extensions\pedallion\Models\PedallionProductGroup;
use Extensions\pedallion\Models\PedallionProductGroupProduct;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PedallionProductGroupController extends Controller
{
    public function index()
    {
        $pfx = (string) config('catalog.prefix');
        $langId = (int) config('catalog.default_language_id');

        $groups = PedallionProductGroup::orderByDesc('id')->get();

        $catIds = $groups->pluck('catalog_category_ids')->filter()->flatten()->unique()->values()->all();
        $categoryNames = collect();
        if (!empty($catIds)) {
            $categoryNames = DB::table($pfx . 'category_description')
                ->whereIn('category_id', $catIds)
                ->where('language_id', $langId)
                ->pluck('name', 'category_id');
        }

        $mfgIds = $groups->pluck('manufacturer_ids')->filter()->flatten()->unique()->values()->all();
        $manufacturerNames = collect();
        if (!empty($mfgIds)) {
            $manufacturerNames = DB::table($pfx . 'manufacturer')
                ->whereIn('manufacturer_id', $mfgIds)
                ->pluck('name', 'manufacturer_id');
        }

        $pedallionCategories = PedallionCategory::pluck('name', 'pedallion_category_id');

        return view('ext-pedallion::product-groups.index', compact(
            'groups', 'categoryNames', 'manufacturerNames', 'pedallionCategories'
        ));
    }

    public function create()
    {
        return $this->form(new PedallionProductGroup(), 'create');
    }

    public function edit(int $id)
    {
        $group = PedallionProductGroup::findOrFail($id);
        return $this->form($group, 'edit');
    }

    private function form(PedallionProductGroup $group, string $mode)
    {
        $pfx = (string) config('catalog.prefix');
        $langId = (int) config('catalog.default_language_id');

        $catalogCategories = DB::table($pfx . 'category_description')
            ->where('language_id', $langId)
            ->orderBy('name')
            ->get(['category_id', 'name']);

        $manufacturers = DB::table($pfx . 'manufacturer')
            ->orderBy('name')
            ->get(['manufacturer_id', 'name']);

        $pedallionCategories = PedallionCategory::orderBy('name')->get();

        return view('ext-pedallion::product-groups.form', compact(
            'group', 'mode', 'catalogCategories', 'manufacturers', 'pedallionCategories'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                   => ['required', 'string', 'max:255'],
            'catalog_category_ids'   => ['nullable', 'array'],
            'catalog_category_ids.*' => ['integer'],
            'manufacturer_ids'       => ['nullable', 'array'],
            'manufacturer_ids.*'     => ['integer'],
            'pedallion_category_id'  => ['nullable', 'integer'],
            'condition'              => ['nullable', 'string', 'max:32'],
        ]);

        $group = PedallionProductGroup::create($data);
        ActivityLogger::log('created', 'Pedallion Product Group', $group->id, $group->name);

        return redirect()->route('ext.pedallion.product-groups.index')->with('status', 'Product group created.');
    }

    public function update(Request $request, int $id)
    {
        $group = PedallionProductGroup::findOrFail($id);

        $data = $request->validate([
            'name'                   => ['required', 'string', 'max:255'],
            'catalog_category_ids'   => ['nullable', 'array'],
            'catalog_category_ids.*' => ['integer'],
            'manufacturer_ids'       => ['nullable', 'array'],
            'manufacturer_ids.*'     => ['integer'],
            'pedallion_category_id'  => ['nullable', 'integer'],
            'condition'              => ['nullable', 'string', 'max:32'],
        ]);

        $group->update($data);
        ActivityLogger::log('updated', 'Pedallion Product Group', $group->id, $group->name);

        return redirect()->route('ext.pedallion.product-groups.index')->with('status', 'Product group updated.');
    }

    public function destroy(int $id)
    {
        $group = PedallionProductGroup::findOrFail($id);
        $name = $group->name;
        $group->delete();
        ActivityLogger::log('deleted', 'Pedallion Product Group', $id, $name);

        return redirect()->route('ext.pedallion.product-groups.index')->with('status', 'Product group deleted.');
    }

    public function products(int $id)
    {
        $group = PedallionProductGroup::findOrFail($id);
        $pfx = (string) config('catalog.prefix');
        $langId = (int) config('catalog.default_language_id');

        $productIds = $this->getMatchingProductIds($group);

        $products = collect();
        if (!empty($productIds)) {
            $products = DB::table($pfx . 'product as p')
                ->join($pfx . 'product_description as pd', function ($j) use ($langId) {
                    $j->on('p.product_id', '=', 'pd.product_id')
                        ->where('pd.language_id', $langId);
                })
                ->whereIn('p.product_id', $productIds)
                ->select('p.product_id', 'pd.name', 'p.sku', 'p.model', 'p.quantity', 'p.price', 'p.status')
                ->orderBy('pd.name')
                ->get();
        }

        // Resolve names for group summary card
        $categoryNames = DB::table($pfx . 'category_description')
            ->where('language_id', $langId)
            ->whereIn('category_id', $group->catalog_category_ids ?? [])
            ->pluck('name', 'category_id');

        $manufacturerNames = DB::table($pfx . 'manufacturer')
            ->whereIn('manufacturer_id', $group->manufacturer_ids ?? [])
            ->pluck('name', 'manufacturer_id');

        $pedallionCategoryName = $group->pedallion_category_id
            ? PedallionCategory::where('pedallion_category_id', $group->pedallion_category_id)->value('name')
            : null;

        return view('ext-pedallion::product-groups.products', compact(
            'group', 'products', 'categoryNames', 'manufacturerNames', 'pedallionCategoryName'
        ));
    }

    public function syncProducts(int $id)
    {
        $group = PedallionProductGroup::findOrFail($id);
        $productIds = $this->getMatchingProductIds($group);

        $existing = PedallionProductGroupProduct::where('pedallion_product_group_id', $id)
            ->pluck('product_id')->all();

        $toInsert = array_diff($productIds, $existing);

        foreach ($toInsert as $pid) {
            PedallionProductGroupProduct::create([
                'pedallion_product_group_id' => $id,
                'product_id'                 => $pid,
            ]);
        }

        return redirect()->route('ext.pedallion.product-groups.products', $id)
            ->with('status', count($toInsert) . ' products synced to product group.');
    }

    private function getMatchingProductIds(PedallionProductGroup $group): array
    {
        $pfx = (string) config('catalog.prefix');
        $langId = (int) config('catalog.default_language_id');

        $catIds = $group->catalog_category_ids ?? [];
        $mfgIds = $group->manufacturer_ids ?? [];

        if (empty($catIds) && empty($mfgIds)) {
            return PedallionProductGroupProduct::where('pedallion_product_group_id', $group->id)
                ->pluck('product_id')->all();
        }

        $query = DB::table($pfx . 'product as p');

        if (!empty($catIds)) {
            $query->join($pfx . 'product_to_category as ptc', 'p.product_id', '=', 'ptc.product_id')
                ->whereIn('ptc.category_id', $catIds);
        }

        if (!empty($mfgIds)) {
            $query->whereIn('p.manufacturer_id', $mfgIds);
        }

        $filterIds = $query->distinct()->pluck('p.product_id')->all();

        $manualIds = PedallionProductGroupProduct::where('pedallion_product_group_id', $group->id)
            ->pluck('product_id')->all();

        return array_values(array_unique(array_merge($filterIds, $manualIds)));
    }
}
