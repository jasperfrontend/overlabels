<?php

use App\Events\UserRegistered;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventTemplateMappingController;
use App\Http\Controllers\ExternalEventController;
use App\Http\Controllers\ExternalEventTemplateMappingController;
use App\Http\Controllers\GpsSessionController;
use App\Http\Controllers\IntegrationSuggestionController;
use App\Http\Controllers\KitController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OverlayAccessTokenController;
use App\Http\Controllers\OverlayControlController;
use App\Http\Controllers\OverlayTemplateController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Settings\IntegrationController;
use App\Http\Controllers\Settings\StreamLabsIntegrationController;
use App\Http\Controllers\TemplateTagController;
use App\Http\Controllers\TestingController;
use App\Http\Controllers\TwitchDataController;
use App\Http\Controllers\TwitchEventController;
use App\Http\Controllers\TwitchEventSubController;
use App\Jobs\SetupUserEventSubSubscriptions;
use App\Models\Game;
use App\Models\User;
use App\Services\TemplateDataMapperService;
use App\Services\TwitchApiService;
use App\Services\TwitchTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

Route::get('/', function (TemplateDataMapperService $mapper) {
    return Inertia::render('Welcome', [
        'sampleData' => $mapper->getSampleTemplateData(),
    ]);
})->name('home');

// gamejam routes
Route::get('/gamejam', function () {
    return Inertia::render('gamejam/index');
})->name('gamejam');

Route::get('/gamejam/live/{login}', function (string $login) {
    $login = strtolower($login);

    $user = User::where('bot_enabled', true)
        ->whereNotNull('twitch_data')
        ->get()
        ->first(fn (User $u) => strtolower($u->twitch_data['login'] ?? '') === $login);

    abort_unless($user, 404);

    $game = Game::activeFor($user);

    return Inertia::render('gamejam/live', [
        'broadcasterId' => (string) $user->twitch_id,
        'broadcasterLogin' => $login,
        'snapshot' => $game ? [
            'game' => [
                'id' => $game->id,
                'status' => $game->status,
                'current_round' => $game->current_round,
                'player_hp' => $game->player_hp,
                'round_started_at' => $game->round_started_at?->toISOString(),
            ],
            'joiners' => $game->joiners()
                ->orderBy('joined_round')
                ->get()
                ->map(fn ($j) => [
                    'twitch_user_id' => $j->twitch_user_id,
                    'username' => $j->username,
                    'status' => $j->status,
                    'joined_round' => $j->joined_round,
                    'current_vote' => $j->current_vote,
                    'last_vote_round' => $j->last_vote_round,
                    'blocks_remaining' => $j->blocks_remaining,
                ])
                ->all(),
        ] : null,
    ]);
})->where('login', '[a-z0-9_]+')->name('gamejam.live');

Route::get('/privacy', function () {
    return Inertia::render('Privacy');
})->name('privacy');

Route::get('/terms', function () {
    return Inertia::render('Terms');
})->name('terms');

Route::get('/help', function () {
    return Inertia::render('help/Index');
})->name('help');

Route::get('/help/conditionals', function () {
    return Inertia::render('help/Conditionals');
})->name('help.conditionals');

Route::get('/help/controls', function () {
    return Inertia::render('help/Controls');
})->name('help.controls');

Route::get('/help/formatting', function () {
    return Inertia::render('help/Formatting');
})->name('help.formatting');

Route::get('/help/math', function () {
    return Inertia::render('help/Math');
})->name('help.math');

Route::get('/help/resources', function () {
    return Inertia::render('help/Resources');
})->name('help.resources');

Route::get('/help/why-kofi', function () {
    return Inertia::render('help/WhyKofi');
})->name('help.why-kofi');

Route::get('/help/manifesto', function () {
    return Inertia::render('help/Manifesto');
})->name('help.manifesto');

Route::get('/help/bot', function () {
    return Inertia::render('help/bot/Index');
})->name('help.bot');

Route::get('/help/bot/commands', function () {
    return Inertia::render('help/bot/Commands');
})->name('help.bot.commands');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth.redirect'])
    ->name('dashboard.index');

