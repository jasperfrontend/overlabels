<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserEventsubSubscription;
use DateMalformedStringException;
use DateTime;
use Exception;
use Illuminate\Support\Facades\Log;

class UserEventSubManager
{
    private TwitchEventSubService $eventSubService;

    private TwitchScopeService $scopeService;

    // Define which events we support
    public const array SUPPORTED_EVENTS = [
        'channel.follow' => [
            'version' => '2',
            'condition_keys' => ['broadcaster_user_id', 'moderator_user_id'],
            'required_scope' => 'moderator:read:followers',
        ],
        'channel.subscribe' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => 'channel:read:subscriptions',
        ],
        'channel.subscription.gift' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => 'channel:read:subscriptions',
        ],
        'channel.subscription.message' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => 'channel:read:subscriptions',
        ],
        'channel.raid' => [
            'version' => '1',
            'condition_keys' => ['to_broadcaster_user_id'],
            'required_scope' => null,
        ],
        'channel.channel_points_custom_reward_redemption.add' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => 'channel:read:redemptions',
        ],
        'channel.channel_points_custom_reward_redemption.update' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => 'channel:read:redemptions',
        ],
        'stream.online' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => null,
        ],
        'stream.offline' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => null,
        ],
        'channel.update' => [
            'version' => '2',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => null,
        ],
        // Hype train (v2 - v1 withdrawn by Twitch 2026-01-15)
        'channel.hype_train.begin' => [
            'version' => '2',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => 'channel:read:hype_train',
        ],
        'channel.hype_train.progress' => [
            'version' => '2',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => 'channel:read:hype_train',
        ],
        'channel.hype_train.end' => [
            'version' => '2',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => 'channel:read:hype_train',
        ],
        // Charity campaigns
        'channel.charity_campaign.donate' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => 'channel:read:charity',
        ],
        'channel.charity_campaign.start' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => 'channel:read:charity',
        ],
        'channel.charity_campaign.progress' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => 'channel:read:charity',
        ],
        'channel.charity_campaign.stop' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => 'channel:read:charity',
        ],
        // Goals
        'channel.goal.begin' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => 'channel:read:goals',
        ],
        'channel.goal.progress' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => 'channel:read:goals',
        ],
        'channel.goal.end' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => 'channel:read:goals',
        ],
        // Polls
        'channel.poll.begin' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => 'channel:read:polls',
        ],
        'channel.poll.progress' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => 'channel:read:polls',
        ],
        'channel.poll.end' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => 'channel:manage:polls',
        ],
        // Predictions
        'channel.prediction.begin' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => 'channel:read:predictions',
        ],
        'channel.prediction.progress' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => 'channel:read:predictions',
        ],
        'channel.prediction.lock' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => 'channel:read:predictions',
        ],
        'channel.prediction.end' => [
            'version' => '1',
            'condition_keys' => ['broadcaster_user_id'],
            'required_scope' => 'channel:read:predictions',
        ],
    ];

    public function __construct(TwitchEventSubService $eventSubService, TwitchScopeService $scopeService)
    {
        $this->eventSubService = $eventSubService;
        $this->scopeService = $scopeService;
    }

    /**
     * Setup all EventSub subscriptions for a user
     *
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
            'skipped_missing_scope' => [],
        ];

        // Get app access token (required for webhooks)
        $appToken = $this->eventSubService->getAppAccessToken();
        if (! $appToken) {
            throw new Exception('Failed to obtain app access token');
        }

        // Determine webhook URL (production vs local)
        $webhookUrl = $this->getWebhookUrl();

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

            // Skip events whose required scope the user hasn't granted - lets the
            // scope banner drive the relog rather than polluting failed bucket
            // with Twitch 403s.
            $requiredScope = $config['required_scope'] ?? null;
            if ($requiredScope && ! $this->scopeService->hasScope($user, $requiredScope)) {
                $results['skipped_missing_scope'][] = $eventType;

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
                UserEventsubSubscription::create([
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

        // Also clean up Twitch-side subscriptions that may not be in our DB.
        // Scope by user_id so we only look at this broadcaster's subs, avoiding
        // Twitch's 100-per-page cap on the unfiltered endpoint.
        $twitchSubs = $this->eventSubService->getSubscriptions($appToken, [
            'user_id' => $user->twitch_id,
        ]);
        if ($twitchSubs && isset($twitchSubs['data'])) {
            foreach ($twitchSubs['data'] as $sub) {
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

        // Get this user's subscriptions from Twitch. Scoping by user_id avoids
        // the 100-per-page cap that previously caused fresh subs to show up as
        // not_found_on_twitch whenever the app had >100 subs across all users.
        $twitchSubs = $this->eventSubService->getSubscriptions($appToken, [
            'user_id' => $user->twitch_id,
        ]);

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
     * Build condition array for an event type
     */
    private function buildCondition(string $eventType, string $twitchId): array
    {
        return match ($eventType) {
            'channel.follow' => [
                'broadcaster_user_id' => $twitchId,
                'moderator_user_id' => $twitchId,
            ],
            'channel.raid' => [
                'to_broadcaster_user_id' => $twitchId,
            ],
            default => [
                'broadcaster_user_id' => $twitchId,
            ],
        };
    }

    /**
     * Determine the appropriate webhook URL
     */
    private function getWebhookUrl(): string
    {
        return rtrim(config('app.url'), '/').'/api/twitch/webhook';
    }

    /**
     * Get the list of supported events with human-readable labels.
     *
     * @return array<string, string>
     */
    public static function getSupportedEventLabels(): array
    {
        $labels = [
            'stream.online' => 'Stream goes live',
            'stream.offline' => 'Stream goes offline',
            'channel.update' => 'Stream info updated (title, category)',
            'channel.follow' => 'New follower',
            'channel.subscribe' => 'Subscription',
            'channel.subscription.gift' => 'Gifted subscription',
            'channel.subscription.message' => 'Resubscription message',
            'channel.raid' => 'Raid received',
            'channel.channel_points_custom_reward_redemption.add' => 'Channel points redeemed',
            'channel.channel_points_custom_reward_redemption.update' => 'Channel points redemption updated',
            'channel.hype_train.begin' => 'Hype train started',
            'channel.hype_train.progress' => 'Hype train progress',
            'channel.hype_train.end' => 'Hype train ended',
            'channel.charity_campaign.donate' => 'Charity donation received',
            'channel.charity_campaign.start' => 'Charity campaign started',
            'channel.charity_campaign.progress' => 'Charity campaign progress',
            'channel.charity_campaign.stop' => 'Charity campaign ended',
            'channel.goal.begin' => 'Channel goal started',
            'channel.goal.progress' => 'Channel goal progress',
            'channel.goal.end' => 'Channel goal ended',
            'channel.poll.begin' => 'Poll started',
            'channel.poll.progress' => 'Poll progress',
            'channel.poll.end' => 'Poll ended',
            'channel.prediction.begin' => 'Prediction started',
            'channel.prediction.progress' => 'Prediction progress',
            'channel.prediction.lock' => 'Prediction locked',
            'channel.prediction.end' => 'Prediction ended',
        ];

        // Only return labels for events that are actually in SUPPORTED_EVENTS
        return array_intersect_key($labels, self::SUPPORTED_EVENTS);
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
            'subscriptions_by_type' => UserEventsubSubscription::select('event_type')
                ->groupBy('event_type')
                ->pluck('count', 'event_type'),
        ];
    }
}
