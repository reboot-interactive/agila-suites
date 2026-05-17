<?php

namespace Extensions\pedallion;

use App\Extensions\ExtensionProvider;
use App\Integrations\Dto\RecentOrder;
use App\Integrations\Dto\TopProduct;
use App\Integrations\IntegrationCard;
use App\Integrations\IntegrationProvider;
use App\Integrations\IntegrationRegistry;
use App\Integrations\MenuItem;
use App\Integrations\OrderTab;
use Extensions\pedallion\Models\PedallionOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PedallionExtension extends ExtensionProvider implements IntegrationProvider
{
    protected string $id = 'pedallion';

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Extensions\pedallion\Commands\PedallionSyncOrders::class,
                \Extensions\pedallion\Commands\PedallionPushStock::class,
            ]);
        }

        $this->app->make(IntegrationRegistry::class)->register($this);
    }

    public function integrationId(): string
    {
        return $this->id;
    }

    public function integrationCards(): array
    {
        return [
            new IntegrationCard(
                id: $this->id,
                name: 'Pedallion',
                tagline: 'Pedallion distributor API — products, product groups, orders.',
                icon: 'truck',
                accent: '#0d9488',
                permission: 'manage_pedallion',
                menu: [
                    new MenuItem('Settings', 'ext.pedallion.index', 'manage_pedallion'),
                    new MenuItem('Reference Data', 'ext.pedallion.categories.index', 'manage_pedallion'),
                    new MenuItem('Product Groups', 'ext.pedallion.product-groups.index', 'manage_pedallion'),
                    new MenuItem('Products', 'ext.pedallion.products.index', 'manage_pedallion'),
                    new MenuItem('Orders', 'ext.pedallion.orders.index', 'manage_pedallion_orders'),
                ],
            ),
        ];
    }

    public function orderTabs(): array
    {
        return [
            new OrderTab(
                id: $this->id,
                label: 'Pedallion',
                icon: 'truck',
                accent: '#0d9488',
                routeName: 'ext.pedallion.orders.index',
                permission: 'manage_pedallion_orders',
                unprocessedCounter: function () {
                    if (!Schema::hasTable('pedallion_orders')) {
                        return 0;
                    }
                    return PedallionOrder::query()
                        ->whereIn('status', ['pending', 'processing', 'awaiting_shipment'])
                        ->count();
                },
                dailyOrdersCounter: function () {
                    if (!Schema::hasTable('pedallion_orders')) {
                        return 0;
                    }
                    return PedallionOrder::query()
                        ->where('order_date', '>=', now()->startOfDay())
                        ->count();
                },
                dailyRevenueCounter: function () {
                    if (!Schema::hasTable('pedallion_orders')) {
                        return 0.0;
                    }
                    return (float) PedallionOrder::query()
                        ->where('order_date', '>=', now()->startOfDay())
                        ->sum('total');
                },
                topProductsCallback: function (int $limit) {
                    if (!Schema::hasTable('pedallion_orders') || !Schema::hasTable('pedallion_order_products')) {
                        return [];
                    }
                    return DB::table('pedallion_order_products as p')
                        ->join('pedallion_orders as o', 'o.id', '=', 'p.pedallion_order_id')
                        ->where('o.order_date', '>=', now()->startOfDay())
                        ->whereNotNull('p.pedallion_sku')
                        ->where('p.pedallion_sku', '!=', '')
                        ->groupBy('p.pedallion_sku')
                        ->orderByDesc('qty_sold')
                        ->limit($limit)
                        ->select(
                            'p.pedallion_sku as sku',
                            DB::raw('MAX(p.product_name) as name'),
                            DB::raw('SUM(p.quantity) as qty_sold'),
                            DB::raw('SUM(p.quantity * p.price) as revenue'),
                        )
                        ->get()
                        ->map(fn ($r) => new TopProduct(
                            sku: (string) $r->sku,
                            name: (string) ($r->name ?? $r->sku),
                            imageUrl: null,
                            qtySold: (int) $r->qty_sold,
                            revenue: (float) $r->revenue,
                        ))->all();
                },
                recentOrdersCallback: function (int $limit) {
                    if (!Schema::hasTable('pedallion_orders')) {
                        return [];
                    }
                    return PedallionOrder::query()
                        ->orderByDesc('order_date')
                        ->limit($limit)
                        ->get(['id', 'order_number', 'status', 'buyer_name', 'total', 'order_date'])
                        ->map(fn ($o) => new RecentOrder(
                            reference: (string) $o->order_number,
                            customerName: $o->buyer_name,
                            total: (float) ($o->total ?? 0),
                            statusLabel: (string) $o->status,
                            orderedAt: $o->order_date,
                            url: route('ext.pedallion.orders.show', $o->id),
                        ))->all();
                },
            ),
        ];
    }
}
