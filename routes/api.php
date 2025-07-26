<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TwitchEventSubController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Twitch webhook endpoint - must be accessible without authentication
Route::post('/twitch/webhook', [TwitchEventSubController::class, 'webhook'])
    ->withoutMiddleware(['auth:sanctum']);