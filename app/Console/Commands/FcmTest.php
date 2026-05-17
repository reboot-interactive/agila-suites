<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Services\FcmService;
use Illuminate\Console\Command;

class FcmTest extends Command
{
    protected $signature = 'fcm:test {--token= : Send to a specific FCM token} {--type=confirmed : Notification type: confirmed or cancelled}';
    protected $description = 'Test FCM push notification';

    public function handle(): int
    {
        $fcm = new FcmService();

        // Step 1: Verify credentials
        $this->info('1. Checking Firebase credentials...');
        $credsPath = config('services.firebase.credentials', storage_path('app/firebase-credentials.json'));

        if (! file_exists($credsPath)) {
            $this->error("   Missing: {$credsPath}");
            return 1;
        }

        $creds = json_decode(file_get_contents($credsPath), true);
        if (! isset($creds['project_id'], $creds['client_email'], $creds['private_key'])) {
            $this->error('   Invalid credentials file — missing project_id, client_email, or private_key');
            return 1;
        }

        $this->info("   OK — project: {$creds['project_id']}, email: {$creds['client_email']}");

        // Step 2: Check registered tokens
        $this->info('2. Checking registered device tokens...');
        $tokens = DeviceToken::with('user')->get();

        if ($tokens->isEmpty() && ! $this->option('token')) {
            $this->warn('   No device tokens registered yet. Log in from the mobile app first,');
            $this->warn('   or use --token=<fcm_token> to send to a specific token.');
            return 0;
        }

        foreach ($tokens as $dt) {
            $this->line("   - {$dt->user->name} ({$dt->platform}) — ...".substr($dt->token, -12));
        }

        // Step 3: Send test notification
        $type = $this->option('type') === 'cancelled' ? 'order_cancelled' : 'order_confirmed';
        $status = $type === 'order_cancelled' ? 'Cancelled' : 'Pending';
        $this->info("3. Sending test {$type} notification...");

        $data = [
            'type'      => $type,
            'order_id'  => '999',
            'item_name' => 'Sample Product',
            'total'     => '1,234.56 PHP',
            'source'    => 'lazada',
            'status'    => $status,
        ];

        $specificToken = $this->option('token');

        if ($specificToken) {
            $result = $fcm->sendData($specificToken, $data);
            $this->info($result ? '   Sent successfully!' : '   Failed — check laravel.log');
        } else {
            foreach ($tokens as $dt) {
                $fcm->sendData($dt->token, $data);
            }
            $this->info("   Sent to {$tokens->count()} device(s). Check your phone!");
        }

        return 0;
    }
}
