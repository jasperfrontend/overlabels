<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TwitchEventSubController;
use App\Http\Controllers\TwitchEventController;
use App\Http\Controllers\TemplateTagController;
use App\Http\Controllers\OverlayTemplateController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('/overlay')->group(function () {
    Route::post('/render', [OverlayTemplateController::class, 'renderAuthenticated'])
        ->name('api.overlay.render')
        ->middleware(['throttle:overlay', 'rate.limit.overlay']);
});

// Get all template tags (API endpoint)
Route::get('/template-tags', [TemplateTagController::class, 'getAllTags'])
    ->name('tags.api.all');

// Twitch webhook endpoint - must be accessible without authentication
Route::post('/twitch/webhook', [TwitchEventSubController::class, 'webhook']);

// Twitch events API - protected by authentication

Route::prefix('/twitch/events')->group(function () {
    Route::get('/', [TwitchEventController::class, 'index']);
    Route::get('/{id}', [TwitchEventController::class, 'show']);
    Route::put('/{id}/process', [TwitchEventController::class, 'markAsProcessed']);
    Route::post('/batch-process', [TwitchEventController::class, 'batchMarkAsProcessed']);
    Route::delete('/{id}', [TwitchEventController::class, 'destroy']);
})->middleware('auth:sanctum');
