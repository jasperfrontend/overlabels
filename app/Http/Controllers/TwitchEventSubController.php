<?php

namespace App\Http\Controllers;

use App\Services\TwitchEventSubService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            $callbackUrl = config('app.url') . '/twitch/webhook';
            
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

            $results = [
                'follow_subscription' => $followSub,
                'sub_subscription' => $subSub,
                'callback_url' => $callbackUrl,
                'debug' => [
                    'user_id' => $user->twitch_id,
                    'access_token_length' => strlen($user->access_token),
                    'access_token_start' => substr($user->access_token, 0, 10) . '...',
                ]
            ];

            Log::info('EventSub connection attempt', $results);

            return response()->json([
                'success' => true,
                'message' => 'EventSub subscriptions created',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to connect to EventSub',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
            // Get all current subscriptions
            $subscriptions = $this->eventSubService->getSubscriptions($user->access_token);
            
            if (!$subscriptions || !isset($subscriptions['data'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'No subscriptions to remove'
                ]);
            }

            $deletedCount = 0;
            foreach ($subscriptions['data'] as $subscription) {
                if ($this->eventSubService->deleteSubscription($user->access_token, $subscription['id'])) {
                    $deletedCount++;
                }
            }

            Log::info('EventSub disconnection', ['deleted_subscriptions' => $deletedCount]);

            return response()->json([
                'success' => true,
                'message' => "Removed {$deletedCount} subscriptions"
            ]);

        } catch (\Exception $e) {
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
            $body = $request->getContent();
            $data = json_decode($body, true);
            $messageType = $request->header('Twitch-Eventsub-Message-Type');

            // Log EVERYTHING for debugging
            Log::info('=== TWITCH WEBHOOK RECEIVED ===', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'message_type' => $messageType,
                'all_headers' => $request->headers->all(),
                'body_raw' => $body,
                'body_decoded' => $data,
                'has_challenge' => isset($data['challenge']),
            ]);

            // Handle webhook verification challenge
            if ($messageType === 'webhook_callback_verification' && isset($data['challenge'])) {
                Log::info('🎯 RESPONDING TO CHALLENGE', ['challenge' => $data['challenge']]);
                
                return response($data['challenge'], 200, ['Content-Type' => 'text/plain']);
            }

            // For all other message types, verify signature
            if ($messageType !== 'webhook_callback_verification') {
                if (!$this->verifyTwitchSignature($request)) {
                    Log::warning('Invalid Twitch webhook signature', ['message_type' => $messageType]);
                    return response('Invalid signature', 403);
                }
            }

            // Handle actual events (notifications)
            if ($messageType === 'notification' && isset($data['event'])) {
                Log::info('Twitch event notification received');
                $this->handleTwitchEvent($data);
            }

            // Handle revocations
            if ($messageType === 'revocation') {
                Log::warning('Twitch subscription revoked', ['data' => $data]);
            }

            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error('WEBHOOK ERROR: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response('Error', 500);
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

        Log::info('Twitch event received', [
            'type' => $eventType,
            'event' => $event
        ]);

        // Broadcast the event to connected WebSocket clients
        // We'll implement this broadcast functionality next
        broadcast(new \App\Events\TwitchEventReceived($eventType, $event));
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

        } catch (\Exception $e) {
            Log::error('Failed to get EventSub status: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to get status'], 500);
        }
    }
}