Route::get('/dashboard/recents', [DashboardController::class, 'recentActivity'])
    ->middleware(['auth.redirect'])
    ->name('dashboard.recents');

Route::get('/dashboard/gps-sessions', [GpsSessionController::class, 'index'])
    ->middleware(['auth.redirect'])
    ->name('dashboard.gps-sessions');

Route::delete('/dashboard/gps-sessions/{sessionId}', [GpsSessionController::class, 'destroy'])
    ->middleware(['auth.redirect'])
    ->name('dashboard.gps-sessions.destroy');

Route::get('/dashboard/events', [DashboardController::class, 'recentEvents'])
    ->middleware(['auth.redirect'])
    ->name('dashboard.events');

Route::get('/login', [PageController::class, 'notAuthorized'])
    ->middleware(['guest'])
    ->name('login');

Route::get('/twitchdata', [TwitchDataController::class, 'index'])
    ->middleware(['auth.redirect', 'twitch.token'])
    ->name('twitchdata');

Route::get('/twitchdata/refresh/expensive', [TwitchDataController::class, 'getLiveTwitchData'])
    ->middleware(['auth.redirect', 'twitch.token'])
    ->name('twitchdata.refresh.expensive');

Route::post('/twitchdata/refresh/all', [TwitchDataController::class, 'refreshAllTwitchApiData'])
    ->middleware(['auth.redirect', 'twitch.token'])
    ->name('twitchdata.refresh.all');

Route::post('/twitchdata/refresh/user', [TwitchDataController::class, 'refreshUserInfoData'])
    ->middleware(['auth.redirect', 'twitch.token'])
    ->name('twitchdata.refresh.user');

Route::post('/twitchdata/refresh/info', [TwitchDataController::class, 'refreshChannelInfoData'])
    ->middleware(['auth.redirect', 'twitch.token'])
    ->name('twitchdata.refresh.info');

Route::post('/twitchdata/refresh/following', [TwitchDataController::class, 'refreshFollowedChannelsData'])
    ->middleware(['auth.redirect', 'twitch.token'])
    ->name('twitchdata.refresh.following');

Route::post('/twitchdata/refresh/followers', [TwitchDataController::class, 'refreshChannelFollowersData'])
    ->middleware(['auth.redirect', 'twitch.token'])
    ->name('twitchdata.refresh.followers');

Route::post('/twitchdata/refresh/subscribers', [TwitchDataController::class, 'refreshSubscribersData'])
    ->middleware(['auth.redirect', 'twitch.token'])
    ->name('twitchdata.refresh.subscribers');

Route::post('/twitchdata/refresh/goals', [TwitchDataController::class, 'refreshGoalsData'])
    ->middleware(['auth.redirect', 'twitch.token'])
    ->name('twitchdata.refresh.goals');

Route::get('/overlay/{slug}', [OverlayTemplateController::class, 'serveAuthenticated'])
    ->name('overlay.authenticated')
    ->where('slug', '[a-z0-9]+(-[a-z0-9]+)*');

Route::get('/overlay/{slug}/public', [OverlayTemplateController::class, 'servePublic'])
    ->name('overlay.public')
    ->where('slug', '[a-z0-9]+(-[a-z0-9]+)*');

Route::get('/overlay/{slug}/public/screenshot', [OverlayTemplateController::class, 'servePublicScreenshot'])
    ->name('overlay.public.screenshot')
    ->where('slug', '[a-z0-9]+(-[a-z0-9]+)*');

// Initiate login with Twitch
Route::get('/auth/redirect/twitch', function (Request $request) {
    // Preserve the intended URL during OAuth flow
    if ($request->session()->has('url.intended')) {
        // Session will persist through OAuth flow
    }

    /** @var AbstractProvider $driver */
    $driver = Socialite::driver('twitch');

    return $driver->scopes([
        'user:read:email',            // To get email
        'user:read:follows',          // Who they follow
        'user:read:subscriptions',    // (requires Partner status)
        'channel:read:subscriptions', // Who is subscribed to them
        'channel:read:redemptions',   // Channel point stuff
        'channel:read:goals',         // Follower/sub goals
        'channel:moderate',           // Required for some EventSub actions
        'moderator:read:followers',   // Channel follower details
    ])->redirect();
});

