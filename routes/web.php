<?php

use App\Services\TwitchApiService;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TwitchDataController;
use App\Http\Controllers\TemplateTagController;

use App\Http\Controllers\OverlayAccessTokenController;
use App\Http\Controllers\OverlayTemplateController;
use App\Http\Controllers\TemplateBuilderController;

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
    ->middleware(['auth'])
    ->name('twitchdata');

Route::get('/twitchdata/refresh/expensive', [TwitchDataController::class, 'getLiveTwitchData'])
    ->middleware(['auth']);

Route::post('/twitchdata/refresh/all', [TwitchDataController::class, 'refreshAllTwitchApiData'])
    ->middleware(['auth']);

Route::post('/twitchdata/refresh/user', [TwitchDataController::class, 'refreshUserInfoData'])
    ->middleware(['auth']);

Route::post('/twitchdata/refresh/info', [TwitchDataController::class, 'refreshChannelInfoData'])
    ->middleware(['auth']);

Route::post('/twitchdata/refresh/following', [TwitchDataController::class, 'refreshFollowedChannelsData'])
    ->middleware(['auth']);

Route::post('/twitchdata/refresh/followers', [TwitchDataController::class, 'refreshChannelFollowersData'])
    ->middleware(['auth']);

Route::post('/twitchdata/refresh/subscribers', [TwitchDataController::class, 'refreshSubscribersData'])
    ->middleware(['auth']);

Route::post('/twitchdata/refresh/goals', [TwitchDataController::class, 'refreshGoalsData'])
    ->middleware(['auth']);

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


Route::get('/auth/callback/twitch', function () {
    try {
        $twitchUser = Socialite::driver('twitch')->user();

        // Create the TwitchApiService instance
        $twitchService = new TwitchApiService();

        // Get extended data using the access token
        $extendedData = $twitchService->getExtendedUserData(
            $twitchUser->token,
            $twitchUser->getId()
        );

        // First, try to find the user by twitch_id
        $user = User::where('twitch_id', $twitchUser->getId())->first();

        if (!$user) {
            // If no user found by twitch_id, check if email already exists
            $user = User::where('email', $twitchUser->getEmail())->first();

            if ($user) {
                // User exists with this email but no twitch_id - link the accounts
                $user->update([
                    'twitch_id' => $twitchUser->getId(),
                    'avatar' => $twitchUser->getAvatar(),
                    'access_token' => $twitchUser->token,
                    'refresh_token' => $twitchUser->refreshToken ?? null,
                    'token_expires_at' => now()->addSeconds($twitchUser->expiresIn ?? 3600),
                    'twitch_data' => array_merge($twitchUser->user, $extendedData),
                ]);
            } else {
                // No user exists at all - create a new one
                $user = User::create([
                    'name' => $twitchUser->getNickname() ?? $twitchUser->getName(),
                    'email' => $twitchUser->getEmail(),
                    'twitch_id' => $twitchUser->getId(),
                    'avatar' => $twitchUser->getAvatar(),
                    'access_token' => $twitchUser->token,
                    'refresh_token' => $twitchUser->refreshToken ?? null,
                    'token_expires_at' => now()->addSeconds($twitchUser->expiresIn ?? 3600),
                    'twitch_data' => array_merge($twitchUser->user, $extendedData),
                    'email_verified_at' => now(), // Twitch emails are pre-verified
                    'password' => bcrypt(Str::random(32)), // Random password since they use OAuth
                ]);
            }
        } else {
            // User found by twitch_id - update their tokens and info
            $user->update([
                'name' => $twitchUser->getNickname() ?? $twitchUser->getName(),
                'avatar' => $twitchUser->getAvatar(),
                'access_token' => $twitchUser->token,
                'refresh_token' => $twitchUser->refreshToken ?? null,
                'token_expires_at' => now()->addSeconds($twitchUser->expiresIn ?? 3600),
                'twitch_data' => array_merge($twitchUser->user, $extendedData),
            ]);
        }

        // Make sure we have a valid user before attempting login
        if (!$user) {
            throw new Exception('Failed to create or find user');
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


Route::middleware(['auth'])->group(function () {
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
        Route::get('/{token}/usage', [OverlayAccessTokenController::class, 'usage'])->name('usage');
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
    Route::get('/tags-generator', [TemplateTagController::class, 'index'])
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

    // Export standardized tags for sharing
    Route::get('/template-tags/export', [TemplateTagController::class, 'exportStandardTags'])
        ->name('template.export');

    // Overlay Hash Management Interface
    Route::get('/overlay-hashes', [App\Http\Controllers\OverlayHashController::class, 'index'])
        ->name('overlay.hashes.index');

    // Create a new Overlay Hash
    Route::post('/overlay-hashes', [App\Http\Controllers\OverlayHashController::class, 'store'])
        ->name('overlay.hashes.store');

    // Revoke an Overlay Hash
    Route::post('/overlay-hashes/{hash}/revoke', [App\Http\Controllers\OverlayHashController::class, 'revoke'])
        ->name('overlay.hashes.revoke');

    // Regenerate an Overlay Hash
    Route::post('/overlay-hashes/{hash}/regenerate', [App\Http\Controllers\OverlayHashController::class, 'regenerate'])
        ->name('overlay.hashes.regenerate');

    // Delete an Overlay Hash permanently
    Route::delete('/overlay-hashes/{hash}', [App\Http\Controllers\OverlayHashController::class, 'destroy'])
        ->name('overlay.hashes.destroy');

    // Template Builder Interface - NOW USES SLUG INSTEAD OF HASH_KEY!
    Route::get('/template-builder/{slug?}', [App\Http\Controllers\TemplateBuilderController::class, 'index'])
        ->name('template.builder')
        ->where('slug', '[a-z0-9]+(-[a-z0-9]+)*'); // Match the fun slug pattern: word-word-word-word-word

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
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
