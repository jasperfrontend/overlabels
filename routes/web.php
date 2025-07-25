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
    return Socialite::driver('twitch')
        ->scopes([
            'user:read:email',
        ])
        ->redirect();
});

Route::get('/auth/callback/twitch', function () {
    $twitchUser = Socialite::driver('twitch')->user();

    $user = User::firstOrCreate(
        ['twitch_id' => $twitchUser->getId()],
        [
            'name' => $twitchUser->getNickname(),
            'email' => $twitchUser->getEmail(),
            'avatar' => $twitchUser->getAvatar(),
            'access_token' => $twitchUser->token,
            'refresh_token' => $twitchUser->refreshToken,
            'token_expires_at' => now()->addSeconds($twitchUser->expiresIn),
            'twitch_data' => $twitchUser->user,
        ]
    );

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
