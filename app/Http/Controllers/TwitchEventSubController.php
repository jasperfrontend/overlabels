<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryOutcome;
use App\Events\AlertTriggered;
use App\Events\TwitchEventReceived;
use App\Jobs\DeleteTestTwitchEvent;
use App\Jobs\ForgetTwitchCache;
use App\Jobs\ProcessTwitchEvent;
use App\Jobs\SynthesizeAlertTts;
use App\Models\BotChatOutbox;
use App\Models\EventTemplateMapping;
use App\Models\OverlayTemplate;
use App\Models\StreamState;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Models\UserEventsubSubscription;
use App\Services\AlertMuteService;
use App\Services\Alerts\AlertsOverlay;
use App\Services\Alerts\GiftSubAbsorber;
use App\Services\DeliveryLedger;
use App\Services\EventMeter;
use App\Services\LivingTitleService;
use App\Services\LockdownService;
use App\Services\Messages\AlertMessageRenderer;
use App\Services\StreamSessionService;
use App\Services\StreamStateMachineService;
use App\Services\TemplateDataMapperService;
use App\Services\TwitchApiService;
use App\Services\TwitchEventSubService;
use App\Services\TwitchPayloadScrubber;
use App\Services\TwitchTokenService;
use App\Services\ViewerErasureService;
use Exception;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Random\RandomException;
use Throwable;

class TwitchEventSubController extends Controller
{
    /**
     * Event types that are stored and metered, and then dropped: no cache
     * refresh, no per-stream counter, no alert, no broadcast to the overlay.
     *
     * channel.chat.notification is the USERNOTICE feed. Its `sub` notice
     * arrives next to the channel.subscribe for the same viewer, so letting
     * it run the alert path would fire every sub alert and counter twice.
     * And OverlayRenderer spreads every broadcast payload into the overlay's
     * tag data, where the notice's `message` object would blank a
     * [[[message]]] the resub had just filled. The rows exist to be read
     * later, as the ledger a Plus Points count is built from.
     */
    public const array STORE_ONLY_EVENTS = ['channel.chat.notification'];

    /** How often one streamer's follower/subscriber/goal caches may be cleared. See clearVolatileCache(). */
    public const int CACHE_CLEAR_WINDOW_SECONDS = 10;

    private TwitchEventSubService $eventSubService;

    private TwitchApiService $twitchService;

    private TemplateDataMapperService $mapper;

    private StreamSessionService $streamSessionService;

    private StreamStateMachineService $streamStateMachine;

