<?php

use App\Services\TwitchApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.twitch.client_id' => 'test-client-id']);
    Cache::flush();
});

/**
 * A 429 from Helix used to sleep (Retry-After, else 60 s) and retry, up to three
 * times, inside whatever process made the call. On the single twitch-events
 * worker that froze every streamer's alerts for minutes once one raided channel
 * had spent its rate budget (issue #343). A 429 is now a failed call like any
 * other: one request, null, no sleep.
 */
it('gives up on a 429 after one request instead of sleeping and retrying', function () {
    Http::fake([
        'api.twitch.tv/helix/users*' => Http::response(['message' => 'Too Many Requests'], 429, ['Retry-After' => '1', 'Ratelimit-Reset' => (string) (time() + 30)]),
    ]);

    $started = microtime(true);
    $users = app(TwitchApiService::class)->getUsersInfo('token', ['42']);
    $elapsed = microtime(true) - $started;

    expect($users)->toBe([])
        ->and($elapsed)->toBeLessThan(0.5);
    Http::assertSentCount(1);
});

it('leaves an event unenriched rather than waiting when Helix rate limits the avatar lookup', function () {
    Http::fake([
        'api.twitch.tv/helix/users*' => Http::response(['message' => 'Too Many Requests'], 429, ['Retry-After' => '1']),
    ]);

    $event = ['user_id' => '42', 'user_login' => 'follower', 'user_name' => 'Follower'];

    $enriched = app(TwitchApiService::class)->enrichEventWithUserAvatars('token', $event);

    expect($enriched['user_id'])->toBe('42');
    Http::assertSentCount(1);
});
