<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TwitchEventSubService;
use DateTime;
use Illuminate\Console\Command;

class CheckEventSubStatus extends Command
{
    protected $signature = 'eventsub:check {--user-id=73327367 : Twitch user ID to check}';

    protected $description = 'Check EventSub subscription status';

    private TwitchEventSubService $eventSubService;

    public function __construct(TwitchEventSubService $eventSubService)
    {
        parent::__construct();
        $this->eventSubService = $eventSubService;
    }

    public function handle()
    {
        $twitchId = $this->option('user-id');

        // Get the user
        $user = User::where('twitch_id', $twitchId)->first();

        if (! $user) {
            $this->error("User with twitch_id {$twitchId} not found");

            return 1;
        }

        $this->info("Found user: {$user->name} (ID: {$user->id})");
        $this->info('Access token: '.($user->access_token ? 'Present' : 'Missing'));
        $this->newLine();

        // Get an app access token
        $this->info('Getting app access token...');
        $appToken = $this->eventSubService->getAppAccessToken();

        if (! $appToken) {
            $this->error('Failed to get app access token');

            return 1;
        }

        $this->info('Got app access token successfully');
        $this->newLine();

        // Check subscriptions with app token
        $this->info('Checking EventSub subscriptions with app token...');
        $subscriptions = $this->eventSubService->getSubscriptions($appToken);

        if ($subscriptions && isset($subscriptions['data'])) {
            $this->info('Total subscriptions: '.($subscriptions['total'] ?? 0));
            $this->newLine();

            $table = [];
            foreach ($subscriptions['data'] as $sub) {
                $this->line(str_repeat('━', 60));
                $this->info("Type: {$sub['type']} (v{$sub['version']})");
                $this->info("Status: {$sub['status']}");
                $this->info("ID: {$sub['id']}");

                if (isset($sub['condition'])) {
                    $this->info('Condition:');
                    foreach ($sub['condition'] as $key => $value) {
                        $this->line("  - $key: $value");
                    }
                }

                if (isset($sub['transport'])) {
                    $this->info('Transport:');
                    $this->line("  - Method: {$sub['transport']['method']}");
                    if (isset($sub['transport']['callback'])) {
                        $this->line("  - Callback URL: {$sub['transport']['callback']}");
                    }
                }

                if (isset($sub['created_at'])) {
                    $created = new DateTime($sub['created_at']);
                    $this->info('Created: '.$created->format('Y-m-d H:i:s'));
                }

                if ($sub['status'] === 'webhook_callback_verification_failed') {
                    $this->error('⚠️ WARNING: Webhook callback verification failed!');
                } elseif ($sub['status'] === 'webhook_callback_verification_pending') {
                    $this->warn('⏳ Webhook callback verification pending...');
                } elseif ($sub['status'] === 'enabled') {
                    $this->info('✅ Subscription is active and enabled');
                }
            }

            $this->line(str_repeat('━', 60));
            $this->newLine();

            // Group by status
            $statusGroups = collect($subscriptions['data'])->groupBy('status');
            $this->info('Status breakdown:');
            foreach ($statusGroups as $status => $subs) {
                $statusColor = match ($status) {
                    'enabled' => 'info',
                    'webhook_callback_verification_failed' => 'error',
                    'webhook_callback_verification_pending' => 'warn',
                    default => 'comment'
                };
                $this->$statusColor("  - $status: ".count($subs).' subscription(s)');
            }

            // Group by callback URL
            $this->newLine();
            $urlGroups = collect($subscriptions['data'])->groupBy('transport.callback');
            $this->info('Callback URLs:');
            foreach ($urlGroups as $url => $subs) {
                $this->line("  - $url: ".count($subs).' subscription(s)');

                // Check if this is a production URL
                if (str_contains($url, 'railway.app')) {
                    $this->info('    ↑ This is a production Railway URL');
                } elseif (str_contains($url, '.test')) {
                    $this->warn('    ↑ This is a local development URL');
                } elseif (str_contains($url, 'ngrok')) {
                    $this->comment('    ↑ This is an ngrok tunnel URL');
                }
            }

            // Check for specific issues
            $this->newLine(2);
            $this->info('🔍 Diagnostic Analysis:');

            // Check if any subscriptions are for the user's channel
            $userChannelSubs = collect($subscriptions['data'])->filter(function ($sub) use ($twitchId) {
                return isset($sub['condition']['broadcaster_user_id']) &&
                       $sub['condition']['broadcaster_user_id'] === $twitchId;
            });

            if ($userChannelSubs->isEmpty()) {
                $this->error("❌ No subscriptions found for user's channel (ID: $twitchId)");
            } else {
                $this->info("✅ Found {$userChannelSubs->count()} subscription(s) for user's channel");
            }

            // Check for production vs local URLs
            $productionSubs = collect($subscriptions['data'])->filter(function ($sub) {
                return isset($sub['transport']['callback']) &&
                       (str_contains($sub['transport']['callback'], 'railway.app') ||
                        ! str_contains($sub['transport']['callback'], '.test'));
            });

            $localSubs = collect($subscriptions['data'])->filter(function ($sub) {
                return isset($sub['transport']['callback']) &&
                       str_contains($sub['transport']['callback'], '.test');
            });

            if ($productionSubs->isNotEmpty() && $localSubs->isNotEmpty()) {
                $this->warn('⚠️ Mixed environment subscriptions detected!');
                $this->line("   Production: {$productionSubs->count()} | Local: {$localSubs->count()}");
            }

        } else {
            $this->error('No subscriptions found or failed to fetch');
        }

        // Also check with user token
        $this->newLine(2);
        $this->info('Checking EventSub subscriptions with user token...');
        $userSubscriptions = $this->eventSubService->getSubscriptions($user->access_token);

        if ($userSubscriptions && isset($userSubscriptions['data'])) {
            $this->info('User token subscriptions: '.($userSubscriptions['total'] ?? 0));
        } else {
            $this->error('No user token subscriptions found or failed to fetch');
        }

        // Test webhook URL accessibility
        $this->newLine(2);
        $this->line(str_repeat('━', 60));
        $this->info('Webhook Configuration:');
        $webhookUrl = config('app.url').'/api/twitch/webhook';
        $this->line("Current webhook URL: $webhookUrl");
        $this->line('Webhook Secret: '.(config('app.twitch_webhook_secret') ? 'Configured' : 'Missing'));

        // Check production URL
        $this->newLine();
        $this->info('Environment Info:');
        $this->line('APP_URL: '.config('app.url'));
        $this->line('APP_ENV: '.config('app.env'));

        if (config('app.env') === 'local') {
            $this->warn('⚠️ Running in LOCAL environment');
            $this->line('   For production events, deploy to Railway and update subscriptions');
        }

        // Provide recommendations
        $this->newLine(2);
        $this->info('📋 Recommendations:');

        if ($localSubs->isNotEmpty() && config('app.env') === 'production') {
            $this->warn('1. You have local subscriptions but are running in production');
            $this->line('   → Delete and recreate subscriptions with production URL');
        }

        if ($productionSubs->isNotEmpty() && config('app.env') === 'local') {
            $this->warn('1. You have production subscriptions but are running locally');
            $this->line('   → Use ngrok for local testing or switch to production');
        }

        $failedSubs = collect($subscriptions['data'] ?? [])->filter(function ($sub) {
            return $sub['status'] === 'webhook_callback_verification_failed';
        });

        if ($failedSubs->isNotEmpty()) {
            $this->error("2. You have {$failedSubs->count()} failed subscription(s)");
            $this->line('   → Delete these and recreate with correct webhook URL');
        }

        return 0;
    }
}
