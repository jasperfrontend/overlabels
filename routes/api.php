<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TwitchEventSubController;
use App\Http\Controllers\OverlayTemplateController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('/overlay')->group(function () {
    Route::post('/render', [OverlayTemplateController::class, 'renderAuthenticated'])
        ->name('api.overlay.render')
        ->middleware(['throttle:overlay', 'rate.limit.overlay']);
});

//Route::prefix('overlay')->middleware(['throttle:overlay'])->group(function () {
//    Route::post('/render', [OverlayTemplateController::class, 'renderAuthenticated'])
//        ->name('api.overlay.render')
//        ->middleware(['rate.limit.overlay']);
//});

// Twitch webhook endpoint - must be accessible without authentication
Route::post('/twitch/webhook', [TwitchEventSubController::class, 'webhook']);
