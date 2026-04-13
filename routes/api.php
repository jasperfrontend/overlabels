<?php

use App\Http\Controllers\Api\ExternalWebhookController;
use App\Http\Controllers\Api\Internal\BotChannelController;
use App\Http\Controllers\Api\Internal\BotCommandController;
use App\Http\Controllers\Api\Internal\BotControlController;
use App\Http\Controllers\Api\Internal\BotTokenController;
use App\Http\Controllers\Api\RailwayWebhookController;
use App\Http\Controllers\OverlayTemplateController;
use App\Http\Controllers\TemplateTagController;
use App\Http\Controllers\TwitchEventSubController;
use App\Http\Middleware\CheckBanned;
use App\Jobs\SetupUserEventSubSubscriptions;
use App\Models\ExternalIntegration;
use App\Models\User;
use App\Models\UserEventsubSubscription;
use App\Services\TwitchApiService;
use App\Services\TwitchEventSubService;
use App\Services\UserEventSubManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('/overlay')->group(function () {
    Route::post('/render', [OverlayTemplateController::class, 'renderAuthenticated'])
        ->name('api.overlay.render')
        ->middleware(['throttle:overlay', 'rate.limit.overlay', 'lockdown'])
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
});

// Get all template tags (API endpoint)
Route::get('/template-tags', [TemplateTagController::class, 'getAllTags'])
    ->name('tags.api.all')
    ->middleware('auth:sanctum');

// Get job status for template tag operations
Route::get('/template-tags/jobs/{jobType?}', [TemplateTagController::class, 'getJobStatus'])
    ->name('tags.api.jobs')
    ->middleware('auth:sanctum');

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

// Internal endpoint for StreamElements Node.js listener to fetch active integrations.
// Returns the per-user JWT used for Socket.IO authentication at realtime.streamelements.com.
Route::get('/internal/streamelements/integrations', function () {
    $secret = config('services.streamelements.listener_secret');

    if (empty($secret) || ! hash_equals($secret, (string) request()->header('X-Internal-Secret', ''))) {
        abort(403);
    }

    $integrations = ExternalIntegration::where('service', 'streamelements')
        ->where('enabled', true)
        ->get()
        ->map(function ($integration) {
            $credentials = $integration->getCredentialsDecrypted();

            return [
                'id' => $integration->id,
                'user_id' => $integration->user_id,
                'webhook_token' => $integration->webhook_token,
                'jwt_token' => $credentials['jwt_token'] ?? null,
                'listener_secret' => $credentials['listener_secret'] ?? null,
            ];
        })
        ->filter(fn ($i) => $i['jwt_token'] && $i['listener_secret'])
        ->values();

    return response()->json(['integrations' => $integrations]);
})
    ->middleware(['throttle:10,1'])
    ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class, CheckBanned::class]);

// Internal endpoints for the @overlabels Twitch bot service (separate repo/Railway service).
// Auth: X-Internal-Secret header, validated by bot.internal middleware.
Route::prefix('/internal/bot')
    ->middleware(['bot.internal', 'throttle:30,1'])
    ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class, CheckBanned::class])
    ->group(function () {
        Route::get('/channels', [BotChannelController::class, 'index']);
        Route::get('/tokens', [BotTokenController::class, 'show']);
        Route::post('/tokens', [BotTokenController::class, 'store']);
        Route::get('/commands', [BotCommandController::class, 'index']);
        Route::get('/controls/{login}/{key}', [BotControlController::class, 'show'])
            ->where(['login' => '[a-z0-9_]+', 'key' => '[a-z][a-z0-9_]{0,49}']);
        Route::post('/controls/{login}/{key}', [BotControlController::class, 'update'])
            ->where(['login' => '[a-z0-9_]+', 'key' => '[a-z][a-z0-9_]{0,49}']);
    });

// Railway deployment webhook - triggers version update broadcast
Route::post('/webhooks/railway/{token}', [RailwayWebhookController::class, 'handle'])
    ->middleware(['throttle:10,1'])
    ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class, CheckBanned::class])
    ->name('webhooks.railway');

// External service webhooks - no auth/CSRF, rate-limited
Route::get('/webhooks/{service}/{webhookToken}', [ExternalWebhookController::class, 'show'])
    ->middleware(['throttle:60,1'])
    ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class, CheckBanned::class])
    ->name('webhooks.external.show');
Route::post('/webhooks/{service}/{webhookToken}', [ExternalWebhookController::class, 'handle'])
    ->middleware(['throttle:60,1'])
    ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class, CheckBanned::class])
    ->name('webhooks.external');
// EventSub health check endpoint for external cron services
Route::get('/eventsub-health-check', function () {
    try {
        $manager = app(UserEventSubManager::class);
        $stats = $manager->getGlobalStats();

        // Get failed subscriptions
        $failedSubs = UserEventsubSubscription::whereIn('status', [
            'webhook_callback_verification_failed',
            'notification_failures_exceeded',
            'authorization_revoked',
            'user_removed',
        ])->with('user')->get();

        // Auto-fix if there are failed subscriptions
        if ($failedSubs->count() > 0) {
            foreach ($failedSubs->groupBy('user_id') as $userId => $userFailedSubs) {
                $user = $userFailedSubs->first()->user;
                SetupUserEventSubSubscriptions::dispatch($user, true);
            }
        }

        // Check for users who should be connected but aren't
        $usersNeedingSetup = User::where('eventsub_auto_connect', true)
            ->whereNull('eventsub_connected_at')
            ->get();

        foreach ($usersNeedingSetup as $user) {
            SetupUserEventSubSubscriptions::dispatch($user);
        }

        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'stats' => $stats,
            'actions_taken' => [
                'failed_subscriptions_renewed' => $failedSubs->count(),
                'users_auto_setup' => $usersNeedingSetup->count(),
            ],
        ]);

    } catch (Exception $e) {
        Log::error('EventSub health check failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'status' => 'error',
            'timestamp' => now()->toISOString(),
            'error' => $e->getMessage(),
        ], 500);
    }
})->withoutMiddleware([EnsureFrontendRequestsAreStateful::class, CheckBanned::class]);
