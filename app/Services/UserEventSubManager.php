<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserEventsubSubscription;
use DateMalformedStringException;
use DateTime;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserEventSubManager
{
    private TwitchEventSubService $eventSubService;

    // Define which events we support
    private const array SUPPORTED_EVENTS = [
        'channel.follow' => [
            'version' => '2',
            'condition_keys' => ['broadcaster_user_id', 'moderator_user_id'],
        ],
        'channel.subscribe' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
        ],
        'channel.subscription.gift' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
        ],
        'channel.subscription.message' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
        ],
        'channel.raid' => [
            'version' => '1',
            'condition_keys' => ['to_broadcaster_user_id'],
        ],
        'channel.channel_points_custom_reward_redemption.add' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
        ],
        'channel.channel_points_custom_reward_redemption.update' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
        ],
        'stream.online' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
        ],
        'stream.offline' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
        ],
    ];

    public function __construct(TwitchEventSubService $eventSubService)
    {
        $this->eventSubService = $eventSubService;
    }

    /**
     * Setup all EventSub subscriptions for a user
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function setupUserSubscriptions(User $user): array
    {
        if (! $user->twitch_id || ! $user->access_token) {
            throw new Exception('User must be authenticated with Twitch');
        }

        $results = [
            'created' => [],
            'failed' => [],
            'existing' => [],
        ];

        // Get app access token (required for webhooks)
        $appToken = $this->eventSubService->getAppAccessToken();
        if (! $appToken) {
            throw new Exception('Failed to obtain app access token');
        }

        // Determine webhook URL (production vs local)
        $webhookUrl = $this->getWebhookUrl();

        Log::info("Setting up EventSub subscriptions for user {$user->id}", [
            'twitch_id' => $user->twitch_id,
            'webhook_url' => $webhookUrl,
        ]);

        foreach (self::SUPPORTED_EVENTS as $eventType => $config) {
            // Check if subscription already exists
            $existing = UserEventsubSubscription::where('user_id', $user->id)
                ->where('event_type', $eventType)
                ->where('status', 'enabled')
                ->first();

            if ($existing) {
                $results['existing'][] = $eventType;

                continue;
            }

            // Build condition based on event type
            $condition = $this->buildCondition($eventType, $user->twitch_id);

            // Create subscription with Twitch
            $payload = [
                'type' => $eventType,
                'version' => $config['version'],
                'condition' => $condition,
                'transport' => [
                    'method' => 'webhook',
                    'callback' => $webhookUrl,
                    'secret' => $user->webhook_secret ?? config('app.twitch_webhook_secret'),
                ],
            ];

            try {
                $response = $this->eventSubService->createSubscription($appToken, $payload);
            } catch (Exception $e) {
                $results['failed'][$eventType] = $e->getMessage();

                Log::warning('Failed to create EventSub subscription', [
                    'user_id' => $user->id,
                    'event_type' => $eventType,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if ($response && ! isset($response['error'])) {
                // Store in database immediately (no transaction) so the challenge
                // handler can find and update it before we finish the loop
                $subscription = UserEventsubSubscription::create([
                    'user_id' => $user->id,
                    'twitch_subscription_id' => $response['data'][0]['id'] ?? uniqid(),
                    'event_type' => $eventType,
                    'version' => $config['version'],
                    'status' => $response['data'][0]['status'] ?? 'pending',
                    'condition' => $condition,
                    'callback_url' => $webhookUrl,
                    'twitch_created_at' => isset($response['data'][0]['created_at'])
                        ? new DateTime($response['data'][0]['created_at'])
                        : now(),
                    'last_verified_at' => now(),
                ]);

                $results['created'][] = $eventType;

            } else {
                $results['failed'][$eventType] = $response['message'] ?? 'Unknown error';

                Log::warning('Failed to create EventSub subscription', [
                    'user_id' => $user->id,
                    'event_type' => $eventType,
                    'error' => $response['message'] ?? 'Unknown error',
                ]);
            }
        }

        // Reconcile local subscription status with Twitch's actual status.
        // Challenges may have completed while we were creating later subscriptions,
        // but the DB records were created before the challenge handler could update them.
        $this->verifyUserSubscriptions($user);

        // Update user's eventsub connection status
        $user->update([
            'eventsub_connected_at' => now(),
        ]);

        return $results;
    }

    /**
     * Remove all EventSub subscriptions for a user
     */
    public function removeUserSubscriptions(User $user): int
    {
        $deletedCount = 0;

        // Get app token for deletion
        $appToken = $this->eventSubService->getAppAccessToken();
        if (! $appToken) {
            Log::error('Failed to get app token for subscription cleanup');

            return 0;
        }

        // Delete local DB records
        $localSubs = UserEventsubSubscription::where('user_id', $user->id)->get();
        foreach ($localSubs as $subscription) {
            try {
                if ($this->eventSubService->deleteSubscription($appToken, $subscription->twitch_subscription_id)) {
                    $deletedCount++;
                }
            } catch (Exception $e) {
                Log::warning('Failed to delete subscription from Twitch', [
                    'subscription_id' => $subscription->twitch_subscription_id,
                    'error' => $e->getMessage(),
                ]);
            }
            $subscription->delete();
        }

        // Also clean up Twitch-side subscriptions that may not be in our DB
        $twitchSubs = $this->eventSubService->getSubscriptions($appToken);
        if ($twitchSubs && isset($twitchSubs['data'])) {
            foreach ($twitchSubs['data'] as $sub) {
                $condition = $sub['condition'] ?? [];
                $belongsToUser = ($condition['broadcaster_user_id'] ?? null) === $user->twitch_id
                    || ($condition['to_broadcaster_user_id'] ?? null) === $user->twitch_id
                    || ($condition['moderator_user_id'] ?? null) === $user->twitch_id;

                if ($belongsToUser) {
                    try {
                        if ($this->eventSubService->deleteSubscription($appToken, $sub['id'])) {
                            $deletedCount++;
                        }
                    } catch (Exception $e) {
                        Log::warning('Failed to delete Twitch-side subscription', [
                            'subscription_id' => $sub['id'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        // Clear user's connection timestamp
        $user->update([
            'eventsub_connected_at' => null,
        ]);

        Log::info('Removed EventSub subscriptions for user', [
            'user_id' => $user->id,
            'deleted_count' => $deletedCount,
        ]);

        return $deletedCount;
    }

    /**
     * Verify and refresh user's subscriptions
     */
    public function verifyUserSubscriptions(User $user): array
    {
        $appToken = $this->eventSubService->getAppAccessToken();
        if (! $appToken) {
            return ['error' => 'Failed to get app token'];
        }

        // Get all subscriptions from Twitch
        $twitchSubs = $this->eventSubService->getSubscriptions($appToken);

        if (! $twitchSubs || ! isset($twitchSubs['data'])) {
            return ['error' => 'Failed to fetch subscriptions from Twitch'];
        }

        // Create a map of Twitch subscriptions
        $twitchSubsMap = collect($twitchSubs['data'])->keyBy('id');

        // Get user's stored subscriptions
        $userSubs = UserEventsubSubscription::where('user_id', $user->id)->get();

        $status = [
            'active' => 0,
            'failed' => 0,
            'missing' => 0,
            'renewed' => 0,
        ];

        foreach ($userSubs as $userSub) {
            $twitchSub = $twitchSubsMap->get($userSub->twitch_subscription_id);

            if (! $twitchSub) {
                // Subscription not found on Twitch - mark as failed locally
                $status['missing']++;
                $userSub->update([
                    'status' => 'not_found_on_twitch',
                    'last_verified_at' => now(),
                ]);
            } elseif ($twitchSub['status'] === 'enabled') {
                $status['active']++;
                $userSub->update([
                    'status' => 'enabled',
                    'last_verified_at' => now(),
                ]);
            } else {
                $status['failed']++;
                $userSub->update([
                    'status' => $twitchSub['status'],
                    'last_verified_at' => now(),
                ]);
            }
        }

        return $status;
    }

    /**
     * Create a single subscription for a user
     * @throws DateMalformedStringException
     */
    private function createSingleSubscription(User $user, string $eventType): bool
    {
        if (! isset(self::SUPPORTED_EVENTS[$eventType])) {
            return false;
        }

        $appToken = $this->eventSubService->getAppAccessToken();
        if (! $appToken) {
            return false;
        }

        $config = self::SUPPORTED_EVENTS[$eventType];
        $condition = $this->buildCondition($eventType, $user->twitch_id);

        $payload = [
            'type' => $eventType,
            'version' => $config['version'],
            'condition' => $condition,
            'transport' => [
                'method' => 'webhook',
                'callback' => $this->getWebhookUrl(),
                'secret' => $user->webhook_secret ?? config('app.twitch_webhook_secret'),
            ],
        ];

        $response = $this->eventSubService->createSubscription($appToken, $payload);

        if ($response && ! isset($response['error'])) {
            UserEventsubSubscription::create([
                'user_id' => $user->id,
                'twitch_subscription_id' => $response['data'][0]['id'] ?? uniqid(),
                'event_type' => $eventType,
                'version' => $config['version'],
                'status' => $response['data'][0]['status'] ?? 'pending',
                'condition' => $condition,
                'callback_url' => $this->getWebhookUrl(),
                'twitch_created_at' => isset($response['data'][0]['created_at'])
                    ? new DateTime($response['data'][0]['created_at'])
                    : now(),
                'last_verified_at' => now(),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Build condition array for an event type
     */
    private function buildCondition(string $eventType, string $twitchId): array
    {
        switch ($eventType) {
            case 'channel.follow':
                return [
                    'broadcaster_user_id' => $twitchId,
                    'moderator_user_id' => $twitchId,
                ];

            case 'channel.raid':
                return [
                    'to_broadcaster_user_id' => $twitchId,
                ];

            default:
                return [
                    'broadcaster_user_id' => $twitchId,
                ];
        }
    }

    /**
     * Determine the appropriate webhook URL
     */
    private function getWebhookUrl(): string
    {
        return rtrim(config('app.url'), '/').'/api/twitch/webhook';
    }

    /**
     * Get subscription statistics for all users
     */
    public function getGlobalStats(): array
    {
        return [
            'total_users' => User::count(),
            'connected_users' => User::whereNotNull('eventsub_connected_at')->count(),
            'total_subscriptions' => UserEventsubSubscription::count(),
            'active_subscriptions' => UserEventsubSubscription::where('status', 'enabled')->count(),
            'failed_subscriptions' => UserEventsubSubscription::whereIn('status', [
                'webhook_callback_verification_failed',
                'notification_failures_exceeded',
            ])->count(),
            'subscriptions_by_type' => UserEventsubSubscription::select('event_type', DB::raw('count(*) as count'))
                ->groupBy('event_type')
                ->pluck('count', 'event_type'),
        ];
    }
}
