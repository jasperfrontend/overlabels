<?php

use App\Services\TwitchApiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.twitch.client_id' => 'test-client-id']);
    Cache::flush();
});

/**
 * getChannelFollowers used to call GET helix/channels first, uncached, only to
 * read broadcaster_id back out of it - which is the id it was given. One wasted
 * Helix round trip (~190 ms from Amsterdam) on every follower refetch (#343).
 */
it('fetches followers without a channel info round trip first', function () {
    Http::fake([
        'api.twitch.tv/helix/channels/followers*' => Http::response([
            'total' => 1,
            'data' => [['user_id' => '42', 'user_login' => 'follower', 'user_name' => 'Follower', 'followed_at' => '2026-11-22T20:00:00Z']],
        ]),
        'api.twitch.tv/helix/users*' => Http::response(['data' => [['id' => '42', 'profile_image_url' => 'https://example.test/42.png']]]),
        'api.twitch.tv/helix/channels*' => Http::response(['data' => [['broadcaster_id' => '1001']]]),
    ]);

    $followers = app(TwitchApiService::class)->getChannelFollowers('token', '1001');

    expect($followers['total'])->toBe(1);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/helix/channels/followers') && str_contains($r->url(), 'broadcaster_id=1001'));
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/helix/channels?'));
});
