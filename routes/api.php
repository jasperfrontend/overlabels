<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TwitchEventSubController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Twitch webhook endpoint - must be accessible without authentication
Route::post('/twitch/webhook', [TwitchEventSubController::class, 'webhook']);

Route::post('/test-webhook', function(Request $request) {
    \Log::info('Test webhook called', [
        'method' => $request->method(),
        'body' => $request->getContent(),
        'headers' => $request->headers->all()
    ]);
    return response('Test OK', 200);
});

Route::post('/test-challenge', function(Request $request) {
    $challenge = $request->input('challenge', 'test-challenge-123');
    
    Log::info('Test challenge endpoint called', [
        'challenge' => $challenge,
        'headers' => $request->headers->all(),
        'method' => $request->method()
    ]);
    
    // Test the same response method as your webhook
    http_response_code(200);
    header('Content-Type: text/plain');
    header('Content-Length: ' . strlen($challenge));
    echo $challenge;
    exit();
});
