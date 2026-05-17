<?php

namespace App\Services;

use App\Models\Catalog\OrderStatus;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    private ?array $serviceAccount = null;

    /** Status names that trigger "confirmed" notification */
    private const CONFIRMED_STATUSES = ['Pending', 'Unpaid', 'Processing'];

    /** Status names that trigger "cancelled" notification */
    private const CANCELLED_STATUSES = ['Cancelled', 'Denied', 'Reversed', 'Failed'];

    /**
     * Check a status transition and send the appropriate notification.
     * Call this wherever order status changes (creation, sync, manual update).
     */
    public function notifyIfNeeded(
        int $orderId,
        int $oldStatusId,
        int $newStatusId,
        string $itemName,
        string $total,
        string $source = ''
    ): void {
        if ($oldStatusId === $newStatusId) {
            return;
        }

        $newStatusName = $this->getStatusName($newStatusId);
        if (! $newStatusName) {
            return;
        }

        $type = null;
        if (in_array($newStatusName, self::CONFIRMED_STATUSES, true)) {
            $type = 'order_confirmed';
        } elseif (in_array($newStatusName, self::CANCELLED_STATUSES, true)) {
            $type = 'order_cancelled';
        }

        if (! $type) {
            return;
        }

        $tokens = $this->getTokensForPermission('manage_orders');
        if (empty($tokens)) {
            return;
        }

        $data = [
            'type'      => $type,
            'order_id'  => (string) $orderId,
            'item_name' => $itemName,
            'total'     => $total,
            'source'    => $source,
            'status'    => $newStatusName,
        ];

        foreach ($tokens as $token) {
            $this->sendData($token, $data);
        }
    }

    /**
     * Send a data-only FCM message (handled by the app's FirebaseMessagingService).
     */
    public function sendData(string $deviceToken, array $data): bool
    {
        $projectId = $this->getProjectId();
        if (! $projectId) {
            return false;
        }

        $accessToken = $this->getAccessToken();
        if (! $accessToken) {
            return false;
        }

        $payload = [
            'message' => [
                'token' => $deviceToken,
                'data'  => $data,
            ],
        ];

        try {
            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

            if ($response->failed()) {
                $body = $response->json();
                $errorCode = $body['error']['details'][0]['errorCode'] ?? '';

                if (in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT'])) {
                    DeviceToken::where('token', $deviceToken)->delete();
                    Log::info("FCM: Removed stale device token");
                } else {
                    Log::warning("FCM send failed: " . $response->body());
                }

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error("FCM send exception: " . $e->getMessage());
            return false;
        }
    }

    private function getStatusName(int $statusId): ?string
    {
        return Cache::remember("order_status_name_{$statusId}", 3600, function () use ($statusId) {
            return OrderStatus::where('order_status_id', $statusId)->value('name');
        });
    }

    private function getTokensForPermission(string $permission): array
    {
        $userIds = User::all()->filter(function (User $user) use ($permission) {
            return $user->hasPermission($permission);
        })->pluck('id');

        return DeviceToken::whereIn('user_id', $userIds)
            ->pluck('token')
            ->toArray();
    }

    private function getServiceAccount(): ?array
    {
        if ($this->serviceAccount !== null) {
            return $this->serviceAccount;
        }

        $path = config('services.firebase.credentials', storage_path('app/firebase-credentials.json'));

        if (! file_exists($path)) {
            Log::error("FCM: Firebase credentials not found at {$path}");
            return null;
        }

        $this->serviceAccount = json_decode(file_get_contents($path), true);

        return $this->serviceAccount;
    }

    private function getProjectId(): ?string
    {
        $sa = $this->getServiceAccount();

        return $sa['project_id'] ?? null;
    }

    private function getAccessToken(): ?string
    {
        return Cache::remember('fcm_access_token', 3000, function () {
            $sa = $this->getServiceAccount();
            if (! $sa) {
                return null;
            }

            $jwt = $this->createJwt($sa);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            if ($response->failed()) {
                Log::error("FCM: OAuth token request failed: " . $response->body());
                return null;
            }

            return $response->json('access_token');
        });
    }

    private function createJwt(array $sa): string
    {
        $now = time();

        $header = $this->base64url(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ]));

        $payload = $this->base64url(json_encode([
            'iss'   => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));

        $unsigned = "{$header}.{$payload}";

        openssl_sign($unsigned, $signature, $sa['private_key'], OPENSSL_ALGO_SHA256);

        return $unsigned . '.' . $this->base64url($signature);
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
