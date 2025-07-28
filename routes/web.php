<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FoxController;
use App\Http\Controllers\TwitchDataController;
use App\Http\Controllers\TemplateTagController;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

Route::get('/fox', [FoxController::class, 'index'])
    ->middleware(['auth'])
    ->name('fox');

Route::get('/foxes', [FoxController::class, 'gallery'])
    ->name('foxes');

Route::get('/twitchdata', [TwitchDataController::class, 'index'])
    ->middleware(['auth'])
    ->name('twitchdata');

Route::get('/widgets', [TwitchDataController::class, 'widget'])
    ->middleware(['auth'])
    ->name('widgets');

Route::post('/twitchdata/refresh/all', [TwitchDataController::class, 'refreshAllTwitchApiData'])
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


Route::get('/phpinfo', function () {
    phpinfo();
});

// Initiate login with Twitch
Route::get('/auth/redirect/twitch', function () {
    /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
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
    $twitchUser = Socialite::driver('twitch')->user();
    
    // Create the TwitchApiService instance
    $twitchService = new \App\Services\TwitchApiService();
    
    // Get extended data using the access token
    $extendedData = $twitchService->getExtendedUserData(
        $twitchUser->token, 
        $twitchUser->getId()
    );

    // First, try to find user by twitch_id
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
                'refresh_token' => $twitchUser->refreshToken,
                'token_expires_at' => now()->addSeconds($twitchUser->expiresIn),
                'twitch_data' => array_merge($twitchUser->user, $extendedData), // Merge the data!
            ]);
        } else {
            // Completely new user - create them
            $user = User::create([
                'name' => $twitchUser->getNickname(),
                'email' => $twitchUser->getEmail(),
                'twitch_id' => $twitchUser->getId(),
                'avatar' => $twitchUser->getAvatar(),
                'access_token' => $twitchUser->token,
                'refresh_token' => $twitchUser->refreshToken,
                'token_expires_at' => now()->addSeconds($twitchUser->expiresIn),
                'twitch_data' => array_merge($twitchUser->user, $extendedData), // Merge the data!
            ]);
        }
    } else {
        // User found by twitch_id - update their tokens and info
        $user->update([
            'name' => $twitchUser->getNickname(),
            'avatar' => $twitchUser->getAvatar(),
            'access_token' => $twitchUser->token,
            'refresh_token' => $twitchUser->refreshToken,
            'token_expires_at' => now()->addSeconds($twitchUser->expiresIn),
            'twitch_data' => array_merge($twitchUser->user, $extendedData), // Merge the data!
        ]);
    }

    Auth::login($user);

    return redirect('/dashboard');
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

    // Template tag generator interface
    Route::get('/template-generator', [TemplateTagController::class, 'index'])
        ->name('template.generator');
    
    // Generate tags from current Twitch data
    Route::post('/template-tags/generate', [TemplateTagController::class, 'generateTags'])
        ->name('template.generate');
    
    // Preview a specific tag with current data
    Route::get('/template-tags/{tag}/preview', [TemplateTagController::class, 'previewTag'])
        ->name('template.preview');
    
    // Update a template tag
    Route::patch('/template-tags/{tag}', [TemplateTagController::class, 'updateTag'])
        ->name('template.update');
    
    // Clear all template tags
    Route::delete('/template-tags/clear', [TemplateTagController::class, 'clearAllTags'])
        ->name('template.clear');
    
    // Get all template tags (API endpoint)
    Route::get('/api/template-tags', [TemplateTagController::class, 'getAllTags'])
        ->name('template.api.all');
});


require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
