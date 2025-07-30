<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TwitchEventSubController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Twitch webhook endpoint - must be accessible without authentication
Route::post('/twitch/webhook', [TwitchEventSubController::class, 'webhook']);

Route::get('/help-proxy/{slug}', function ($slug) {
    return Cache::remember("wp_help_{$slug}", now()->addMinutes(60), function () use ($slug) {
        $wpUrl = config('services.wordpress.wp_json_url') . "/wp-json/wp/v2/posts?category_name={$slug}";
        Log::info($wpUrl);
        $response = Http::get($wpUrl);

        if (!$response->successful()) {
            return response()->json(['error' => 'WP request failed'], 502);
        }

        return collect($response->json())->map(fn ($post) => [
            'id' => $post['id'],
            'title' => $post['title']['rendered'],
            'excerpt' => $post['excerpt']['rendered'],
            'content' => $post['content']['rendered'],
            'url' => $post['link'],
        ])->all();
    });
});