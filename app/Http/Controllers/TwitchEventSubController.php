<?php

namespace App\Http\Controllers;

use App\Events\TwitchEventReceived;
use App\Models\EventTemplateMapping;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\TemplateDataMapperService;
use App\Services\TwitchApiService;
use App\Services\TwitchEventSubService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Random\RandomException;

class TwitchEventSubController extends Controller
{
    private TwitchEventSubService $eventSubService;

    private TwitchApiService $twitchService;

    private TemplateDataMapperService $mapper;

    public function __construct(
        TwitchEventSubService $eventSubService,
        TwitchApiService $twitchService,
        TemplateDataMapperService $mapper
    ) {
        $this->eventSubService = $eventSubService;
        $this->twitchService = $twitchService;
        $this->mapper = $mapper;
    }

    /**
     * Show the EventSub demo page
     */
    public function index()
    {
        return Inertia::render('EventSubDemo');
    }

    /**
     * Connect to EventSub (create subscriptions)
     */
    public function connect(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->access_token || ! $user->twitch_id) {
            return response()->json(['error' => 'User not authenticated with Twitch'], 401);
        }

        try {
            // The callback URL that Twitch will send events to
            $callbackUrl = config('app.url').'/api/twitch/webhook';

            // Subscribe to follow events
            $followSub = $this->eventSubService->subscribeToFollows(
                $user->access_token,
                $user->twitch_id,
                $callbackUrl
            );

            // Subscribe to subscription events
            $subSub = $this->eventSubService->subscribeToSubscriptions(
                $user->access_token,
                $user->twitch_id,
                $callbackUrl
            );

            // Subscribe to gift subscription events
            $giftSub = $this->eventSubService->subscribeToSubscriptionGifts(
                $user->access_token,
                $user->twitch_id,
                $callbackUrl
            );

            // Subscribe to subscription message events (resubs)
            $resubSub = $this->eventSubService->subscribeToSubscriptionMessages(
                $user->access_token,
                $user->twitch_id,
                $callbackUrl
            );

            $raidSub = $this->eventSubService->subscribeToRaids(
                $user->access_token,
                $user->twitch_id,
                $callbackUrl
            );
            $onlineSub = $this->eventSubService->subscribeToStreamOnline(
                $user->access_token,
                $user->twitch_id,
                $callbackUrl
            );

            $results = [
                'follow_subscription' => $followSub,
                'sub_subscription' => $subSub,
                'gift_subscription' => $giftSub,
                'resub_subscription' => $resubSub,
                'raid_subscription' => $raidSub,
                'online_subscription' => $onlineSub,
                'callback_url' => $callbackUrl,
            ];

            return response()->json([
                'success' => true,
                'message' => 'EventSub subscriptions created',
                'data' => $results,
            ]);

        } catch (Exception $e) {
            Log::error('EventSub connection failed: '.$e->getMessage());

            return response()->json([
                'error' => 'Failed to connect to EventSub',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disconnect from EventSub (remove all subscriptions)
     */
    public function disconnect(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->access_token) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        try {
            // Get app access token (since subscriptions were created with app token)
            $appToken = $this->eventSubService->getAppAccessToken();

            if (! $appToken) {
                return response()->json([
                    'error' => 'Could not get app access token for cleanup',
                    'message' => 'Failed to get app token',
                ], 500);
            }

            // Get all current subscriptions using an app token
            $subscriptions = $this->eventSubService->getSubscriptions($appToken);

            if (! $subscriptions || ! isset($subscriptions['data'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'No subscriptions to remove',
                ]);
            }

            $deletedCount = 0;
            $errors = [];

            foreach ($subscriptions['data'] as $subscription) {
                if ($this->eventSubService->deleteSubscription($appToken, $subscription['id'])) {
                    $deletedCount++;

                } else {
                    $errors[] = "Failed to delete subscription: {$subscription['id']}";
                    Log::warning('Failed to delete subscription', [
                        'id' => $subscription['id'],
                        'type' => $subscription['type'],
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Removed $deletedCount subscriptions",
                'deleted_count' => $deletedCount,
                'errors' => $errors,
            ]);

        } catch (Exception $e) {
            Log::error('EventSub disconnection failed: '.$e->getMessage());

            return response()->json([
                'error' => 'Failed to disconnect from EventSub',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle incoming webhooks from Twitch
     */
    public function webhook(Request $request)
    {

        try {
            // Step 1: Get the raw body
            $body = $request->getContent();

            // Step 2: Parse JSON
            $data = json_decode($body, true);
            $jsonError = json_last_error();

            if ($jsonError !== JSON_ERROR_NONE) {
                Log::error('JSON parsing failed', [
                    'error' => json_last_error_msg(),
                    'body' => $body,
                ]);

                return response('Invalid JSON', 400);
            }

            // Step 3: Get a message type
            $messageType = $request->header('Twitch-Eventsub-Message-Type');

            // Step 4: Check if it's a challenge
            $isChallenge = $messageType === 'webhook_callback_verification' && isset($data['challenge']);

            // Step 5: Store webhook activity
            $webhookLog = [
                'timestamp' => now()->toISOString(),
                'message_type' => $messageType,
                'has_challenge' => isset($data['challenge']),
                'challenge' => $data['challenge'] ?? null,
                'event_type' => $data['subscription']['type'] ?? null,
                'status' => 'received',
                'debug' => true,
            ];

            Cache::put('last_webhook_activity', $webhookLog, 300);

            // Step 6: Handle challenge if present
            if ($isChallenge) {

                // Update webhook log
                $webhookLog['status'] = 'challenge_responded';
                Cache::put('last_webhook_activity', $webhookLog, 300);
                Cache::put('webhook_challenge_received', true, 300);

                $challenge = $data['challenge'];

                try {
                    // Ultra-simple response - bypass Laravel response system
                    http_response_code(200);
                    header('Content-Type: text/plain');
                    header('Content-Length: '.strlen($challenge));
                    echo $challenge;

                    exit(); // Important: exit immediately to prevent Laravel from adding anything

                } catch (Exception $e) {
                    Log::error('Step 6: Failed to send challenge response', [
                        'error' => $e->getMessage(),
                        'challenge' => $challenge,
                    ]);

                    // Fallback to Laravel response
                    return response($challenge, 200, [
                        'Content-Type' => 'text/plain',
                        'Content-Length' => strlen($challenge),
                    ]);
                }
            }

            // Step 7: Handle other message types

            // For all other message types, verify signature
            if ($messageType !== 'webhook_callback_verification') {
                if (! $this->verifyTwitchSignature($request)) {
                    Log::warning('Invalid Twitch webhook signature', ['message_type' => $messageType]);

                    return response('Invalid signature', 403);
                }
            }

            // Handle actual events (notifications)
            if ($messageType === 'notification' && isset($data['event'])) {
                $this->handleTwitchEvent($data);

                // Update webhook log
                $webhookLog['status'] = 'event_processed';
                $webhookLog['event_data'] = $data['event'];
                Cache::put('last_webhook_activity', $webhookLog, 300);
            }

            // Handle revocations
            if ($messageType === 'revocation') {
                Log::warning('Twitch subscription revoked', ['data' => $data]);

                // Update webhook log
                $webhookLog['status'] = 'subscription_revoked';
                Cache::put('last_webhook_activity', $webhookLog, 300);
            }

            return response('OK', 200);

        } catch (Exception $e) {
            Log::error('=== WEBHOOK EXCEPTION ===', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            Cache::put('webhook_error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'timestamp' => now()->toISOString(),
            ], 300);

            return response('Error: '.$e->getMessage(), 500);
        }
    }

    public function webhookStatus()
    {
        return response()->json([
            'last_activity' => Cache::get('last_webhook_activity'),
            'challenge_received' => Cache::get('webhook_challenge_received', false),
            'error' => Cache::get('webhook_error'),
        ]);
    }

    /**
     * Check and log the current status of all subscriptions
     */
    public function checkStatus(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->access_token) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        try {
            // Get an app access token to check app-created subscriptions
            $appToken = $this->eventSubService->getAppAccessToken();

            if (! $appToken) {
                return response()->json(['error' => 'Could not get app token'], 500);
            }

            // Check subscriptions with app token
            $subscriptions = $this->eventSubService->getSubscriptions($appToken);

            return response()->json([
                'subscriptions' => $subscriptions['data'] ?? [],
                'total' => $subscriptions['total'] ?? 0,
                'breakdown' => collect($subscriptions['data'] ?? [])->groupBy('status')->map->count(),
                'token_type' => 'app_token',
            ]);

        } catch (Exception $e) {
            Log::error('Failed to check EventSub status: '.$e->getMessage());

            return response()->json(['error' => 'Failed to get status'], 500);
        }
    }

    /**
     * Clean up ALL EventSub subscriptions (both user and app token created)
     */
    public function cleanupAll(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->access_token) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $deletedCount = 0;
        $errors = [];

        try {
            // Get app access token
            $appToken = $this->eventSubService->getAppAccessToken();

            if ($appToken) {
                // Clean up app token subscriptions
                $appSubscriptions = $this->eventSubService->getSubscriptions($appToken);

                if ($appSubscriptions && isset($appSubscriptions['data'])) {
                    foreach ($appSubscriptions['data'] as $subscription) {
                        if ($this->eventSubService->deleteSubscription($appToken, $subscription['id'])) {
                            $deletedCount++;
                        } else {
                            $errors[] = "Failed to delete app subscription: {$subscription['id']}";
                        }
                    }
                }
            }

            // Also try with a user token
            $userSubscriptions = $this->eventSubService->getSubscriptions($user->access_token);

            if ($userSubscriptions && isset($userSubscriptions['data'])) {
                foreach ($userSubscriptions['data'] as $subscription) {
                    if ($this->eventSubService->deleteSubscription($user->access_token, $subscription['id'])) {
                        $deletedCount++;
                    } else {
                        $errors[] = "Failed to delete user subscription: {$subscription['id']}";
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Cleaned up $deletedCount subscriptions",
                'deleted_count' => $deletedCount,
                'errors' => $errors,
            ]);

        } catch (Exception $e) {
            Log::error('EventSub cleanup failed: '.$e->getMessage());

            return response()->json([
                'error' => 'Failed to cleanup subscriptions',
                'message' => $e->getMessage(),
                'deleted_count' => $deletedCount,
            ], 500);
        }
    }

    /**
     * Verify that the webhook came from Twitch
     */
    private function verifyTwitchSignature(Request $request): bool
    {
        $signature = $request->header('Twitch-Eventsub-Message-Signature');
        $timestamp = $request->header('Twitch-Eventsub-Message-Timestamp');
        $body = $request->getContent();

        if (! $signature || ! $timestamp) {
            return false;
        }

        $message = $request->header('Twitch-Eventsub-Message-Id').$timestamp.$body;

        // Try per-user webhook secret first
        $data = json_decode($body, true);
        $event = $data['event'] ?? [];
        $eventType = $data['subscription']['type'] ?? '';
        $broadcasterId = $eventType === 'channel.raid'
            ? ($event['to_broadcaster_user_id'] ?? null)
            : ($event['broadcaster_user_id'] ?? null);

        if ($broadcasterId) {
            $user = User::where('twitch_id', $broadcasterId)->first();
            if ($user && $user->webhook_secret) {
                $expected = 'sha256='.hash_hmac('sha256', $message, $user->webhook_secret);
                if (hash_equals($expected, $signature)) {
                    return true;
                }
            }
        }

        // Fall back to global secret (backward-compatible for existing users)
        $globalSecret = config('app.twitch_webhook_secret');
        $expectedGlobal = 'sha256='.hash_hmac('sha256', $message, $globalSecret);

        return hash_equals($expectedGlobal, $signature);
    }

    /**
     * Refresh relevant caches based on the event type
     */
    private function refreshCachesForEvent(string $eventType, ?string $broadcasterId): void
    {
        if (! $broadcasterId) {
            return;
        }

        try {
            // Map event types to cache clear methods
            switch ($eventType) {
                case 'channel.follow':
                    // New follower - refresh followers and goals
                    $this->twitchService->clearChannelFollowersCaches($broadcasterId);
                    $this->twitchService->clearGoalsCaches($broadcasterId);
                    Log::info('Cleared follower and goals caches for new follow event', [
                        'broadcaster_id' => $broadcasterId,
                    ]);
                    break;

                case 'channel.subscribe':
                case 'channel.subscription.gift':
                case 'channel.subscription.message':
                    // New subscriber - refresh subscribers and goals
                    $this->twitchService->clearSubscribersCaches($broadcasterId);
                    $this->twitchService->clearGoalsCaches($broadcasterId);
                    Log::info('Cleared subscriber and goals caches for subscription event', [
                        'event_type' => $eventType,
                        'broadcaster_id' => $broadcasterId,
                    ]);
                    break;

                case 'channel.raid':
                    // Raid might affect goals
                    $this->twitchService->clearGoalsCaches($broadcasterId);
                    Log::info('Cleared goals cache for raid event', [
                        'broadcaster_id' => $broadcasterId,
                    ]);
                    break;

                case 'stream.online':
                case 'stream.offline':
                    // Stream status change - might want to refresh channel info
                    $this->twitchService->clearChannelInfoCaches($broadcasterId);
                    Log::info('Cleared channel info cache for stream status change', [
                        'event_type' => $eventType,
                        'broadcaster_id' => $broadcasterId,
                    ]);
                    break;

                default:
                    // No specific cache clearing needed for other events
                    break;
            }
        } catch (Exception $e) {
            Log::warning('Failed to clear caches for event', [
                'event_type' => $eventType,
                'broadcaster_id' => $broadcasterId,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - cache clearing failure shouldn't break event processing
        }
    }

    /**
     * Process incoming Twitch events
     */
    private function handleTwitchEvent(array $data): void
    {
        $eventType = $data['subscription']['type'] ?? 'unknown';
        $event = $data['event'] ?? [];

        try {
            // Find the broadcaster user
            // For raid events, the broadcaster is in 'to_broadcaster_user_id'
            $broadcasterId = $eventType === 'channel.raid'
                ? ($event['to_broadcaster_user_id'] ?? null)
                : ($event['broadcaster_user_id'] ?? null);

            $user = $broadcasterId ? User::where('twitch_id', $broadcasterId)->first() : null;

            // Store the event in the database
            $twitchEvent = TwitchEvent::create([
                'user_id' => $user?->id,
                'event_type' => $eventType,
                'event_data' => $event,
                'twitch_timestamp' => now(),
                'processed' => false,
            ]);

            Log::info("Stored Twitch event in database: $eventType (ID: $twitchEvent->id)");

            // Clear relevant caches based on event type
            $this->refreshCachesForEvent($eventType, $broadcasterId);

            Log::info('Processing event for broadcaster', [
                'event_type' => $eventType,
                'broadcaster_id' => $broadcasterId,
                'event_keys' => array_keys($event),
            ]);

            if ($broadcasterId) {
                if ($user) {
                    // Check if user has a template mapping for this event
                    $mapping = EventTemplateMapping::with('template')
                        ->where('user_id', $user->id)
                        ->where('event_type', $eventType)
                        ->where('enabled', true)
                        ->first();

                    if ($mapping && $mapping->template) {
                        Log::info('Found template mapping for event', [
                            'event_type' => $eventType,
                            'template_id' => $mapping->template_id,
                            'user_id' => $user->id,
                        ]);
                        // Render the user's custom alert template
                        $this->renderEventAlert($user, $mapping, $data);
                    } else {
                        Log::warning('No enabled template mapping found', [
                            'event_type' => $eventType,
                            'user_id' => $user->id,
                            'mapping_exists' => EventTemplateMapping::where('user_id', $user->id)
                                ->where('event_type', $eventType)->exists(),
                        ]);
                    }
                } else {
                    Log::warning("User not found for Twitch ID: $broadcasterId");
                }
            } else {
                Log::warning('No broadcaster ID found in event', [
                    'event_type' => $eventType,
                    'event_keys' => array_keys($event),
                ]);
            }

            // Broadcast the event to connected WebSocket clients
            broadcast(new TwitchEventReceived($eventType, $event));

        } catch (Exception $e) {
            Log::error("Failed to store Twitch event: {$e->getMessage()}", [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);

            // Still broadcast the event even if database storage fails
            broadcast(new TwitchEventReceived($eventType, $event));
        }
    }

    /**
     * Render user's custom alert template for an event
     */
    private function renderEventAlert(User $user, EventTemplateMapping $mapping, array $eventData): void
    {
        try {
            // Get user's Twitch data for static template tags
            $twitchData = $this->twitchService->getExtendedUserData(
                $user->access_token,
                $user->twitch_id
            );

            // Map template data including event tags
            $templateData = $this->mapper->mapForTemplate(
                $twitchData,
                $mapping->template->name,
                $mapping->template->template_tags,
                $eventData // Pass event data for event.* tags
            );

            // Broadcast alert data to overlay
            broadcast(new \App\Events\AlertTriggered(
                $mapping->template->html,
                $mapping->template->css,
                $templateData,
                $mapping->duration_ms,
                $mapping->transition_in,
                $mapping->transition_out,
                $user->twitch_id
            ));

            Log::info("Rendered custom alert for user {$user->id}", [
                'event_type' => $eventData['subscription']['type'],
                'template_id' => $mapping->template_id,
                'duration' => $mapping->duration_ms,
            ]);

        } catch (Exception $e) {
            Log::error("Failed to render event alert: {$e->getMessage()}", [
                'user_id' => $user->id,
                'template_id' => $mapping->template_id,
                'event_type' => $eventData['subscription']['type'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Replay a historical event as an alert
     * @throws RandomException
     */
    public function replay(Request $request, TwitchEvent $twitchEvent)
    {
        $user = $request->user();

        if ($twitchEvent->user_id !== $user->id) {
            return back()->with('message', 'You do not own this event.')->with('type', 'error');
        }

        $mapping = EventTemplateMapping::with('template')
            ->where('user_id', $user->id)
            ->where('event_type', $twitchEvent->event_type)
            ->where('enabled', true)
            ->first();

        if (! $mapping || ! $mapping->template) {
            return back()->with('message', 'No active template mapping found for this event type.')->with('type', 'error');
        }

        $reconstructedData = [
            'subscription' => ['type' => $twitchEvent->event_type],
            'event' => $twitchEvent->event_data,
        ];

        $this->renderEventAlert($user, $mapping, $reconstructedData);
        $randomString = str_pad(random_int(1, 999999), 4, '0', STR_PAD_LEFT);
        $message = "Replayed alert {$twitchEvent->event_type} (ID: {$twitchEvent->id}-{$randomString})";
        return back()->with('message', $message)->with('type', 'success');
    }

    /**
     * Get current subscription status
     */
    public function status(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->access_token) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        try {
            $subscriptions = $this->eventSubService->getSubscriptions($user->access_token);

            return response()->json([
                'subscriptions' => $subscriptions['data'] ?? [],
                'total' => $subscriptions['total'] ?? 0,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get EventSub status: '.$e->getMessage());

            return response()->json(['error' => 'Failed to get status'], 500);
        }
    }
}
