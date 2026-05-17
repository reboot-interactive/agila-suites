<?php

namespace Extensions\pedallion\Controllers;

use App\Http\Controllers\Controller;

use Extensions\pedallion\Models\PedallionApiLog;
use Extensions\pedallion\Models\PedallionOrderStatusMap;
use Extensions\pedallion\Models\PedallionSetting;
use App\Services\ActivityLogger;
use Extensions\pedallion\Services\Pedallion\PedallionClient;
use Illuminate\Http\Request;

class PedallionController extends Controller
{
    public function index()
    {
        $setting = PedallionSetting::query()->first() ?? new PedallionSetting();

        $logs = PedallionApiLog::query()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('ext-pedallion::index', compact('setting', 'logs'));
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'base_url' => ['required', 'string', 'max:255'],
            'api_key'  => ['required', 'string', 'max:255'],
            'enabled'  => ['nullable'],
        ]);

        $setting = PedallionSetting::query()->first();

        $attrs = [
            'base_url' => $data['base_url'],
            'api_key'  => $data['api_key'],
            'enabled'  => $request->has('enabled'),
        ];

        if ($setting) {
            $setting->update($attrs);
            ActivityLogger::log('updated', 'Pedallion Settings', $setting->id, 'Settings saved');
        } else {
            $setting = PedallionSetting::create($attrs);
            ActivityLogger::log('created', 'Pedallion Settings', $setting->id, 'Initial setup');
        }

        return redirect()->route('ext.pedallion.index')->with('status', 'Pedallion settings saved.');
    }

    public function saveSyncDays(Request $request)
    {
        $request->validate([
            'sync_last_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $setting = PedallionSetting::query()->firstOrFail();
        $setting->update(['sync_last_days' => $request->input('sync_last_days')]);

        return redirect()->route('ext.pedallion.index')
            ->with('status', "Sync window set to last {$request->input('sync_last_days')} days.");
    }

    public function testConnection(Request $request)
    {
        $baseUrl = $request->input('base_url');
        $apiKey  = $request->input('api_key');

        if ($baseUrl && $apiKey) {
            $setting = new PedallionSetting(['base_url' => $baseUrl, 'enabled' => true]);
            $setting->setRawApiKey($apiKey);
        } else {
            $setting = PedallionSetting::query()->first();
        }

        if (!$setting) {
            return response()->json(['ok' => false, 'error' => 'Not configured']);
        }

        $client = new PedallionClient($setting);
        $result = $client->ping();

        return response()->json($result);
    }

    public function toggleLogging(Request $request)
    {
        $setting = PedallionSetting::query()->firstOrFail();
        $setting->update(['logging_enabled' => (bool) $request->input('logging_enabled', false)]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'logging_enabled' => $setting->logging_enabled]);
        }

        $state = $setting->logging_enabled ? 'enabled' : 'disabled';
        return redirect()->route('ext.pedallion.index', ['tab' => 'logs'])->with('status', "API logging {$state}.");
    }

    public function clearApiLogs()
    {
        PedallionApiLog::query()->delete();
        return redirect()->route('ext.pedallion.index', ['tab' => 'logs'])->with('status', 'API logs cleared.');
    }

    public function explorerRun(Request $request)
    {
        $request->validate([
            'method' => ['required', 'in:GET,POST,PUT,PATCH,DELETE'],
            'path'   => ['required', 'string', 'max:512'],
        ]);

        $setting = PedallionSetting::query()->first();
        if (!$setting || !$setting->api_key) {
            return response()->json(['ok' => false, 'error' => 'Pedallion not configured']);
        }

        $client = new PedallionClient($setting);
        $method = strtolower($request->input('method'));
        $path   = ltrim($request->input('path'), '/');
        $body   = json_decode($request->input('body', '{}'), true) ?: [];

        $result = match ($method) {
            'get'    => $client->get($path, $body),
            'post'   => $client->post($path, $body),
            'put'    => $client->put($path, $body),
            'patch'  => $client->patch($path, $body),
            'delete' => $client->delete($path),
        };

        return response()->json($result);
    }

    public function saveOrderStatusMap(Request $request)
    {
        $map = $request->input('map', []);

        foreach ($map as $slug => $erpStatusId) {
            PedallionOrderStatusMap::updateOrCreate(
                [
                    'pedallion_status' => $slug,
                    'context'          => 'order',
                ],
                [
                    'order_status_id' => (int) $erpStatusId,
                ]
            );
        }

        return redirect()->route('ext.pedallion.categories.index', ['tab' => 'status'])->with('status', 'Order status mapping saved.');
    }

    public function fetchOrderStatuses()
    {
        $setting = PedallionSetting::query()->first();
        if (!$setting || !$setting->api_key) {
            return redirect()->route('ext.pedallion.categories.index', ['tab' => 'status'])->with('error', 'Pedallion not configured.');
        }

        $client = new PedallionClient($setting);
        $result = $client->getReferences();

        if (!$result['ok']) {
            return redirect()->route('ext.pedallion.categories.index', ['tab' => 'status'])
                ->with('error', 'Failed to fetch references: ' . ($result['body']['message'] ?? 'Unknown error'));
        }

        $statuses = $result['body']['order_statuses'] ?? $result['body']['data']['order_statuses'] ?? [];

        if (empty($statuses)) {
            return redirect()->route('ext.pedallion.categories.index', ['tab' => 'status'])
                ->with('error', 'No order statuses found in references response.');
        }

        $count = 0;
        foreach ($statuses as $status) {
            $slug = is_array($status) ? ($status['slug'] ?? $status['value'] ?? '') : (string) $status;
            $name = is_array($status) ? ($status['name'] ?? $status['label'] ?? $slug) : $slug;
            if ($slug === '') continue;

            $inserted = PedallionOrderStatusMap::firstOrCreate(
                ['pedallion_status' => $slug, 'context' => 'order'],
                ['order_status_id' => 0, 'pedallion_status_label' => $name]
            );

            // Update label if it changed
            if (!$inserted->wasRecentlyCreated && ($inserted->pedallion_status_label ?? '') !== $name) {
                $inserted->update(['pedallion_status_label' => $name]);
            }

            $count++;
        }

        return redirect()->route('ext.pedallion.categories.index', ['tab' => 'status'])
            ->with('status', "Fetched {$count} order statuses from Pedallion.");
    }
}
