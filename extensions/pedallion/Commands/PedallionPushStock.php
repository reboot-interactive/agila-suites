<?php

namespace Extensions\pedallion\Commands;

use Extensions\pedallion\Models\PedallionProductLink;
use Extensions\pedallion\Models\PedallionSetting;
use Extensions\pedallion\Services\Pedallion\PedallionClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PedallionPushStock extends Command
{
    protected $signature = 'pedallion:push-stock';
    protected $description = 'Push inventory quantities to Pedallion for all linked products';

    public function handle(): int
    {
        $setting = PedallionSetting::query()->first();

        if (!$setting || !$setting->enabled || !$setting->api_key) {
            $this->error('Pedallion is not configured or disabled.');
            return 1;
        }

        $client = new PedallionClient($setting);
        $pfx = (string) config('catalog.prefix');

        $links = PedallionProductLink::all();

        if ($links->isEmpty()) {
            $this->info('No linked products to push.');
            return 0;
        }

        $productIds = $links->pluck('product_id')->unique()->all();
        $quantities = DB::table($pfx . 'product')
            ->whereIn('product_id', $productIds)
            ->pluck('quantity', 'product_id');

        $items = [];
        $pushed = 0;
        $failed = 0;

        foreach ($links as $link) {
            $qty = (int) ($quantities[$link->product_id] ?? 0);
            $items[] = ['sku' => $link->pedallion_sku, 'stock_quantity' => $qty];

            if (count($items) >= 100) {
                $result = $client->batchUpdateStock($items);
                if ($result['ok']) {
                    $pushed += count($items);
                } else {
                    $failed += count($items);
                }
                $items = [];
            }
        }

        if (!empty($items)) {
            $result = $client->batchUpdateStock($items);
            if ($result['ok']) {
                $pushed += count($items);
            } else {
                $failed += count($items);
            }
        }

        $setting->update(['last_stock_push_at' => now()]);

        $this->info("Stock push complete. {$pushed} pushed, {$failed} failed.");

        return $failed > 0 ? 1 : 0;
    }
}