// Refresh Twitch token endpoint
Route::post('/auth/refresh/twitch', function () {
    $user = Auth::user();

    if (! $user) {
        return response()->json(['error' => 'Not authenticated'], 401);
    }

    $tokenService = app(TwitchTokenService::class);

    if ($tokenService->refreshUserToken($user)) {
        return response()->json(['success' => true, 'message' => 'Token refreshed successfully']);
    }

    return response()->json(['error' => 'Failed to refresh token', 'requires_reauth' => true], 401);
})->middleware('auth')->name('auth.refresh.twitch');

Route::get('/auth/callback/twitch', function () {
    try {
        $twitchUser = Socialite::driver('twitch')->user();

        $twitchService = new TwitchApiService;
        $extendedData = $twitchService->getExtendedUserData(
            $twitchUser->token,
            $twitchUser->getId()
        );

        // Always match by Twitch ID only (including soft-deleted users)
        $user = User::withTrashed()->where('twitch_id', $twitchUser->getId())->first();
        $isNewUser = ! $user;

        // Restore soft-deleted users on re-login
        if ($user && $user->trashed()) {
            $user->restore();
        }

        if (! $user) {
            // Create a new user if not found
            $user = User::create([
                'name' => $twitchUser->getNickname() ?? $twitchUser->getName(),
                'email' => $twitchUser->getEmail(), // Store it, but don't use it to match accounts. We'll use Twitch ID instead.
                'twitch_id' => $twitchUser->getId(),
                'avatar' => $twitchUser->getAvatar(),
                'access_token' => $twitchUser->token,
                'refresh_token' => $twitchUser->refreshToken ?? null,
                'token_expires_at' => now()->addSeconds($twitchUser->expiresIn ?? 3600),
                'twitch_data' => array_merge($twitchUser->user, $extendedData),
                'email_verified_at' => now(),
                'password' => bcrypt(Str::random(32)),
                'webhook_secret' => bin2hex(random_bytes(32)),
                'eventsub_auto_connect' => true, // New users default to auto-connect
            ]);

            // Dispatch event for new user registration
            UserRegistered::dispatch($user);
        } else {
            // Existing user — update tokens and data
            $updateData = [
                'name' => $twitchUser->getNickname() ?? $twitchUser->getName(),
                'avatar' => $twitchUser->getAvatar(),
                'access_token' => $twitchUser->token,
                'refresh_token' => $twitchUser->refreshToken ?? null,
                'token_expires_at' => now()->addSeconds($twitchUser->expiresIn ?? 3600),
                'twitch_data' => array_merge($twitchUser->user, $extendedData),
            ];

            // Backfill webhook_secret for users created before per-user secrets
            if (! $user->webhook_secret) {
                $updateData['webhook_secret'] = bin2hex(random_bytes(32));
            }

            $user->update($updateData);
        }

        Auth::login($user);

        // Block banned users from logging in
        if ($user->isBanned()) {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect('/banned');
        }

        // Auto-setup EventSub subscriptions for new users or users who have auto-connect enabled
        if (($isNewUser || $user->eventsub_auto_connect) && ! $user->eventsub_connected_at) {
            try {
                Log::info('Dispatching EventSub setup for user', [
                    'user_id' => $user->id,
                    'twitch_id' => $user->twitch_id,
                    'is_new_user' => $isNewUser,
                ]);

                // Dispatch the job to setup EventSub subscriptions
                SetupUserEventSubSubscriptions::dispatch($user);

            } catch (Exception $e) {
                Log::warning('Failed to dispatch EventSub setup job', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail authentication if EventSub setup fails
            }
        }

        // Redirect to the intended URL if it's a safe full-page destination.
        // JSON-returning endpoints (onboarding/status, api/, etc.) must never be
        // used here — they get stored as url.intended when fetch() follows a 302
        // redirect to /login, which would land the user on a raw JSON response.
        $intended = session()->pull('url.intended');
        if ($intended) {
            $path = parse_url($intended, PHP_URL_PATH) ?? '';
            $jsonPaths = ['/onboarding/', '/api/'];
            $isSafe = ! collect($jsonPaths)->contains(fn ($prefix) => str_starts_with($path, $prefix));
            if ($isSafe) {
                return redirect($intended);
            }
        }

        return redirect('/dashboard');

    } catch (Exception $e) {
        Log::error('Twitch OAuth callback failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return redirect('/')->with('error', 'Authentication failed. Please try again.');
    }
});

// StreamLabs OAuth callback
Route::get('/auth/callback/streamlabs', [StreamLabsIntegrationController::class, 'callback'])
    ->middleware('auth.redirect')
    ->name('auth.callback.streamlabs');

Route::post('/logout', function () {
    Auth::logout();

    return redirect('/');
});

Route::middleware('auth.redirect')->group(function () {

    // Onboarding
    Route::prefix('onboarding')->name('onboarding.')->group(function () {
        Route::get('/status', [OnboardingController::class, 'status'])->name('status');
        Route::post('/token', [OnboardingController::class, 'createToken'])->name('token');
        Route::post('/complete', [OnboardingController::class, 'complete'])->name('complete');
    });

    // Testing Guide
    Route::get('/testing', [TestingController::class, 'index'])->name('testing.index');

    // Access Token Management
    Route::prefix('tokens')->name('tokens.')->group(function () {
        Route::get('/', [OverlayAccessTokenController::class, 'index'])->name('index');
        Route::post('/', [OverlayAccessTokenController::class, 'store'])->name('store');
        Route::post('/{token}/revoke', [OverlayAccessTokenController::class, 'revoke'])->name('revoke');
        Route::delete('/{token}', [OverlayAccessTokenController::class, 'destroy'])->name('destroy');
    });

    // Template Management - Full resource routes
    Route::prefix('templates')->name('templates.')->group(function () {
        Route::get('/', [OverlayTemplateController::class, 'index'])->name('index');
        Route::get('/create', [OverlayTemplateController::class, 'create'])->name('create');
        Route::post('/', [OverlayTemplateController::class, 'store'])->name('store');
        Route::get('/{template}', [OverlayTemplateController::class, 'show'])->name('show');
        Route::get('/{template}/edit', [OverlayTemplateController::class, 'edit'])->name('edit');
        Route::put('/{template}', [OverlayTemplateController::class, 'update'])->name('update');
        Route::delete('/{template}', [OverlayTemplateController::class, 'destroy'])->name('destroy');
        Route::post('/{template}/fork', [OverlayTemplateController::class, 'fork'])->name('fork');
        Route::put('/{template}/target-overlays', [OverlayTemplateController::class, 'updateTargetOverlays'])->name('target-overlays');
        Route::put('/{template}/screenshot', [OverlayTemplateController::class, 'updateScreenshot'])->name('screenshot');
    });

    // Integration Suggestions (rate limited: 3 per hour per user)
    Route::post('/integration-suggestions', [IntegrationSuggestionController::class, 'store'])
        ->middleware('throttle:3,60')
        ->name('integration-suggestions.store');

    // Controls Management
    Route::prefix('templates/{template}/controls')
        ->name('controls.')
        ->group(function () {
            Route::get('/', [OverlayControlController::class, 'index'])->name('index');
            Route::post('/', [OverlayControlController::class, 'store'])->name('store');
            Route::post('/import', [OverlayControlController::class, 'importForkedControls'])->name('import');
            Route::put('/{control}', [OverlayControlController::class, 'update'])->name('update');
            Route::delete('/{control}', [OverlayControlController::class, 'destroy'])->name('destroy');
            Route::post('/{control}/value', [OverlayControlController::class, 'setValue'])->name('value');
        });

    // Kit Management
    Route::prefix('kits')->name('kits.')->group(function () {
        Route::get('/', [KitController::class, 'index'])->name('index');
        Route::get('/create', [KitController::class, 'create'])->name('create');
        Route::post('/', [KitController::class, 'store'])->name('store');
        Route::get('/{kit}', [KitController::class, 'show'])->name('show');
        Route::get('/{kit}/edit', [KitController::class, 'edit'])->name('edit');
        Route::put('/{kit}', [KitController::class, 'update'])->name('update');
        Route::delete('/{kit}', [KitController::class, 'destroy'])->name('destroy');
        Route::post('/{kit}/fork', [KitController::class, 'fork'])->name('fork');
    });

    // Event Template Mapping Management
    Route::prefix('alerts')->name('events.')->group(function () {
        Route::get('/', [EventTemplateMappingController::class, 'index'])->name('index');
        Route::post('/', [EventTemplateMappingController::class, 'store'])->name('store');
        Route::put('/bulk', [EventTemplateMappingController::class, 'updateMultiple'])->name('update.bulk');
        Route::delete('/{eventType}', [EventTemplateMappingController::class, 'destroy'])->name('destroy');

        // External service event mappings
        Route::prefix('external')->name('external.')->group(function () {
            Route::post('/{service}', [ExternalEventTemplateMappingController::class, 'store'])->name('store');
            Route::put('/bulk', [ExternalEventTemplateMappingController::class, 'updateMultiple'])->name('update.bulk');
            Route::delete('/{service}/{eventType}', [ExternalEventTemplateMappingController::class, 'destroy'])->name('destroy');
        });
    });

    // EventSub connect - called from settings/integrations/index.vue
    Route::post('/eventsub/connect', [IntegrationController::class, 'connectEventSub'])
        ->name('eventsub.connect');

    // Template tag generator interface
    Route::get('/tags', [TemplateTagController::class, 'index'])
        ->name('tags.generator');

    // Generate standardized tags from current Twitch data
    Route::post('/template-tags/generate', [TemplateTagController::class, 'generateTags'])
        ->name('tags.generate');

    // Preview a specific tag with current data
    Route::get('/template-tags/{tag}/preview', [TemplateTagController::class, 'previewTag'])
        ->name('tags.preview');

    // Clear all template tags
    Route::delete('/template-tags/clear', [TemplateTagController::class, 'clearAllTags'])
        ->name('tags.clear');

    // Clean up redundant _data_X_ tags
    Route::post('/template-tags/cleanup', [TemplateTagController::class, 'cleanupRedundantTags'])
        ->name('tags.cleanup');

    // Replay a historical event as an alert
    Route::post('/events/{twitchEvent}/replay', [TwitchEventSubController::class, 'replay'])->name('events.replay');

    // Fire a synthetic channel.cheer for alert/control testing
    Route::post('/twitch/test-cheer', [TwitchEventSubController::class, 'testCheer'])->name('twitch.test-cheer');

    // Replay a stored external (Ko-fi, etc.) event as an alert
    Route::post('/external-events/{externalEvent}/replay', [ExternalEventController::class, 'replay'])->name('external-events.replay');

    // Twitch events API - protected by authentication
    Route::prefix('/api/twitch/events')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [TwitchEventController::class, 'index']);
        Route::get('/{id}', [TwitchEventController::class, 'show']);
        Route::put('/{id}/process', [TwitchEventController::class, 'markAsProcessed']);
        Route::post('/batch-process', [TwitchEventController::class, 'batchMarkAsProcessed']);
        Route::delete('/{id}', [TwitchEventController::class, 'destroy']);
    });
});

// Public map pages (no auth, opt-in via map_sharing_enabled)
Route::get('/map/{twitchId}', [MapController::class, 'live'])->name('map.live');
Route::get('/map/{twitchId}/{sessionId}', [MapController::class, 'session'])->name('map.session');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';

Route::get('/banned', fn () => Inertia::render('Banned'))->name('banned');

Route::any('{catchall}', [PageController::class, 'notfound'])->where('catchall', '.*');
