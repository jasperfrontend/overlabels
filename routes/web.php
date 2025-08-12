<?php

use App\Services\TwitchApiService;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TwitchDataController;
use App\Http\Controllers\TemplateTagController;
use App\Http\Controllers\PageController;

use App\Http\Controllers\OverlayAccessTokenController;
use App\Http\Controllers\OverlayTemplateController;
use App\Http\Controllers\TemplateBuilderController;
use App\Http\Controllers\TwitchEventController;

use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Laravel\Socialite\Two\AbstractProvider;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');


Route::get('/twitchdata', [TwitchDataController::class, 'index'])
    ->middleware(['auth', 'twitch.token'])
    ->name('twitchdata');

Route::get('/twitchdata/refresh/expensive', [TwitchDataController::class, 'getLiveTwitchData'])
    ->middleware(['auth', 'twitch.token']);

Route::post('/twitchdata/refresh/all', [TwitchDataController::class, 'refreshAllTwitchApiData'])
    ->middleware(['auth', 'twitch.token']);

Route::post('/twitchdata/refresh/user', [TwitchDataController::class, 'refreshUserInfoData'])
    ->middleware(['auth', 'twitch.token']);

Route::post('/twitchdata/refresh/info', [TwitchDataController::class, 'refreshChannelInfoData'])
    ->middleware(['auth', 'twitch.token']);

Route::post('/twitchdata/refresh/following', [TwitchDataController::class, 'refreshFollowedChannelsData'])
    ->middleware(['auth', 'twitch.token']);

Route::post('/twitchdata/refresh/followers', [TwitchDataController::class, 'refreshChannelFollowersData'])
    ->middleware(['auth', 'twitch.token']);

Route::post('/twitchdata/refresh/subscribers', [TwitchDataController::class, 'refreshSubscribersData'])
    ->middleware(['auth', 'twitch.token']);

Route::post('/twitchdata/refresh/goals', [TwitchDataController::class, 'refreshGoalsData'])
    ->middleware(['auth', 'twitch.token']);

Route::get('/overlay/{slug}', [OverlayTemplateController::class, 'serveAuthenticated'])
    ->name('overlay.authenticated')
    ->where('slug', '[a-z0-9]+(-[a-z0-9]+)*');

Route::get('/overlay/{slug}/public', [OverlayTemplateController::class, 'servePublic'])
    ->name('overlay.public')
    ->where('slug', '[a-z0-9]+(-[a-z0-9]+)*');


// Test endpoint for debugging hash authentication (with fun slug)
Route::get('/test-hash/{slug}/{hashKey}', [App\Http\Controllers\OverlayHashController::class, 'testHash'])
    ->name('overlay.test')
    ->where('slug', '[a-z0-9]+(-[a-z0-9]+)*') // Same pattern as above
    ->where('hashKey', '[a-zA-Z0-9]{64}');

Route::get('/phpinfo', function () {
    phpinfo();
});

// Initiate login with Twitch
Route::get('/auth/redirect/twitch', function () {
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

    if (!$user) {
        return response()->json(['error' => 'Not authenticated'], 401);
    }

    $tokenService = app(\App\Services\TwitchTokenService::class);

    if ($tokenService->refreshUserToken($user)) {
        return response()->json(['success' => true, 'message' => 'Token refreshed successfully']);
    }

    return response()->json(['error' => 'Failed to refresh token', 'requires_reauth' => true], 401);
})->middleware('auth')->name('auth.refresh.twitch');