    public function __construct(
        TwitchEventSubService $eventSubService,
        TwitchApiService $twitchService,
        TemplateDataMapperService $mapper,
        StreamSessionService $streamSessionService,
        StreamStateMachineService $streamStateMachine,
        private readonly ViewerErasureService $erasures,
    ) {
        $this->eventSubService = $eventSubService;
        $this->twitchService = $twitchService;
        $this->mapper = $mapper;
        $this->streamSessionService = $streamSessionService;
        $this->streamStateMachine = $streamStateMachine;
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
            $secret = $user->webhook_secret;

            // Subscribe to follow events
            $followSub = $this->eventSubService->subscribeToFollows(
                $user->access_token,
                $user->twitch_id,
                $callbackUrl,
                $secret
            );

            // Subscribe to subscription events
            $subSub = $this->eventSubService->subscribeToSubscriptions(
                $user->access_token,
                $user->twitch_id,
                $callbackUrl,
                $secret
            );

            // Subscribe to gift subscription events
            $giftSub = $this->eventSubService->subscribeToSubscriptionGifts(
                $user->access_token,
                $user->twitch_id,
                $callbackUrl,
                $secret
            );

            // Subscribe to subscription message events (resubs)
            $resubSub = $this->eventSubService->subscribeToSubscriptionMessages(
                $user->access_token,
                $user->twitch_id,
                $callbackUrl,
                $secret
            );

            $raidSub = $this->eventSubService->subscribeToRaids(
                $user->access_token,
                $user->twitch_id,
                $callbackUrl,
                $secret
            );
            $onlineSub = $this->eventSubService->subscribeToStreamOnline(
                $user->access_token,
                $user->twitch_id,
                $callbackUrl,
                $secret
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
        if (app(LockdownService::class)->isActive()) {
            return response()->json(['ok' => true]); // absorb silently during lockdown
        }

        try {
            // Step 1: Get the raw body
            $body = $request->getContent();

            // Step 2: Parse JSON
            $data = json_decode($body, true);
            $jsonError = json_last_error();

            if ($jsonError !== JSON_ERROR_NONE) {
                // Deliberately does not log $body. An unparseable webhook body
                // is still a webhook body: viewer names, cheer messages and
                // redemption text, written verbatim into a plaintext log file
                // that nothing sweeps.
                Log::error('JSON parsing failed', [
                    'error' => json_last_error_msg(),
                    'bytes' => strlen($body),
                ]);

                return response('Invalid JSON', 400);
            }

            // Step 3: Get a message type
            $messageType = $request->header('Twitch-Eventsub-Message-Type');

            // Step 4: Check if it's a challenge
            $isChallenge = $messageType === 'webhook_callback_verification' && isset($data['challenge']);

            // Step 5 used to mirror every inbound webhook into a
            // `last_webhook_activity` cache key, upgraded on notification to
            // carry the whole event payload. It was a single global key shared
            // by every account, so one streamer's viewer data sat in it until
            // the next streamer's event replaced it, and nothing anywhere ever
            // read it. Removed rather than scoped: dead debug code does not
            // need a tenancy model.

            // Step 6: Handle challenge if present
            if ($isChallenge) {

                $challenge = $data['challenge'];
                $subscriptionId = $data['subscription']['id'] ?? null;

                // Mark the subscription as enabled in our DB - if we respond correctly,
                // Twitch will enable it on their side too
                if ($subscriptionId) {
                    UserEventsubSubscription::where('twitch_subscription_id', $subscriptionId)
                        ->update(['status' => 'enabled', 'last_verified_at' => now()]);
                }

                return response($challenge, 200)
                    ->header('Content-Type', 'text/plain')
                    ->header('Content-Length', strlen($challenge));
            }

            // Step 7: Handle other message types

            // For all other message types, verify signature
            if ($messageType !== 'webhook_callback_verification') {
                if (! $this->verifyTwitchSignature($request)) {
                    $debugData = json_decode($body, true);
                    $debugEvent = $debugData['event'] ?? [];
                    $debugEventType = $debugData['subscription']['type'] ?? 'unknown';
                    $debugBroadcasterId = $debugEventType === 'channel.raid'
                        ? ($debugEvent['to_broadcaster_user_id'] ?? null)
                        : ($debugEvent['broadcaster_user_id'] ?? null);
                    $debugUser = $debugBroadcasterId ? User::where('twitch_id', $debugBroadcasterId)->first() : null;

                    Log::warning('Invalid Twitch webhook signature', [
                        'message_type' => $messageType,
                        'event_type' => $debugEventType,
                        'subscription_id' => $debugData['subscription']['id'] ?? 'unknown',
                        'message_id' => $request->header('Twitch-Eventsub-Message-Id'),
                        'broadcaster_id' => $debugBroadcasterId,
                        'user_has_webhook_secret' => $debugUser ? ($debugUser->webhook_secret !== null) : 'user_not_found',
                        'global_secret_set' => config('app.twitch_webhook_secret') !== null,
                        'signature_prefix' => substr($request->header('Twitch-Eventsub-Message-Signature') ?? '', 0, 20).'...',
                    ]);

                    return response('Invalid signature', 403);
                }
            }

            // Handle actual events (notifications)
            if ($messageType === 'notification' && isset($data['event'])) {
                $this->handleTwitchEvent(
                    $data,
                    $request->header('Twitch-Eventsub-Message-Id'),
                    $request->header('Twitch-Eventsub-Message-Timestamp'),
                );
            }

            // Handle revocations
            if ($messageType === 'revocation') {
                Log::warning('Twitch subscription revoked', ['data' => $data]);

                // Twitch has stopped delivering this subscription. Record the status it
                // sent (authorization_revoked, user_removed, notification_failures_exceeded)
                // so the integrations page and eventsub:monitor see it now, not at the
                // next 24-hour verify.
                $revokedId = $data['subscription']['id'] ?? null;
                $revokedStatus = $data['subscription']['status'] ?? null;

                if ($revokedId && $revokedStatus) {
                    UserEventsubSubscription::where('twitch_subscription_id', $revokedId)
                        ->update(['status' => $revokedStatus, 'last_verified_at' => now()]);
                }
            }

            return response('OK', 200);

        } catch (Exception $e) {
            Log::error('=== WEBHOOK EXCEPTION ===', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response('Error: '.$e->getMessage(), 500);
        }
    }

    /**
     * Verify that the webhook came from Twitch
     */
    private function verifyTwitchSignature(Request $request): bool
    {
        return $this->signatureIsValid(
            $request->header('Twitch-Eventsub-Message-Id'),
            $request->header('Twitch-Eventsub-Message-Timestamp'),
            $request->getContent(),
            $request->header('Twitch-Eventsub-Message-Signature'),
        );
    }

    /**
     * The HMAC check, from its parts, so the direct webhook and the front desk
     * batch (where the headers travel inside the message) verify identically.
     */
    private function signatureIsValid(?string $messageId, ?string $timestamp, string $body, ?string $signature): bool
    {
        if (! $signature || ! $timestamp) {
            return false;
        }

        $message = $messageId.$timestamp.$body;

        // Try per-user webhook secret first
        $data = json_decode($body, true);
        $event = $data['event'] ?? [];
        $eventType = $data['subscription']['type'] ?? '';
        $broadcasterId = $eventType === 'channel.raid'
            ? ($event['to_broadcaster_user_id'] ?? null)
            : ($event['broadcaster_user_id'] ?? null);

        if ($broadcasterId) {
            $user = $this->userByTwitchId((string) $broadcasterId);
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
     * Pick the winning poll choice(s) by max `votes`. Returns every choice tied
     * at the max so templates can render ties without per-template logic. With
     * all-zero votes (poll just started) this returns every choice, which is
     * what lets a single alert template handle begin/progress/end uniformly.
     *
     * @param  array<int, array<string, mixed>>  $choices
     * @return array<int, array<string, mixed>>
     */
    private function computePollWinners(array $choices): array
    {
        if (empty($choices)) {
            return [];
        }

        $max = 0;
        foreach ($choices as $choice) {
            $votes = (int) ($choice['votes'] ?? 0);
            if ($votes > $max) {
                $max = $votes;
            }
        }

        return array_values(array_filter(
            $choices,
            fn ($choice) => (int) ($choice['votes'] ?? 0) === $max,
        ));
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
                    $this->clearVolatileCache('channel_followers', $broadcasterId);
                    $this->clearVolatileCache('goals', $broadcasterId);
                    break;

                case 'channel.subscribe':
                case 'channel.subscription.gift':
                case 'channel.subscription.message':
                    // New subscriber - refresh subscribers and goals
                    $this->clearVolatileCache('subscribers', $broadcasterId);
                    $this->clearVolatileCache('goals', $broadcasterId);
                    break;

                case 'channel.raid':
                    // Raid might affect goals
                    $this->clearVolatileCache('goals', $broadcasterId);
                    break;

                case 'channel.goal.begin':
                case 'channel.goal.progress':
                case 'channel.goal.end':
                    // Goal updates - keep goals cache fresh
                    $this->clearVolatileCache('goals', $broadcasterId);
                    break;

                case 'stream.online':
                case 'stream.offline':
                case 'channel.update':
                    // Stream status or info change - refresh channel info
                    $this->twitchService->clearChannelInfoCaches($broadcasterId);
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
     * Clear a volatile Helix cache (followers, subscribers, goals) at most once
     * per window per streamer, plus once more when the window ends.
     *
     * Clearing on EVERY event made the alert render fetch the data straight back
     * from Helix each time: four round trips per follow on the single
     * twitch-events worker, and a raid's worth of them against the streamer's
     * rate budget, for a follower total Twitch itself only updates minutes later
     * (#343). The overlay never needed it - it counts from the events. Now the
     * first event of a window clears (its alert reads fresh data), the rest reuse
     * that, and ForgetTwitchCache clears again at the end of the window so the
     * last change of a burst is not held for the full TTL.
     */
    private function clearVolatileCache(string $cache, string $broadcasterId): void
    {
        if (! Cache::add("twitch:cache_clear:{$cache}:{$broadcasterId}", 1, self::CACHE_CLEAR_WINDOW_SECONDS)) {
            return;
        }

        ForgetTwitchCache::forget($this->twitchService, $cache, $broadcasterId);
        ForgetTwitchCache::dispatch($cache, $broadcasterId)->delay(now()->addSeconds(self::CACHE_CLEAR_WINDOW_SECONDS));
    }

    /**
     * Store a verified notification and queue its work.
     *
     * @return 'stored'|'duplicate'|'error' what the front desk batch reports per
     *                                      message: an error is retried, the rest acked
     */
    private function handleTwitchEvent(array $data, ?string $messageId = null, ?string $sentAt = null): string
    {
        $eventType = $data['subscription']['type'] ?? 'unknown';
        $event = $data['event'] ?? [];

        try {
            // Find the broadcaster user
            // For raid events, the broadcaster is in 'to_broadcaster_user_id'
            $broadcasterId = $eventType === 'channel.raid'
                ? ($event['to_broadcaster_user_id'] ?? null)
                : ($event['broadcaster_user_id'] ?? null);

            $user = $broadcasterId ? $this->userByTwitchId((string) $broadcasterId) : null;

            // Twitch poll payloads ship per-choice votes but no winner. Compute it
            // server-side once so the same value flows through DB persist, alert
            // render, and broadcast - and templates can iterate it via foreach.
            if (in_array($eventType, ['channel.poll.begin', 'channel.poll.progress', 'channel.poll.end'], true)) {
                $event['winners'] = $this->computePollWinners($event['choices'] ?? []);
                $data['event'] = $event;
            }

            // Take out what is not ours to keep, BEFORE enrichment: an avatar
            // must not be fetched for a viewer we are about to drop, and an
            // anonymous cheerer must not be given one at all. Everything
            // downstream - persist, alert render, broadcast, TTS - reads the
            // scrubbed payload.
            //
            // A viewer who used !forgetme is anonymised here too. !forgetme
            // promises "we won't store you again", and without this the next
            // follow, sub or cheer from them writes their id and display name
            // straight back into twitch_events for another 90 days. The
            // streamer still gets the event, the alert and the counter; what
            // they do not get is a name, exactly as with an anonymous cheer.
            $event = TwitchPayloadScrubber::scrub(
                $event,
                forceAnonymous: $this->erasures->isSuppressed(
                    TwitchPayloadScrubber::actingViewerId($event)
                ),
            );
            $data['event'] = $event;

            // Store the event in the database. Twitch retries on any non-2xx or
            // timeout with the same message id; the unique index turns a
            // redelivery into a no-op instead of a second row and a second alert.
            // The insert runs in its own transaction so a violation rolls back to
            // a savepoint rather than poisoning any enclosing transaction (Postgres
            // refuses every statement after an error until rollback).
            try {
                $twitchEvent = DB::transaction(fn () => TwitchEvent::create([
                    'user_id' => $user?->id,
                    'event_type' => $eventType,
                    'message_id' => $messageId,
                    'event_data' => $event,
                    'twitch_timestamp' => now(),
                    'processed' => false,
                ]));
            } catch (UniqueConstraintViolationException) {
                Log::info('Duplicate Twitch event ignored', [
                    'event_type' => $eventType,
                    'message_id' => $messageId,
                ]);

                return 'duplicate';
            }

            // Answer Twitch now; everything with an outbound call or alert
            // work happens on the twitch-events queue. See ProcessTwitchEvent.
            ProcessTwitchEvent::dispatch($twitchEvent->id, $data, $sentAt);

            return 'stored';
        } catch (Exception $e) {
            Log::error("Failed to store Twitch event: {$e->getMessage()}", [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);

            // Still broadcast the event even if database storage fails
            if (! empty($broadcasterId)) {
                broadcast(new TwitchEventReceived($eventType, $event, (string) $broadcasterId));
            }
        } catch (Throwable $e) {
            // A PHP Error (TypeError, a ShouldBroadcastNow handler throwing, ...) is
            // not an Exception and used to land here silently: no log, no row, and a
            // 200 to Twitch so it never retried. Still swallow it - a 5xx makes Twitch
            // redeliver, and twitch_events has no message_id to dedup the redelivery
            // against - but say so.
            Log::error("Unhandled error while handling Twitch event: {$e->getMessage()}", [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return 'error';
    }

    /**
     * Notifications from the Cloudflare front desk, up to 100 at a time.
     *
     * The Worker answered Twitch at the edge and queued each notification with
     * its headers and raw body; it holds no secrets and checks nothing beyond
     * shape. So every message is verified here exactly like a direct webhook,
     * then stored and queued the same way. The answer names each message's
     * fate: `stored`, `duplicate` and `invalid` are acked, `error` is retried.
     * The raw request content is decoded directly: TrimStrings would trim the
     * bodies inside it and break their signatures.
     *
     * See docs/design/eventsub-front-desk.md.
     */
    public function batch(Request $request): JsonResponse
    {
        // Trimmed on both ends: a secret piped in through a shell can pick up a
        // trailing newline in one store (Kamal, wrangler) and not the other.
        $secret = trim((string) config('services.twitch.front_desk_secret'));
        if ($secret === '' || ! hash_equals($secret, trim((string) $request->header('X-Front-Desk-Secret')))) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = json_decode($request->getContent(), true);
        $messages = is_array($payload) && is_array($payload['messages'] ?? null) ? $payload['messages'] : null;
        if ($messages === null || count($messages) > 100) {
            return response()->json(['error' => 'Expected at most 100 messages'], 422);
        }

        $lockdown = app(LockdownService::class)->isActive();
        $results = [];
        $invalid = 0;

        foreach ($messages as $message) {
            $id = is_array($message) && is_string($message['id'] ?? null) ? $message['id'] : null;
            if ($id === null) {
                $invalid++;

                continue;
            }

            // Absorbed silently during lockdown, like the direct webhook.
            if ($lockdown) {
                $results[$id] = 'ignored';

                continue;
            }

            $timestamp = is_string($message['timestamp'] ?? null) ? $message['timestamp'] : null;
            $signature = is_string($message['signature'] ?? null) ? $message['signature'] : null;
            $body = is_string($message['body'] ?? null) ? $message['body'] : '';
            $data = json_decode($body, true);

            if (! is_array($data) || ! isset($data['event']) || ! $this->signatureIsValid($id, $timestamp, $body, $signature)) {
                $results[$id] = 'invalid';
                $invalid++;

                continue;
            }

            $results[$id] = $this->handleTwitchEvent($data, $id, $timestamp);
        }

        if ($invalid > 0) {
            Log::warning('Front desk batch held messages that failed verification', [
                'invalid' => $invalid,
                'messages' => count($messages),
            ]);
        }

        return response()->json(['results' => (object) $results]);
    }

    /** Notifications Twitch sent longer ago than this fire no alert. See DeliveryOutcome::Late. */
    public const int LATE_ALERT_SECONDS = 300;

    /**
     * Was this notification sent more than LATE_ALERT_SECONDS ago? Twitch's
     * timestamp carries nanoseconds, which PHP cannot parse, so the fraction is
     * cut to microseconds first. Missing or unreadable: not late.
     */
    private static function isLate(?string $sentAt): bool
    {
        if ($sentAt === null || $sentAt === '') {
            return false;
        }

        try {
            $sent = Carbon::parse(preg_replace('/(\.\d{6})\d+/', '$1', $sentAt));
        } catch (Throwable) {
            return false;
        }

        return $sent->diffInSeconds(now()) > self::LATE_ALERT_SECONDS;
    }

    /** @var array<string, User|null> per-request memo: a batch is mostly one broadcaster */
    private array $usersByTwitchId = [];

    private function userByTwitchId(string $twitchId): ?User
    {
        if (! array_key_exists($twitchId, $this->usersByTwitchId)) {
            $this->usersByTwitchId[$twitchId] = User::where('twitch_id', $twitchId)->first();
        }

        return $this->usersByTwitchId[$twitchId];
    }

    /**
     * Everything a stored notification sets off: token refresh, avatar
     * enrichment, metering, caches, counters, the alert and the broadcasts.
     * Called by ProcessTwitchEvent on the twitch-events queue, after the
     * webhook has answered. $sentAt is Twitch's message timestamp: a
     * notification that reaches us late is stored and counted but fires no
     * alert (an outage backlog must not replay as a wall of stale alerts).
     */
    public function processStoredEvent(TwitchEvent $twitchEvent, array $data, ?string $sentAt = null): void
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

            // Everything below builds with the stored access token and nothing
            // else on this path refreshes it. An expired one is refreshed once,
            // here, before enrichment and the alert. Never ensureValidToken():
            // it calls Twitch's validate endpoint whenever the stored expiry is
            // not past, which on this path is one HTTP call per event.
            if ($user && $user->token_expires_at?->isPast()) {
                $this->refreshExpiredToken($user);
            }

            // Decorate the payload with profile_image_url for every user the
            // event references (acting user, broadcaster, raid source, hype
            // train top_contributions[] and last_contribution, ...). Single
            // batched lookup, memoised per user id, then mutates $event so
            // DB persist, alert render, raw broadcast, and tts message
            // all see the same `<prefix>_avatar` keys.
            //
            // Only when one of the streamer's own templates shows an event
            // avatar: nothing else reads it, and in a raid this lookup was the
            // whole remaining cost of a follow (#343). When it is needed, the
            // warm-up fetches the rows queued behind this one in the same call.
            if ($user && OverlayTemplate::ownerShowsEventAvatars($user->id)) {
                $enrichmentToken = $user->access_token ?: $this->eventSubService->getAppAccessToken();
                if ($enrichmentToken) {
                    $this->twitchService->warmEventAvatars($enrichmentToken, $event, fn () => TwitchEvent::where('user_id', $user->id)
                        ->where('id', '>', $twitchEvent->id)
                        ->orderBy('id')
                        ->limit(99)
                        ->pluck('event_data'));
                    $event = $this->twitchService->enrichEventWithUserAvatars($enrichmentToken, $event);
                    $data['event'] = $event;
                }
            }

            // The row was stored before enrichment, so the webhook could
            // answer without waiting on Helix. Keep it in step with what the
            // alert and the broadcast below see.
            $twitchEvent->forceFill(['event_data' => $event])->save();

            // Meter the inbound event (the usage unit pricing is set against).
            // testCheer() synthetic events use a different path and are not counted.
            if ($user) {
                app(EventMeter::class)->record($user->id);
            }

            if (in_array($eventType, self::STORE_ONLY_EVENTS, true)) {
                return;
            }

            // Clear relevant caches based on event type
            $this->refreshCachesForEvent($eventType, $broadcasterId);

            // The living title re-renders on any event, debounced. Except a
            // channel.update, which IS a title change - possibly ours, possibly
            // the streamer's - and is judged rather than re-rendered.
            if ($user) {
                if ($eventType === 'channel.update') {
                    app(LivingTitleService::class)->handleChannelUpdate($user, $event);
                } else {
                    app(LivingTitleService::class)->schedule($user);
                }
            }

            // Stream session lifecycle and per-stream controls
            if ($user) {
                if ($eventType === 'stream.online') {
                    $this->streamStateMachine->handleEventSubOnline($user, $event);
                } elseif ($eventType === 'stream.offline') {
                    $this->streamStateMachine->handleEventSubOffline($user, $event);
                } else {
                    $this->streamSessionService->handleEvent($user, $eventType, $event);
                }
            }

            if ($broadcasterId) {
                if ($user && $eventType === 'channel.subscribe' && app(GiftSubAbsorber::class)->absorb($user, $event)) {
                    // A recipient of a gift bomb whose gift alert already
                    // announced the whole batch. See GiftSubAbsorber.
                    app(DeliveryLedger::class)->record($twitchEvent, DeliveryOutcome::GiftAbsorbed);
                } elseif ($user && self::isLate($sentAt)) {
                    // Stored and counted above; an alert this stale is noise.
                    app(DeliveryLedger::class)->record($twitchEvent, DeliveryOutcome::Late);
                } elseif ($user) {
                    // Pick the alert template for this event, honoring variant
                    // conditions (e.g. cheer at_least/exactly bits thresholds).
                    $mapping = EventTemplateMapping::resolveForEvent($user->id, $eventType, $event);

                    if ($mapping && $mapping->template) {
                        // The gift alert announces the whole batch, so its
                        // recipients' own sub alerts are absorbed. Set before
                        // rendering: recipients arrive while it renders.
                        if ($eventType === 'channel.subscription.gift') {
                            app(GiftSubAbsorber::class)->expect($user, (int) ($event['total'] ?? 0));
                        }

                        // Render the user's custom alert template
                        $this->renderEventAlert($user, $mapping, $data, $twitchEvent);
                    } else {
                        app(DeliveryLedger::class)->record($twitchEvent, DeliveryOutcome::NoMapping);
                        Log::warning('No enabled template mapping found', [
                            'event_type' => $eventType,
                            'user_id' => $user->id,
                            'mapping_exists' => EventTemplateMapping::where('user_id', $user->id)
                                ->where('event_type', $eventType)->exists(),
                        ]);
                    }
                } else {
                    app(DeliveryLedger::class)->record($twitchEvent, DeliveryOutcome::UnknownUser);
                    Log::warning("User not found for Twitch ID: $broadcasterId");
                }
            } else {
                app(DeliveryLedger::class)->record($twitchEvent, DeliveryOutcome::UnknownUser);
                Log::warning('No broadcaster ID found in event', [
                    'event_type' => $eventType,
                    'event_keys' => array_keys($event),
                ]);
            }

            // Broadcast the event to connected WebSocket clients on the
            // broadcaster's private per-user channel. Without a broadcaster id
            // we have nowhere to send it - drop silently rather than fan out
            // to a guessable global channel.
            if ($broadcasterId) {
                broadcast(new TwitchEventReceived($eventType, $event, (string) $broadcasterId));
            }

        } catch (Exception $e) {
            Log::error("Failed to store Twitch event: {$e->getMessage()}", [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);

            // Still broadcast the event even if database storage fails
            if (! empty($broadcasterId)) {
                broadcast(new TwitchEventReceived($eventType, $event, (string) $broadcasterId));
            }
        } catch (Throwable $e) {
            // A PHP Error (TypeError, a ShouldBroadcastNow handler throwing, ...) is
            // not an Exception and used to land here silently: no log, no row, and a
            // 200 to Twitch so it never retried. Still swallow it - a 5xx makes Twitch
            // redeliver, and twitch_events has no message_id to dedup the redelivery
            // against - but say so.
            Log::error("Unhandled error while handling Twitch event: {$e->getMessage()}", [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * Refresh a stored token whose expiry has passed, updating $user in place.
     * A failed refresh (no refresh token, revoked) backs off ten minutes so an
     * account that cannot be refreshed pays one outbound call per ten minutes,
     * not one per event; its alerts then fail as before and the ledger says
     * token_invalid, which the /wiring token wire turns into "log in again".
     */
    private function refreshExpiredToken(User $user): void
    {
        $backoffKey = "twitch:refresh_failed:{$user->id}";

        if (Cache::has($backoffKey)) {
            return;
        }

        if (! app(TwitchTokenService::class)->refreshUserToken($user)) {
            Cache::put($backoffKey, true, now()->addMinutes(10));
        }
    }

    /**
     * Render user's custom alert template for an event
     */
    private function renderEventAlert(User $user, EventTemplateMapping $mapping, array $eventData, ?TwitchEvent $row = null): void
    {
        // Global mute: muted is muted - no broadcast, no TTS synthesis, no
        // bot message. Guarded here so the live webhook path, replay, and
        // test cheer are all covered by one check.
        if (app(AlertMuteService::class)->isMuted($user)) {
            app(DeliveryLedger::class)->record($row, DeliveryOutcome::Muted);

            return;
        }

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

            // Where it renders: its targets, else the live Alerts overlay,
            // else every static overlay (null). See AlertsOverlay.
            $targetSlugs = app(AlertsOverlay::class)->targetSlugs($mapping->template, $user);

            // One snapshot for everything this alert says: TTS, chat and
            // the payload the overlay renders. See renderAlert().
            $rendered = app(AlertMessageRenderer::class)->renderAlert(
                $user,
                $mapping->template->tts_message,
                $mapping->template->chat_message,
                $templateData,
            );
            $ttsText = $rendered['tts'];
            $templateData = $rendered['data'];

            $alertId = (string) Str::uuid();

            // Broadcast alert data to overlay - only when the overlay has
            // something to do. A chat-only alert is the bot's alone.
            $alert = new AlertTriggered(
                $alertId,
                $mapping->template->html,
                $mapping->template->css,
                $templateData,
                $mapping->duration_ms,
                $user->twitch_id,
                $targetSlugs,
                $mapping->template->slug,
                $ttsText,
                (int) ($mapping->template->tts_delay_ms ?? 0),
                $mapping->template->alert_sound_url,
                $mapping->template->head,
                $mapping->event_type,
            );
            if ($alert->hasOverlayWork()) {
                // The worker closes the ledger row by this id once Reverb answers.
                app(DeliveryLedger::class)->stamp($row, $alertId);
                broadcast($alert);
            } else {
                app(DeliveryLedger::class)->record($row, DeliveryOutcome::ChatOnly);
            }

            // Synthesis is queued (best-effort, never blocks the alert). The
            // overlay correlates the resulting TtsAudioReady by alert_id and
            // schedules playback after tts_delay_ms.
            if ($ttsText !== null) {
                SynthesizeAlertTts::dispatch($alertId, (string) $user->twitch_id, $ttsText, $targetSlugs);
            }

            // Optional bot chat message - queued for the bot to post. Gated on
            // bot_enabled so we never enqueue a message the bot can't deliver
            // (it only joins channels where the flag is on).
            // One row per part, in order: the bot posts rows oldest first.
            if ($user->bot_enabled) {
                foreach (AlertMessageRenderer::chatParts($rendered['chat']) as $part) {
                    BotChatOutbox::create([
                        'user_id' => $user->id,
                        'message' => $part,
                    ]);
                }
            }

        } catch (Exception $e) {
            // The alert should have reached the overlay and could not even be
            // built. Scored, and a refused token gets its own name: it is the
            // one failure the streamer can fix (TenzinNiznet, token expired
            // June 14th, every follow alert since - invisible until this).
            app(DeliveryLedger::class)->record($row, DeliveryOutcome::forRenderException($e));
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
     *
     * @throws RandomException
     */
    public function replay(Request $request, TwitchEvent $twitchEvent)
    {
        $result = $this->replayForUser($request->user(), $twitchEvent);

        return back()->with('message', $result['message'])->with('type', $result['type']);
    }

    /**
     * Replay core, independent of how the request was authenticated (dashboard
     * session or overlay token via the events feed).
     *
     * @return array{message: string, type: string}
     *
     * @throws RandomException
     */
    public function replayForUser(User $user, TwitchEvent $twitchEvent): array
    {
        if ($twitchEvent->user_id !== $user->id) {
            return ['message' => 'You do not own this event.', 'type' => 'error'];
        }

        if (app(AlertMuteService::class)->isMuted($user)) {
            return ['message' => 'Alerts are muted. Unmute alerts to replay events.', 'type' => 'warning'];
        }

        $mapping = EventTemplateMapping::resolveForEvent(
            $user->id,
            $twitchEvent->event_type,
            $twitchEvent->event_data ?? []
        );

        if (! $mapping || ! $mapping->template) {
            return ['message' => 'No active template mapping found for this event type.', 'type' => 'error'];
        }

        $reconstructedData = [
            'subscription' => ['type' => $twitchEvent->event_type],
            'event' => $twitchEvent->event_data,
        ];

        $this->renderEventAlert($user, $mapping, $reconstructedData);
        $randomString = str_pad(random_int(1, 999999), 4, '0', STR_PAD_LEFT);

        return ['message' => "Replayed alert $twitchEvent->event_type (ID: $twitchEvent->id-$randomString)", 'type' => 'success'];
    }

    /**
     * Fire a synthetic channel.cheer event through the normal handler pipeline.
     * Used by the "Send test cheer" button on the integrations page so users can
     * iterate on cheer alerts and bits controls without the Twitch CLI.
     *
     * @throws RandomException
     */
    public function testCheer(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->twitch_id) {
            return response()->json(['error' => 'User not authenticated with Twitch.'], 401);
        }

        $bits = random_int(100, 1000);
        $cheererName = 'TestCheerer';
        $message = "cheer$bits test cheer from the integrations page!";

        $event = [
            'broadcaster_user_id' => (string) $user->twitch_id,
            'broadcaster_user_login' => $user->name,
            'broadcaster_user_name' => $user->name,
            'user_id' => '999999999',
            'user_login' => strtolower($cheererName),
            'user_name' => $cheererName,
            'is_anonymous' => false,
            'message' => $message,
            'bits' => $bits,
        ];

        $data = [
            'subscription' => ['type' => 'channel.cheer'],
            'event' => $event,
        ];

        $testEvent = TwitchEvent::create([
            'user_id' => $user->id,
            'event_type' => 'channel.cheer',
            'event_data' => $event,
            'twitch_timestamp' => now(),
            'processed' => true,
        ]);

        // Show briefly in event logs, then vanish - same UX as StreamElements'
        // test tips. A minute is long enough for the user to see the row before
        // the queued job reaps it.
        DeleteTestTwitchEvent::dispatch($testEvent->id)->delay(now()->addMinute());

        $wasLive = StreamState::forUser($user)->isConfidentlyLive();

        $this->streamSessionService->handleEvent($user, 'channel.cheer', $event);

        $mapping = EventTemplateMapping::resolveForEvent($user->id, 'channel.cheer', $event);

        $muted = app(AlertMuteService::class)->isMuted($user);

        $alertFired = false;
        if (! $muted && $mapping && $mapping->template) {
            $this->renderEventAlert($user, $mapping, $data, $testEvent);
            $alertFired = true;
        }

        broadcast(new TwitchEventReceived('channel.cheer', $event, (string) $user->twitch_id));

        return response()->json([
            'ok' => true,
            'bits' => $bits,
            'cheerer_name' => $cheererName,
            'alert_fired' => $alertFired,
            'alerts_muted' => $muted,
            'controls_updated' => $wasLive,
            'is_live' => $wasLive,
        ]);
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
