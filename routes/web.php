<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FoxController;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

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

Route::get('/phpinfo', function () {
    phpinfo();
});

// Initiate login with Twitch
Route::get('/auth/redirect/twitch', function () {
    /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
    $driver = Socialite::driver('twitch');
    
    return $driver->scopes([
        'user:read:email',
    ])->redirect();
});

// Initiate login with Twitch
Route::get('/auth/redirect/twitchextended', function () {
    /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
    $driver = Socialite::driver('twitch');

    return $driver->scopes([
        'user:read:email',            // To get email
        'user:read:follows',          // Who they follow
        'user:read:subscriptions',    // (requires Partner status)
        'channel:read:subscriptions', // Who is subscribed to them
        'channel:read:redemptions',   // Channel point stuff
        'channel:read:goals',         // Follower/sub goals
    ])->redirect();
});

Route::get('/auth/callback/twitch', function () {
    $twitchUser = Socialite::driver('twitch')->user();

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
                'twitch_data' => $twitchUser->user,
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
                'twitch_data' => $twitchUser->user,
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
            'twitch_data' => $twitchUser->user,
        ]);
    }

    Auth::login($user);

    return redirect('/dashboard');
});

Route::post('/logout', function () {
    Auth::logout();
    return redirect('/');
});

// Route::get('/debug/user', function () {
//     return response()->json([
//         'user' => auth()->user(),
//         'database_works' => \DB::connection()->getPdo() ? 'yes' : 'no',
//         'users_count' => \App\Models\User::count(),
//     ]);
// })->middleware(['auth']);

// Route::get('/debug/basic', function () {
//     return response()->json([
//         'laravel_version' => app()->version(),
//         'environment' => app()->environment(),
//         'database_works' => \DB::connection()->getPdo() ? 'yes' : 'no',
//         'config_cached' => app()->configurationIsCached(),
//     ]);
// });

// Route::get('/debug/counts', function () {
//     return response()->json([
//         'users_count' => \App\Models\User::count(),
//         'foxes_count' => \App\Models\Fox::count(),
//         'latest_user' => \App\Models\User::latest()->first(),
//         'latest_fox' => \App\Models\Fox::latest()->first(),
//         'database_name' => \DB::connection()->getDatabaseName(),
//     ]);
// });

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
