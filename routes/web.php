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

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
