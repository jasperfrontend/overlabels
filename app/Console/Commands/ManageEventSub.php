<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TwitchEventSubService;
use DateTime;
use Illuminate\Console\Command;

class ManageEventSub extends Command
{
    protected $signature = 'eventsub:manage 
                            {action : create|delete|status}
                            {--user-id=73327367 : Twitch user ID}
                            {--url= : Override webhook URL (for production)}
                            {--all : Subscribe to all event types}';

    protected $description = 'Manage EventSub subscriptions';

    private TwitchEventSubService $eventSubService;

    public function __construct(TwitchEventSubService $eventSubService)
    {
        parent::__construct();
        $this->eventSubService = $eventSubService;
    }

    public function handle()
    {
        $action = $this->argument('action');
        $twitchId = $this->option('user-id');

        // Get the user
        $user = User::where('twitch_id', $twitchId)->first();

        if (! $user) {
            $this->error("User with twitch_id {$twitchId} not found");

            return 1;
        }

        $this->info("User: {$user->name} (Twitch ID: {$twitchId})");

        switch ($action) {
            case 'create':
                return $this->createSubscriptions($user);
            case 'delete':
                return $this->deleteSubscriptions($user);
            case 'status':
                return $this->showStatus($user);
            default:
                $this->error('Invalid action. Use: create, delete, or status');

                return 1;
        }
    }

    private function createSubscriptions(User $user)
    {
        // Determine webhook URL
        $webhookUrl = $this->option('url') ?: config('app.url').'/api/twitch/webhook';

        $this->info('Creating EventSub subscriptions...');
        $this->line("Webhook URL: $webhookUrl");
        $this->line('Webhook Secret: '.substr(config('app.twitch_webhook_secret'), 0, 10).'...');

        if (! $this->confirm('Proceed with subscription creation?')) {
            return 0;
        }

        // Get app access token for events that require it
        $appToken = $this->eventSubService->getAppAccessToken();
        if (! $appToken) {
            $this->error('Failed to get app access token');

            return 1;
        }

        $results = [];
        $eventTypes = [
            'channel.follow' => [
                'version' => '2',
                'token' => $appToken, // App token required
                'condition' => [
                    'broadcaster_user_id' => $user->twitch_id,
                    'moderator_user_id' => $user->twitch_id,
                ],
            ],
            'channel.subscribe' => [
                'version' => '1',
                'token' => $appToken, // App token required for webhooks
                'condition' => [
                    'broadcaster_user_id' => $user->twitch_id,
                ],
            ],
            'channel.subscription.gift' => [
                'version' => '1',
                'token' => $appToken, // App token required for webhooks
                'condition' => [
                    'broadcaster_user_id' => $user->twitch_id,
                ],
            ],
            'channel.subscription.message' => [
                'version' => '1',
                'token' => $appToken, // App token required for webhooks
                'condition' => [
                    'broadcaster_user_id' => $user->twitch_id,
                ],
            ],
            'channel.raid' => [
                'version' => '1',
                'token' => $appToken, // App token required for webhooks
                'condition' => [
                    'to_broadcaster_user_id' => $user->twitch_id,
                ],
            ],
            'channel.channel_points_custom_reward_redemption.add' => [
                'version' => '1',
                'token' => $appToken, // App token required for webhooks
                'condition' => [
                    'broadcaster_user_id' => $user->twitch_id,
                ],
            ],
            'channel.channel_points_custom_reward_redemption.update' => [
                'version' => '1',
                'token' => $appToken, // App token required for webhooks
                'condition' => [
                    'broadcaster_user_id' => $user->twitch_id,
                ],
            ],
            'stream.online' => [
                'version' => '1',
                'token' => $appToken, // App token required for webhooks
                'condition' => [
                    'broadcaster_user_id' => $user->twitch_id,
                ],
            ],
            'stream.offline' => [
                'version' => '1',
                'token' => $appToken, // App token required for webhooks
                'condition' => [
                    'broadcaster_user_id' => $user->twitch_id,
                ],
            ],
        ];

        // If not --all, ask which events to subscribe to
        if (! $this->option('all')) {
            $selectedEvents = $this->choice(
                'Which events do you want to subscribe to?',
                array_keys($eventTypes),
                null,
                null,
                true
            );

            $eventTypes = array_intersect_key($eventTypes, array_flip($selectedEvents));
        }

        foreach ($eventTypes as $type => $config) {
            $this->line("\nSubscribing to: $type");

            $payload = [
                'type' => $type,
                'version' => $config['version'],
                'condition' => $config['condition'],
                'transport' => [
                    'method' => 'webhook',
                    'callback' => $webhookUrl,
                    'secret' => config('app.twitch_webhook_secret'),
                ],
            ];

            $result = $this->eventSubService->createSubscription($config['token'], $payload);

            if ($result && ! isset($result['error'])) {
                $this->info('  ✅ Success');
                $results[$type] = 'success';
            } else {
                $this->error('  ❌ Failed: '.($result['message'] ?? 'Unknown error'));
                $results[$type] = 'failed';

                // Show more details for debugging
                if (isset($result['message'])) {
                    $this->comment('     Response: '.json_encode($result['message']));
                }
            }
        }

        // Summary
        $this->newLine();
        $this->info('Summary:');
        $successful = collect($results)->filter(fn ($r) => $r === 'success')->count();
        $failed = collect($results)->filter(fn ($r) => $r === 'failed')->count();

        $this->line("  Successful: $successful");
        if ($failed > 0) {
            $this->error("  Failed: $failed");
        }

        return $failed > 0 ? 1 : 0;
    }

