<?php

namespace Extensions\pedallion\Commands;

use Extensions\pedallion\Models\PedallionOrder;
use Extensions\pedallion\Models\PedallionOrderProduct;
use Extensions\pedallion\Models\PedallionProductLink;
use Extensions\pedallion\Models\PedallionSetting;
use Extensions\pedallion\Services\Pedallion\PedallionClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PedallionSyncOrders extends Command
{
    protected $signature = 'pedallion:sync-orders {--days= : Override sync window}';
    protected $description = 'Fetch orders from Pedallion marketplace';

    public function handle(): int
    {
        $setting = PedallionSetting::query()->first();

        if (!$setting || !$setting->enabled || !$setting->api_key) {
            $this->error('Pedallion is not configured or disabled.');
            return 1;
        }

        $days = $this->option('days') ?? $setting->sync_last_days ?? 14;
        $since = now()->subDays((int) $days)->format('Y-m-d');

        $client = new PedallionClient($setting);
        $page = 1;
        $totalSynced = 0;

        $skuMap = PedallionProductLink::pluck('product_id', 'pedallion_sku')->all();

        do {
            $result = $client->getOrders($page, 100, null, $since);

            if (!$result['ok']) {
                $this->error('API error: ' . ($result['body']['message'] ?? 'Unknown'));
                break;
            }

            $orders = $result['body']['data'] ?? [];

            if (empty($orders)) break;

            foreach ($orders as $o) {
                DB::transaction(function () use ($o, $skuMap, &$totalSynced) {
                    $order = PedallionOrder::updateOrCreate(
                        ['order_number' => $o['order_number'] ?? $o['id'] ?? ''],
                        [
                            'status'           => $o['status'] ?? null,
                            'total'            => (float) ($o['total'] ?? 0),
                            'currency'         => $o['currency'] ?? 'PHP',
                            'buyer_name'       => $o['buyer_name'] ?? $o['customer_name'] ?? null,
                            'shipping_address' => is_array($o['shipping_address'] ?? null)
                                ? json_encode($o['shipping_address'])
                                : ($o['shipping_address'] ?? null),
                            'order_date'       => $o['created_at'] ?? $o['order_date'] ?? null,
                            'paid_at'          => $o['paid_at'] ?? null,
                            'shipped_at'       => $o['shipped_at'] ?? null,
                            'raw_payload'      => $o,
                        ]
                    );

                    // Sync line items
                    $items = $o['items'] ?? $o['products'] ?? $o['line_items'] ?? [];
                    if (!empty($items)) {
                        $order->products()->delete();
                        foreach ($items as $item) {
                            $sku = $item['sku'] ?? '';
                            PedallionOrderProduct::create([
                                'pedallion_order_id' => $order->id,
                                'pedallion_sku'      => $sku,
                                'product_name'       => $item['name'] ?? $item['product_name'] ?? '',
                                'quantity'           => (int) ($item['quantity'] ?? 1),
                                'price'              => (float) ($item['price'] ?? 0),
                                'total'              => (float) ($item['total'] ?? ($item['price'] ?? 0) * ($item['quantity'] ?? 1)),
                                'product_id'         => $skuMap[$sku] ?? null,
                            ]);
                        }
                    }

                    $totalSynced++;
                });
            }

            $page++;
            $lastPage = $result['body']['meta']['last_page'] ?? $result['body']['last_page'] ?? $page;
        } while ($page <= $lastPage);

        $setting->update(['last_order_sync_at' => now()]);

        $this->info("Pedallion order sync complete. {$totalSynced} orders synced.");

        return 0;
    }
}
