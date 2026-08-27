<?php

use App\Http\Controllers\Api\DeployWebhookController;
use App\Http\Controllers\Api\EventFeedController;
use App\Http\Controllers\Api\ExternalWebhookController;
use App\Http\Controllers\Api\GpsSessionMapController;
use App\Http\Controllers\Api\Internal\BotAccountageController;
use App\Http\Controllers\Api\Internal\BotChannelController;
use App\Http\Controllers\Api\Internal\BotChatAdminController;
use App\Http\Controllers\Api\Internal\BotChatStatsController;
use App\Http\Controllers\Api\Internal\BotCommandController;
use App\Http\Controllers\Api\Internal\BotCommandMapController;
use App\Http\Controllers\Api\Internal\BotControlController;
use App\Http\Controllers\Api\Internal\BotFollowageController;
use App\Http\Controllers\Api\Internal\BotGamejamActionController;
use App\Http\Controllers\Api\Internal\BotListActionController;
use App\Http\Controllers\Api\Internal\BotListAppenderController;
use App\Http\Controllers\Api\Internal\BotOutboxController;
use App\Http\Controllers\Api\Internal\BotPresenceController;
use App\Http\Controllers\Api\Internal\BotRecipeTriggerController;
use App\Http\Controllers\Api\Internal\BotSettingsController;
use App\Http\Controllers\Api\Internal\BotTokenController;
use App\Http\Controllers\Api\ListReadController;
use App\Http\Controllers\ExpressionTagController;
use App\Http\Controllers\OverlayBroadcastingAuthController;
use App\Http\Controllers\OverlayTemplateController;
use App\Http\Controllers\TemplateTagController;
use App\Http\Controllers\TwitchEventSubController;
use App\Http\Middleware\CheckBanned;
use App\Models\ExternalIntegration;
use App\Services\TwitchApiService;
use App\Services\TwitchEventSubService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public, read-only JSON view of a List for external consumers (custom wheel
// pages, web components, scripts). Token-authed via the same OverlayAccessToken
// overlays use, passed as a ?token= query param so it drops into a browser-
// source URL. Lives under /api/* on purpose: that's the only path Laravel's
// default CORS covers, so a cross-origin browser fetch works; a web.php route
// would be CORS-blocked and dragged into Sanctum's stateful handling. Stateless
// (token, not session), so EnsureFrontendRequestsAreStateful is shed like the
// overlay render route. See App\Http\Controllers\Api\ListReadController.
Route::get('/lists/{slug}', [ListReadController::class, 'show'])
    ->name('api.lists.show')
    ->where('slug', '[a-z][a-z0-9_]{0,49}')
    ->middleware(['throttle:overlay', 'lockdown'])
    ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class]);

// Token-authed events feed for /events/feed (phone-friendly, no Twitch login
// needed). Same OverlayAccessToken as overlays; token travels as a ?token=
// query param (GET) or JSON body field (POST), never in the URL path. Reading
// requires the `read` ability, the writes (mute toggle, event replay) require
// `write` - the first endpoints to enforce token abilities. Stateless like the
// overlay render route. See App\Http\Controllers\Api\EventFeedController.
Route::get('/events', [EventFeedController::class, 'index'])
    ->name('api.events.index')
    ->middleware(['throttle:overlay', 'lockdown'])
    ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class]);
Route::post('/events/mute', [EventFeedController::class, 'mute'])
    ->name('api.events.mute')
    ->middleware(['throttle:overlay', 'lockdown'])
    ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class]);
Route::post('/events/{twitchEvent}/replay', [EventFeedController::class, 'replayTwitch'])
    ->name('api.events.replay')
    ->whereNumber('twitchEvent')
    ->middleware(['throttle:overlay', 'lockdown'])
    ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class]);
Route::post('/external-events/{externalEvent}/replay', [EventFeedController::class, 'replayExternal'])
    ->name('api.external-events.replay')
    ->whereNumber('externalEvent')
    ->middleware(['throttle:overlay', 'lockdown'])
    ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class]);

