<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FoxController;
use App\Http\Controllers\TwitchDataController;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

Route::post('/twitch/webhook', [App\Http\Controllers\TwitchEventSubController::class, 'webhook'])
    ->withoutMiddleware(['web', 'csrf']);

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/fox', [FoxController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('fox');

Route::get('/foxes', [FoxController::class, 'gallery'])
    // ->middleware(['auth', 'verified'])
    ->name('foxes');

Route::get('/twitchdata', [TwitchDataController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('twitchdata');

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


Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/eventsub-demo', [App\Http\Controllers\TwitchEventSubController::class, 'index'])->name('eventsub.demo');
    Route::post('/eventsub/connect', [App\Http\Controllers\TwitchEventSubController::class, 'connect'])->name('eventsub.connect');
    Route::post('/eventsub/disconnect', [App\Http\Controllers\TwitchEventSubController::class, 'disconnect'])->name('eventsub.disconnect');
    Route::get('/eventsub/status', [App\Http\Controllers\TwitchEventSubController::class, 'status'])->name('eventsub.status');
    Route::get('/eventsub/webhook-status', [App\Http\Controllers\TwitchEventSubController::class, 'webhookStatus']);
});


require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
