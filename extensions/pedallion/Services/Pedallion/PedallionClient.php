<?php

namespace Extensions\pedallion\Services\Pedallion;

use Extensions\pedallion\Models\PedallionApiLog;
use Extensions\pedallion\Models\PedallionSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PedallionClient
{
    private PedallionSetting $setting;

    public function __construct(PedallionSetting $setting)
    {
        $this->setting = $setting;
    }

    private function http(): PendingRequest
    {
        return Http::timeout(30)
            ->retry(2, 300, function (\Exception $e, \Illuminate\Http\Client\PendingRequest $request) {
                // Only retry on server errors (5xx) or connection issues, not client errors (4xx)
                if ($e instanceof \Illuminate\Http\Client\RequestException) {
                    return $e->response->serverError();
                }
                return true; // retry connection errors
            }, throw: false)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->setting->api_key,
                'Accept'        => 'application/json',
            ]);
    }

    private function url(string $path): string
    {
        return rtrim($this->setting->base_url, '/') . '/' . ltrim($path, '/');
    }

    // ─── Core HTTP methods ─────────────────────────────────

    public function get(string $path, array $params = []): array
    {
        $start = microtime(true);
        try {
            $resp = $this->http()->get($this->url($path), $params);
            $result = [
                'status' => $resp->status(),
                'ok'     => $resp->successful(),
                'body'   => $resp->json() ?? [],
            ];
        } catch (\Throwable $e) {
            Log::error('Pedallion API GET failed', ['path' => $path, 'error' => $e->getMessage()]);
            $result = [
                'status' => 0,
                'ok'     => false,
                'body'   => ['error' => $e->getMessage()],
            ];
        }
        $this->logRequest('GET', $path, $params, $result, $start);
        return $result;
    }

    public function post(string $path, array $data = []): array
    {
        $start = microtime(true);
        try {
            $resp = $this->http()->asJson()->post($this->url($path), $data);
            $result = [
                'status' => $resp->status(),
                'ok'     => $resp->successful(),
                'body'   => $resp->json() ?? [],
            ];
        } catch (\Throwable $e) {
            Log::error('Pedallion API POST failed', ['path' => $path, 'error' => $e->getMessage()]);
            $result = [
                'status' => 0,
                'ok'     => false,
                'body'   => ['error' => $e->getMessage()],
            ];
        }
        $this->logRequest('POST', $path, $data, $result, $start);
        return $result;
    }

    public function put(string $path, array $data = []): array
    {
        $start = microtime(true);
        try {
            $resp = $this->http()->asJson()->put($this->url($path), $data);
            $result = [
                'status' => $resp->status(),
                'ok'     => $resp->successful(),
                'body'   => $resp->json() ?? [],
            ];
        } catch (\Throwable $e) {
            Log::error('Pedallion API PUT failed', ['path' => $path, 'error' => $e->getMessage()]);
            $result = [
                'status' => 0,
                'ok'     => false,
                'body'   => ['error' => $e->getMessage()],
            ];
        }
        $this->logRequest('PUT', $path, $data, $result, $start);
        return $result;
    }

    public function patch(string $path, array $data = []): array
    {
        $start = microtime(true);
        try {
            $resp = $this->http()->asJson()->patch($this->url($path), $data);
            $result = [
                'status' => $resp->status(),
                'ok'     => $resp->successful(),
                'body'   => $resp->json() ?? [],
            ];
        } catch (\Throwable $e) {
            Log::error('Pedallion API PATCH failed', ['path' => $path, 'error' => $e->getMessage()]);
            $result = [
                'status' => 0,
                'ok'     => false,
                'body'   => ['error' => $e->getMessage()],
            ];
        }
        $this->logRequest('PATCH', $path, $data, $result, $start);
        return $result;
    }

    public function delete(string $path): array
    {
        $start = microtime(true);
        try {
            $resp = $this->http()->delete($this->url($path));
            $result = [
                'status' => $resp->status(),
                'ok'     => $resp->successful(),
                'body'   => $resp->json() ?? [],
            ];
        } catch (\Throwable $e) {
            Log::error('Pedallion API DELETE failed', ['path' => $path, 'error' => $e->getMessage()]);
            $result = [
                'status' => 0,
                'ok'     => false,
                'body'   => ['error' => $e->getMessage()],
            ];
        }
        $this->logRequest('DELETE', $path, [], $result, $start);
        return $result;
    }

    // ─── API Logging ───────────────────────────────────────

    private function logRequest(string $method, string $path, array $requestBody, array $result, float $start): void
    {
        if (!$this->setting->logging_enabled) {
            return;
        }

        try {
            PedallionApiLog::create([
                'method'        => $method,
                'endpoint'      => $path,
                'status_code'   => $result['status'],
                'request_body'  => $requestBody ?: null,
                'response_body' => $result['body'] ?: null,
                'duration_ms'   => (int) round((microtime(true) - $start) * 1000),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log Pedallion API request', ['error' => $e->getMessage()]);
        }
    }

    // ─── Convenience Methods ───────────────────────────────

    /** Test connectivity */
    public function ping(): array
    {
        return $this->get('categories', ['per_page' => 1]);
    }

    // ── Reference Data ──

    public function getCategories(int $page = 1, int $perPage = 100): array
    {
        return $this->get('categories', ['page' => $page, 'per_page' => $perPage]);
    }

    public function getCategoryTree(): array
    {
        return $this->get('categories/tree');
    }

    public function getManufacturers(int $page = 1, int $perPage = 100): array
    {
        return $this->get('manufacturers', ['page' => $page, 'per_page' => $perPage]);
    }

    public function getReferences(): array
    {
        return $this->get('references');
    }

    // ── Products ──

    public function getProducts(int $page = 1, int $perPage = 100, ?string $status = null): array
    {
        $params = ['page' => $page, 'per_page' => $perPage];
        if ($status) {
            $params['status'] = $status;
        }
        return $this->get('products', $params);
    }

    public function getProduct(string $sku): array
    {
        return $this->get("products/{$sku}");
    }

    public function createProduct(array $data): array
    {
        return $this->post('products', $data);
    }

    public function updateProduct(string $sku, array $data): array
    {
        return $this->put("products/{$sku}", $data);
    }

    public function deleteProduct(string $sku): array
    {
        return $this->delete("products/{$sku}");
    }

    public function batchSync(array $products): array
    {
        return $this->post('products/sync', ['products' => $products]);
    }

    // ── Stock ──

    public function updateStock(string $sku, int $quantity): array
    {
        return $this->patch("products/{$sku}/stock", [
            'quantity' => $quantity,
        ]);
    }

    public function batchUpdateStock(array $items): array
    {
        return $this->post('stock/batch', ['items' => $items]);
    }

    public function updateVariantStock(string $sku, string $variantSku, int $quantity): array
    {
        return $this->patch("products/{$sku}/variants/{$variantSku}/stock", [
            'quantity' => $quantity,
        ]);
    }

    public function batchUpdateVariantStock(array $items): array
    {
        return $this->post('variants/stock/batch', ['items' => $items]);
    }

    // ── Images ──

    public function uploadImages(string $sku, array $imageUrls): array
    {
        return $this->post("products/{$sku}/images", ['image_urls' => $imageUrls]);
    }

    public function deleteImage(string $sku, int $imageId): array
    {
        return $this->delete("products/{$sku}/images/{$imageId}");
    }

    // ── Orders ──

    public function getOrders(int $page = 1, int $perPage = 100, ?string $status = null, ?string $since = null, ?string $until = null): array
    {
        $params = ['page' => $page, 'per_page' => $perPage];
        if ($status) $params['status'] = $status;
        if ($since)  $params['created_after'] = $since;
        if ($until)  $params['created_before'] = $until;
        return $this->get('orders', $params);
    }

    public function getOrder(string $orderNumber): array
    {
        return $this->get("orders/{$orderNumber}");
    }
}