Route::get('/auth/callback/twitch', function () {
    try {
        $twitchUser = Socialite::driver('twitch')->user();

        $twitchService = new TwitchApiService();
        $extendedData = $twitchService->getExtendedUserData(
            $twitchUser->token,
            $twitchUser->getId()
        );

        // Always match by Twitch ID only
        $user = User::where('twitch_id', $twitchUser->getId())->first();

        if (!$user) {
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
            ]);
        } else {
            // Existing user — update tokens and data
            $user->update([
                'name' => $twitchUser->getNickname() ?? $twitchUser->getName(),
                'avatar' => $twitchUser->getAvatar(),
                'access_token' => $twitchUser->token,
                'refresh_token' => $twitchUser->refreshToken ?? null,
                'token_expires_at' => now()->addSeconds($twitchUser->expiresIn ?? 3600),
                'twitch_data' => array_merge($twitchUser->user, $extendedData),
            ]);
        }

        Auth::login($user);

        return redirect('/dashboard');

    } catch (Exception $e) {
        Log::error('Twitch OAuth callback failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return redirect('/')->with('error', 'Authentication failed. Please try again.');
    }
});


Route::post('/logout', function () {
    Auth::logout();
    return redirect('/');
});


Route::middleware('auth')->group(function () {
    Route::post('/eventsub/connect', [App\Http\Controllers\TwitchEventSubController::class, 'connect'])->name('eventsub.connect');
    Route::post('/eventsub/disconnect', [App\Http\Controllers\TwitchEventSubController::class, 'disconnect'])->name('eventsub.disconnect');
    Route::get('/eventsub-demo', [App\Http\Controllers\TwitchEventSubController::class, 'index'])->name('eventsub.demo');
    Route::get('/eventsub/status', [App\Http\Controllers\TwitchEventSubController::class, 'status'])->name('eventsub.status');
    Route::get('/eventsub/webhook-status', [App\Http\Controllers\TwitchEventSubController::class, 'webhookStatus']);
    Route::get('/eventsub/check-status', [App\Http\Controllers\TwitchEventSubController::class, 'checkStatus']);
    Route::get('/eventsub/cleanup-all', [App\Http\Controllers\TwitchEventSubController::class, 'cleanupAll']);


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
    });

    // Template Builder
    Route::get('/builder/{template?}', [TemplateBuilderController::class, 'index'])->name('builder');

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

    // Export standardized tags for sharing
    Route::get('/template-tags/export', [TemplateTagController::class, 'exportStandardTags'])
        ->name('template.export');


    // API endpoints for template builder
    Route::prefix('api/template')->group(function () {

        // Get available template tags
        Route::get('/tags', [App\Http\Controllers\TemplateBuilderController::class, 'getAvailableTags'])
            ->name('api.template.tags');

        // Get default templates from a centralized service
        Route::get('/defaults', [App\Http\Controllers\TemplateBuilderController::class, 'getDefaultTemplates'])
            ->name('api.template.defaults');

        // Validate template syntax
        Route::post('/validate', [App\Http\Controllers\TemplateBuilderController::class, 'validateTemplate'])
            ->name('api.template.validate');

        // Save template to overlay hash (still uses hash_key internally for security)
        Route::post('/save', [App\Http\Controllers\TemplateBuilderController::class, 'saveTemplate'])
            ->name('api.template.save');

        // Load existing template from slug
        Route::get('/load/{slug}', [App\Http\Controllers\TemplateBuilderController::class, 'loadTemplate'])
            ->name('api.template.load')
            ->where('slug', '[a-z0-9]+(-[a-z0-9]+)*');

        // Preview template with sample data
        Route::post('/preview', [App\Http\Controllers\TemplateBuilderController::class, 'previewTemplate'])
            ->name('api.template.preview');

        // Export template as a standalone HTML file
        Route::post('/export', [App\Http\Controllers\TemplateBuilderController::class, 'exportTemplate'])
            ->name('api.template.export');
    });


    // Twitch events API - protected by authentication
    Route::prefix('/api/twitch/events')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [TwitchEventController::class, 'index']);
        Route::get('/{id}', [TwitchEventController::class, 'show']);
        Route::put('/{id}/process', [TwitchEventController::class, 'markAsProcessed']);
        Route::post('/batch-process', [TwitchEventController::class, 'batchMarkAsProcessed']);
        Route::delete('/{id}', [TwitchEventController::class, 'destroy']);
    });
});

Route::any('{catchall}', [PageController::class, 'notfound'])->where('catchall', '.*');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
