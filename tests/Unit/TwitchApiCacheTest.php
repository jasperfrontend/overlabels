<?php

use App\Services\TwitchApiService;
use Illuminate\Support\Facades\Cache;

/**
 * Guards the cache contract in TwitchApiService::getCachedData() - the layer
 * behind every getExtendedUserData() read (overlays, Bot Expressions, alerts).
 *
 * The bug this protects against: a single failed fetch used to be cached as []
 * for 365 days, silently emptying tags like [[[followers_latest_user_name]]]
 * for cold readers (Bot Expressions have no live EventSub patch to mask it).
 *
 * getCachedData is private; we drive it by reflection because it IS the unit
 * under test and mocking six Helix endpoints would obscure what we're checking.
 */
function callGetCachedData(TwitchApiService $svc, string $key, string $userId, callable $cb): array
{
    $method = new ReflectionMethod($svc, 'getCachedData');

    return $method->invoke($svc, $key, $userId, $cb);
}

beforeEach(fn () => Cache::flush());

test('a failed fetch is negative-cached only briefly, then retried', function () {
    $svc = app(TwitchApiService::class);

    $calls = 0;
    $cb = function () use (&$calls) {
        $calls++;

        // First fetch fails (transient), second succeeds.
        return $calls === 1
            ? null
            : ['total' => 5, 'data' => [['user_name' => 'Alice']]];
    };

    // Failure -> empty result, cached briefly.
    expect(callGetCachedData($svc, 'channel_followers', '42', $cb))->toBe([])
        ->and($calls)->toBe(1);

    // Inside the negative window: served from cache, callback NOT re-invoked.
    expect(callGetCachedData($svc, 'channel_followers', '42', $cb))->toBe([])
        ->and($calls)->toBe(1);

    // Past the 30s negative window: refetch, now succeeds. This is the bug fix -
    // pre-fix the empty would have persisted for a year.
    $this->travel(31)->seconds();
    expect(callGetCachedData($svc, 'channel_followers', '42', $cb))
        ->toBe(['total' => 5, 'data' => [['user_name' => 'Alice']]])
        ->and($calls)->toBe(2);
});

test('a successful fetch is cached for the per-type TTL', function () {
    $svc = app(TwitchApiService::class);

    $calls = 0;
    $cb = function () use (&$calls) {
        $calls++;

        return ['total' => 1, 'data' => [['user_name' => 'Bob']]];
    };

    callGetCachedData($svc, 'channel_followers', '99', $cb);
    expect($calls)->toBe(1);

    // channel_followers TTL is 120s: still cached just before it.
    $this->travel(119)->seconds();
    callGetCachedData($svc, 'channel_followers', '99', $cb);
    expect($calls)->toBe(1);

    // Expired just after.
    $this->travel(2)->seconds();
    callGetCachedData($svc, 'channel_followers', '99', $cb);
    expect($calls)->toBe(2);
});

test('a legitimately empty-but-successful response is cached, not treated as failure', function () {
    $svc = app(TwitchApiService::class);

    // A brand-new streamer with zero followers: Helix returns a real object
    // {total:0, data:[]} - a non-empty array - so it must take the positive
    // path and NOT be negative-cached.
    $calls = 0;
    $cb = function () use (&$calls) {
        $calls++;

        return ['total' => 0, 'data' => []];
    };

    expect(callGetCachedData($svc, 'channel_followers', '7', $cb))->toBe(['total' => 0, 'data' => []]);

    // 25s later (inside the 30s negative window, well inside the 120s positive
    // TTL): if this had been mis-classified as a failure it would already be
    // refetching. It should still be the cached success.
    $this->travel(25)->seconds();
    callGetCachedData($svc, 'channel_followers', '7', $cb);
    expect($calls)->toBe(1);
});