Route::prefix('/overlay')->group(function () {
    Route::post('/render', [OverlayTemplateController::class, 'renderAuthenticated'])
        ->name('api.overlay.render')
        ->middleware(['throttle:overlay', 'rate.limit.overlay', 'lockdown'])
        ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class]);

    // Overlay-token-authenticated broadcasting auth endpoint. Lets a session-
    // less overlay subscribe to its owner's private alerts/twitch-events
    // channels by presenting a valid OverlayAccessToken. See controller for
    // the channel allowlist.
    Route::post('/broadcasting/auth', [OverlayBroadcastingAuthController::class, 'authenticate'])
        ->name('api.overlay.broadcasting.auth')
        ->middleware(['throttle:overlay', 'lockdown'])
        ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class]);

    // Returns Twitch global + channel emotes as [{code, url}] for frontend emote parsing.
    // Uses server-side app credentials so client IDs/secrets never reach the browser.
    // Cached 24 h server-side; rate-limited to prevent abuse.
    Route::get('/emotes/{channelId}', function (string $channelId) {
        if (! ctype_digit($channelId)) {
            return response()->json(['error' => 'Invalid channel ID'], 400);
        }

        $emotes = Cache::remember(
            "twitch_channel_emotes_{$channelId}",
            now()->addHours(24),
            function () use ($channelId) {
                $appToken = app(TwitchEventSubService::class)->getAppAccessToken();
                if (! $appToken) {
                    return [];
                }

                return app(TwitchApiService::class)->getChannelEmotes($appToken, $channelId);
            }
        );

        return response()->json($emotes);
    })->middleware(['throttle:60,1'])->withoutMiddleware([EnsureFrontendRequestsAreStateful::class]);

    // Returns Twitch chat badge art as { global: {...}, channel: {...} }, each
    // keyed "set/version" to match the IRC `badges` tag (`moderator/1`).
    // Same shape as the emote endpoint above: server-side app credentials,
    // cached 24 h, rate-limited.
    //
    // global and channel stay SEPARATE because a Shared Chat message carries a
    // collab partner's badge versions, whose channel-specific art lives in a
    // manifest we have not fetched. Foreign messages resolve against `global`
    // only, so they can never render this channel's subscriber emblem for
    // someone who subscribes elsewhere.
    Route::get('/badges/{channelId}', function (string $channelId) {
        if (! ctype_digit($channelId)) {
            return response()->json(['error' => 'Invalid channel ID'], 400);
        }

        $badges = Cache::remember(
            "twitch_channel_badges_{$channelId}",
            now()->addHours(24),
            function () use ($channelId) {
                $appToken = app(TwitchEventSubService::class)->getAppAccessToken();
                if (! $appToken) {
                    return ['global' => [], 'channel' => []];
                }

                return app(TwitchApiService::class)->getChannelBadges($appToken, $channelId);
            }
        );

        return response()->json($badges);
    })->middleware(['throttle:60,1'])->withoutMiddleware([EnsureFrontendRequestsAreStateful::class]);
});

// Get all template tags (API endpoint)
Route::get('/template-tags', [TemplateTagController::class, 'getAllTags'])
    ->name('tags.api.all')
    ->middleware('auth:sanctum');

// Resolve the user's live Twitch tag values for the expression-builder preview.
// Piggybacks on TwitchApiService's snapshot cache; typically responds in ms.
Route::get('/expression/tags', [ExpressionTagController::class, 'index'])
    ->name('expression.tags')
    ->middleware(['auth:sanctum', 'throttle:60,1']);

// Get job status for template tag operations

// GPS session map endpoints
Route::get('/gps-sessions/{sessionId}/geojson', [GpsSessionMapController::class, 'authenticatedGeoJson'])
    ->middleware(['auth:sanctum', 'throttle:60,1']);

// Public map API endpoints (no auth, checks map_sharing_enabled). The slug
// is a Sqids-encoded Twitch ID so the numeric ID never appears in network
// requests or WebSocket channel names.
Route::prefix('/map')->group(function () {
    Route::get('/{slug}/position', [GpsSessionMapController::class, 'currentPosition'])
        ->middleware(['throttle:60,1'])
        ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class]);
    Route::get('/{slug}/{sessionId}/geojson', [GpsSessionMapController::class, 'publicSessionGeoJson'])
        ->middleware(['throttle:30,1'])
        ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class]);
    Route::get('/{slug}/{sessionId}/meta', [GpsSessionMapController::class, 'publicSessionMeta'])
        ->middleware(['throttle:60,1'])
        ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class]);
});

// Twitch webhook endpoint - must be accessible without authentication or CSRF
Route::post('/twitch/webhook', [TwitchEventSubController::class, 'webhook'])
    ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class, CheckBanned::class]);