    private function deleteSubscriptions(User $user)
    {
        $this->info('Fetching all subscriptions...');

        // Get app token to check all subscriptions
        $appToken = $this->eventSubService->getAppAccessToken();
        if (! $appToken) {
            $this->error('Failed to get app access token');

            return 1;
        }

        // Get all subscriptions
        $subscriptions = $this->eventSubService->getSubscriptions($appToken);

        if (! $subscriptions || empty($subscriptions['data'])) {
            $this->info('No subscriptions found to delete');

            return 0;
        }

        $count = count($subscriptions['data']);

        if (! $this->confirm("Delete all $count subscription(s)?")) {
            return 0;
        }

        $deleted = 0;
        foreach ($subscriptions['data'] as $sub) {
            $this->line("Deleting: {$sub['type']} ({$sub['id']})");

            if ($this->eventSubService->deleteSubscription($appToken, $sub['id'])) {
                $this->info('  ✅ Deleted');
                $deleted++;
            } else {
                $this->error('  ❌ Failed');
            }
        }

        $this->newLine();
        $this->info("Deleted $deleted of $count subscriptions");

        return 0;
    }

    private function showStatus(User $user)
    {
        $this->info('Fetching subscription status...');

        // Get app token
        $appToken = $this->eventSubService->getAppAccessToken();
        if (! $appToken) {
            $this->error('Failed to get app access token');

            return 1;
        }

        $subscriptions = $this->eventSubService->getSubscriptions($appToken);

        if (! $subscriptions || empty($subscriptions['data'])) {
            $this->warn('No active subscriptions found');

            return 0;
        }

        $this->info('Total subscriptions: '.count($subscriptions['data']));
        $this->newLine();

        $headers = ['Type', 'Status', 'URL', 'Created'];
        $rows = [];

        foreach ($subscriptions['data'] as $sub) {
            $url = $sub['transport']['callback'] ?? 'N/A';
            $urlShort = str_contains($url, 'railway') ? '🚂 Railway' :
                       (str_contains($url, '.test') ? '🏠 Local' :
                       (str_contains($url, 'ngrok') ? '🌐 Ngrok' : '❓ Other'));

            $created = isset($sub['created_at'])
                ? (new DateTime($sub['created_at']))->format('Y-m-d H:i')
                : 'Unknown';

            $status = match ($sub['status']) {
                'enabled' => '✅ Enabled',
                'webhook_callback_verification_failed' => '❌ Failed',
                'webhook_callback_verification_pending' => '⏳ Pending',
                default => $sub['status']
            };

            $rows[] = [
                $sub['type'],
                $status,
                $urlShort,
                $created,
            ];
        }

        $this->table($headers, $rows);

        // Group by status
        $statusGroups = collect($subscriptions['data'])->groupBy('status');
        $this->newLine();
        $this->info('Status Summary:');
        foreach ($statusGroups as $status => $subs) {
            $this->line("  $status: ".count($subs));
        }

        return 0;
    }
}
