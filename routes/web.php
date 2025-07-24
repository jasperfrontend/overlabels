<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FoxController;
use Inertia\Inertia;

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
    ->middleware(['auth', 'verified'])
    ->name('foxes');

Route::get('/phpinfo', function () {
    phpinfo();
});

Route::get('/debug/user', function () {
    return response()->json([
        'user' => auth()->user(),
        'database_works' => \DB::connection()->getPdo() ? 'yes' : 'no',
        'users_count' => \App\Models\User::count(),
    ]);
})->middleware(['auth']);

Route::get('/debug/basic', function () {
    return response()->json([
        'laravel_version' => app()->version(),
        'environment' => app()->environment(),
        'database_works' => \DB::connection()->getPdo() ? 'yes' : 'no',
        'config_cached' => app()->configurationIsCached(),
    ]);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