// Internal endpoint for StreamLabs Node.js listener to fetch active integrations
Route::get('/internal/streamlabs/integrations', function () {
    $secret = config('services.streamlabs.listener_secret');

    if (empty($secret) || ! hash_equals($secret, (string) request()->header('X-Internal-Secret', ''))) {
        abort(403);
    }

    $integrations = ExternalIntegration::where('service', 'streamlabs')
        ->where('enabled', true)
        ->get()
        ->map(function ($integration) {
            $credentials = $integration->getCredentialsDecrypted();

            return [
                'id' => $integration->id,
                'user_id' => $integration->user_id,
                'webhook_token' => $integration->webhook_token,
                'socket_token' => $credentials['socket_token'] ?? null,
                'listener_secret' => $credentials['listener_secret'] ?? null,
            ];
        })
        ->filter(fn ($i) => $i['socket_token'] && $i['listener_secret'])
        ->values();

    return response()->json(['integrations' => $integrations]);
})
    ->middleware(['throttle:10,1'])
    ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class, CheckBanned::class]);

// Internal endpoints for the @overlabels Twitch bot service (separate repo/Railway service).
// Auth: X-Internal-Secret header, validated by bot.internal middleware.
// Two throttle buckets: gamejam votes get their own per-channel bucket so a
// busy raid can't starve token/outbox/control polls. See AppServiceProvider.
Route::prefix('/internal/bot')
    ->middleware(['bot.internal'])
    ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class, CheckBanned::class])
    ->group(function () {
        Route::middleware('throttle:bot-internal')->group(function () {
            Route::get('/channels', [BotChannelController::class, 'index']);
            Route::post('/presence', [BotPresenceController::class, 'store']);
            Route::get('/tokens', [BotTokenController::class, 'show']);
            Route::post('/tokens', [BotTokenController::class, 'store']);
            Route::get('/commands', [BotCommandMapController::class, 'index']);
            Route::post('/commands/fire', [BotCommandController::class, 'fire']);
            Route::post('/recipe-triggers/fire', [BotRecipeTriggerController::class, 'fire']);
            Route::post('/list-appenders/fire', [BotListAppenderController::class, 'fire']);
            Route::post('/list-actions/fire', [BotListActionController::class, 'fire']);
            Route::post('/manage', [BotChatAdminController::class, 'handle']);
            Route::post('/followage', [BotFollowageController::class, 'handle']);
            Route::post('/accountage', [BotAccountageController::class, 'handle']);
            Route::get('/controls/{login}/{key}', [BotControlController::class, 'show'])
                ->where(['login' => '[a-z0-9_]+', 'key' => '[a-z][a-z0-9_]{0,49}']);
            Route::post('/controls/{login}/{key}', [BotControlController::class, 'update'])
                ->where(['login' => '[a-z0-9_]+', 'key' => '[a-z][a-z0-9_]{0,49}']);
            Route::post('/chat-stats/{login}', [BotChatStatsController::class, 'store'])
                ->where('login', '[a-z0-9_]+');
            Route::get('/outbox', [BotOutboxController::class, 'index']);
            Route::post('/settings/{login}/controls-access', [BotSettingsController::class, 'setControlsAccess'])
                ->where('login', '[a-z0-9_]+');
        });

        Route::middleware('throttle:bot-gamejam-action')->group(function () {
            Route::post('/gamejam/action/{login}', [BotGamejamActionController::class, 'handle'])
                ->where('login', '[a-z0-9_]+');
        });
    });

// Deploy webhook - called by GH Actions after a successful kamal deploy
Route::post('/webhooks/deploy/{token}', [DeployWebhookController::class, 'handle'])
    ->middleware(['throttle:10,1'])
    ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class, CheckBanned::class])
    ->name('webhooks.deploy');

// External service webhooks - no auth/CSRF, rate-limited
Route::get('/webhooks/{service}/{webhookToken}', [ExternalWebhookController::class, 'show'])
    ->middleware(['throttle:60,1'])
    ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class, CheckBanned::class])
    ->name('webhooks.external.show');
Route::post('/webhooks/{service}/{webhookToken}', [ExternalWebhookController::class, 'handle'])
    ->middleware(['throttle:60,1'])
    ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class, CheckBanned::class])
    ->name('webhooks.external');
// Unmatched /api/* would otherwise fall through to the `Route::fallback` in
// web.php and come back as the HTML 404 page. A client doing `if (response.ok)`
// saw 200, then choked parsing `<!doctype html>` - "route does not exist" turned
// into a parse error at the call site with nothing pointing at the cause.
//
// Fallback routes always match last, and this one carries the `api` prefix, so
// it only claims requests the web fallback would have taken anyway.
Route::fallback(fn () => response()->json(['message' => 'Not Found.'], 404));
