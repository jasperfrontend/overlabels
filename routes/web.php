<?php

use App\Services\TwitchApiService;
use App\Services\TwitchTokenService;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TwitchDataController;
use App\Http\Controllers\TemplateTagController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\KitController;

use App\Http\Controllers\OverlayAccessTokenController;
use App\Http\Controllers\OverlayTemplateController;
use App\Http\Controllers\TwitchEventController;

use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Laravel\Socialite\Two\AbstractProvider;

//use App\Http\Controllers\StorageConnectionController;
//use App\Http\Controllers\StorageBrowserController;


Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/privacy', function () {
    return Inertia::render('Privacy');
})->name('privacy');

Route::get('/terms', function () {
    return Inertia::render('Terms');
})->name('terms');

Route::get('/help', function () {
    return Inertia::render('Help');
})->name('help');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

Route::get('/dashboard/recents', [DashboardController::class, 'recentCommunityTemplates'])
    ->middleware(['auth'])
    ->name('dashboard.recents');

Route::get('/login', [PageController::class, 'notAuthorized'])
    ->middleware(['guest'])
    ->name('login');

Route::get('/twitchdata', [TwitchDataController::class, 'index'])
    ->middleware(['auth', 'twitch.token'])
    ->name('twitchdata');

Route::get('/twitchdata/refresh/expensive', [TwitchDataController::class, 'getLiveTwitchData'])
    ->middleware(['auth', 'twitch.token'])
    ->name('twitchdata.refresh.expensive');

Route::post('/twitchdata/refresh/all', [TwitchDataController::class, 'refreshAllTwitchApiData'])
    ->middleware(['auth', 'twitch.token'])
    ->name('twitchdata.refresh.all');

Route::post('/twitchdata/refresh/user', [TwitchDataController::class, 'refreshUserInfoData'])
    ->middleware(['auth', 'twitch.token'])
    ->name('twitchdata.refresh.user');

Route::post('/twitchdata/refresh/info', [TwitchDataController::class, 'refreshChannelInfoData'])
    ->middleware(['auth', 'twitch.token'])
    ->name('twitchdata.refresh.info');

Route::post('/twitchdata/refresh/following', [TwitchDataController::class, 'refreshFollowedChannelsData'])
    ->middleware(['auth', 'twitch.token'])
    ->name('twitchdata.refresh.following');

Route::post('/twitchdata/refresh/followers', [TwitchDataController::class, 'refreshChannelFollowersData'])
    ->middleware(['auth', 'twitch.token'])
    ->name('twitchdata.refresh.followers');

Route::post('/twitchdata/refresh/subscribers', [TwitchDataController::class, 'refreshSubscribersData'])
    ->middleware(['auth', 'twitch.token'])
    ->name('twitchdata.refresh.subscribers');

Route::post('/twitchdata/refresh/goals', [TwitchDataController::class, 'refreshGoalsData'])
    ->middleware(['auth', 'twitch.token'])
    ->name('twitchdata.refresh.goals');

Route::get('/overlay/{slug}', [OverlayTemplateController::class, 'serveAuthenticated'])
    ->name('overlay.authenticated')
    ->where('slug', '[a-z0-9]+(-[a-z0-9]+)*');

Route::get('/overlay/{slug}/public', [OverlayTemplateController::class, 'servePublic'])
    ->name('overlay.public')
    ->where('slug', '[a-z0-9]+(-[a-z0-9]+)*');

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

    $tokenService = app(TwitchTokenService::class);

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
    Route::prefix('events')->name('events.')->group(function () {
        Route::get('/', [App\Http\Controllers\EventTemplateMappingController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\EventTemplateMappingController::class, 'store'])->name('store');
        Route::put('/bulk', [App\Http\Controllers\EventTemplateMappingController::class, 'updateMultiple'])->name('update.bulk');
        Route::delete('/{eventType}', [App\Http\Controllers\EventTemplateMappingController::class, 'destroy'])->name('destroy');
    });

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
