<?php

namespace App\Http\Controllers;

use App\Events\TwitchEventReceived;
use App\Models\TwitchEvent;
use App\Services\TwitchEventSubService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class TwitchEventSubController extends Controller
{
    private TwitchEventSubService $eventSubService;

    public function __construct(TwitchEventSubService $eventSubService)
    {
        $this->eventSubService = $eventSubService;
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

        if (!$user || !$user->access_token || !$user->twitch_id) {
            return response()->json(['error' => 'User not authenticated with Twitch'], 401);
        }

        try {
            // The callback URL that Twitch will send events to
            $callbackUrl = config('app.url') . '/api/twitch/webhook';

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
                'raid_subscription' => $raidSub,
                'online_subscription' => $onlineSub,
                'callback_url' => $callbackUrl
            ];

            return response()->json([
                'success' => true,
                'message' => 'EventSub subscriptions created',
                'data' => $results
            ]);

        } catch (Exception $e) {
            Log::error('EventSub connection failed: ' . $e->getMessage());

            return response()->json([
                'error' => 'Failed to connect to EventSub',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Disconnect from EventSub (remove all subscriptions)
     */
    public function disconnect(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->access_token) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        try {
            // Get app access token (since subscriptions were created with app token)
            $appToken = $this->eventSubService->getAppAccessToken();

            if (!$appToken) {
                return response()->json([
                    'error' => 'Could not get app access token for cleanup',
                    'message' => 'Failed to get app token'
                ], 500);
            }

            // Get all current subscriptions using an app token
            $subscriptions = $this->eventSubService->getSubscriptions($appToken);

            if (!$subscriptions || !isset($subscriptions['data'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'No subscriptions to remove'
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
                        'type' => $subscription['type']
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Removed $deletedCount subscriptions",
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ]);

        } catch (Exception $e) {
            Log::error('EventSub disconnection failed: ' . $e->getMessage());

            return response()->json([
                'error' => 'Failed to disconnect from EventSub',
                'message' => $e->getMessage()
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
                    'body' => $body
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
                'debug' => true
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
                    header('Content-Length: ' . strlen($challenge));
                    echo $challenge;

                    exit(); // Important: exit immediately to prevent Laravel from adding anything

                } catch (Exception $e) {
                    Log::error('Step 6: Failed to send challenge response', [
                        'error' => $e->getMessage(),
                        'challenge' => $challenge
                    ]);

                    // Fallback to Laravel response
                    return response($challenge, 200, [
                        'Content-Type' => 'text/plain',
                        'Content-Length' => strlen($challenge)
                    ]);
                }
            }

            // Step 7: Handle other message types

            // For all other message types, verify signature
            if ($messageType !== 'webhook_callback_verification') {
                if (!$this->verifyTwitchSignature($request)) {
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
                'trace' => $e->getTraceAsString()
            ]);

            Cache::put('webhook_error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'timestamp' => now()->toISOString()
            ], 300);

            return response('Error: ' . $e->getMessage(), 500);
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

        if (!$user || !$user->access_token) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        try {
            // Get an app access token to check app-created subscriptions
            $appToken = $this->eventSubService->getAppAccessToken();

            if (!$appToken) {
                return response()->json(['error' => 'Could not get app token'], 500);
            }

            // Check subscriptions with app token
            $subscriptions = $this->eventSubService->getSubscriptions($appToken);

            return response()->json([
                'subscriptions' => $subscriptions['data'] ?? [],
                'total' => $subscriptions['total'] ?? 0,
                'breakdown' => collect($subscriptions['data'] ?? [])->groupBy('status')->map->count(),
                'token_type' => 'app_token'
            ]);

        } catch (Exception $e) {
            Log::error('Failed to check EventSub status: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to get status'], 500);
        }
    }

    /**
     * Clean up ALL EventSub subscriptions (both user and app token created)
     */
    public function cleanupAll(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->access_token) {
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
                'errors' => $errors
            ]);

        } catch (Exception $e) {
            Log::error('EventSub cleanup failed: ' . $e->getMessage());

            return response()->json([
                'error' => 'Failed to cleanup subscriptions',
                'message' => $e->getMessage(),
                'deleted_count' => $deletedCount
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
        $secret = config('app.twitch_webhook_secret', 'fallback-secret');

        if (!$signature || !$timestamp) {
            return false;
        }

        $message = $request->header('Twitch-Eventsub-Message-Id') . $timestamp . $body;
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $message, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process incoming Twitch events
     */
    private function handleTwitchEvent(array $data): void
    {
        $eventType = $data['subscription']['type'] ?? 'unknown';
        $event = $data['event'] ?? [];

        try {
            // Store the event in the database
            $twitchEvent = TwitchEvent::create([
                'event_type' => $eventType,
                'event_data' => $event,
                'twitch_timestamp' => now(), // Use current time if not available in the event
                'processed' => false,
            ]);

            Log::info("Stored Twitch event in database: $eventType (ID: $twitchEvent->id)");

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
     * Get current subscription status
     */
    public function status(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->access_token) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        try {
            $subscriptions = $this->eventSubService->getSubscriptions($user->access_token);

            return response()->json([
                'subscriptions' => $subscriptions['data'] ?? [],
                'total' => $subscriptions['total'] ?? 0
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get EventSub status: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to get status'], 500);
        }
    }
}
