<?php

use App\Http\Controllers\TwitchEventSubController;
use App\Jobs\ForgetTwitchCache;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\TwitchApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * Every follow used to delete the streamer's cached follower list and goals,
 * and the alert render then fetched both straight back from Helix: four round
 * trips (~190 ms each) per follow, on the single twitch-events worker, for a
 * total Twitch itself only updates minutes later (#343). Now a volatile cache
 * is cleared at most once per window per streamer, and a delayed
 * ForgetTwitchCache clears it again at the end of the window so the last
 * change of a burst is never left behind for the full TTL.
 */
beforeEach(function () {
    config(['services.twitch.client_id' => 'test-client-id']);
    Http::fake(['*' => Http::response(['data' => []])]);
    Queue::fake([ForgetTwitchCache::class]);
});

function throttleProcess(User $user, string $type, array $extra = []): void
{
    $event = ['broadcaster_user_id' => $user->twitch_id, 'user_id' => '42', 'user_login' => 'viewer', 'user_name' => 'Viewer'] + $extra;
    $data = ['subscription' => ['type' => $type], 'event' => $event];
    $row = TwitchEvent::create(['user_id' => $user->id, 'event_type' => $type, 'event_data' => $event, 'twitch_timestamp' => now(), 'processed' => false]);

    app(TwitchEventSubController::class)->processStoredEvent($row, $data);
}

function followersKey(User $user): string
{
    return 'twitch_channel_followers_'.$user->twitch_id;
}

function goalsKey(User $user): string
{
    return 'twitch_goals_'.$user->twitch_id;
}

it('clears the follower and goal caches on the first follow of a window only', function () {
    $user = User::factory()->create(['access_token' => 'token']);

    Cache::put(followersKey($user), ['total' => 5], 120);
    Cache::put(goalsKey($user), ['data' => []], 300);
    throttleProcess($user, 'channel.follow');

    expect(Cache::has(followersKey($user)))->toBeFalse()
        ->and(Cache::has(goalsKey($user)))->toBeFalse();

    // The first follow's alert refetched them; the rest of the burst reuses that.
    Cache::put(followersKey($user), ['total' => 6], 120);
    Cache::put(goalsKey($user), ['data' => []], 300);
    throttleProcess($user, 'channel.follow');
    throttleProcess($user, 'channel.follow');

    expect(Cache::has(followersKey($user)))->toBeTrue()
        ->and(Cache::has(goalsKey($user)))->toBeTrue();
});

it('queues one trailing forget per cache per window, delayed by the window', function () {
    $user = User::factory()->create(['access_token' => 'token']);

    throttleProcess($user, 'channel.follow');
    throttleProcess($user, 'channel.follow');
    throttleProcess($user, 'channel.follow');

    foreach (['channel_followers', 'goals'] as $cache) {
        Queue::assertPushed(ForgetTwitchCache::class, fn (ForgetTwitchCache $job) => $job->cache === $cache
            && $job->broadcasterId === (string) $user->twitch_id
            && $job->delay !== null);
    }
    expect(Queue::pushed(ForgetTwitchCache::class)->count())->toBe(2);
});

it('clears again once the window has passed', function () {
    $user = User::factory()->create(['access_token' => 'token']);

    throttleProcess($user, 'channel.follow');
    Cache::put(followersKey($user), ['total' => 6], 120);

    $this->travel(TwitchEventSubController::CACHE_CLEAR_WINDOW_SECONDS + 1)->seconds();
    throttleProcess($user, 'channel.follow');

    expect(Cache::has(followersKey($user)))->toBeFalse();
});

it('throttles per streamer, so one raid does not hold back another channel', function () {
    $raided = User::factory()->create(['access_token' => 'token']);
    $other = User::factory()->create(['access_token' => 'token']);

    throttleProcess($raided, 'channel.follow');
    Cache::put(followersKey($other), ['total' => 1], 120);
    throttleProcess($other, 'channel.follow');

    expect(Cache::has(followersKey($other)))->toBeFalse();
});

it('throttles the subscriber cache the same way across a gift bomb', function () {
    $user = User::factory()->create(['access_token' => 'token']);

    throttleProcess($user, 'channel.subscribe', ['tier' => '1000', 'is_gift' => true]);
    Cache::put('twitch_subscribers_'.$user->twitch_id, ['total' => 3], 300);
    throttleProcess($user, 'channel.subscribe', ['tier' => '1000', 'is_gift' => true]);

    expect(Cache::has('twitch_subscribers_'.$user->twitch_id))->toBeTrue();
});

it('the trailing job forgets the cache it names', function () {
    $user = User::factory()->create(['access_token' => 'token']);
    Cache::put(followersKey($user), ['total' => 7], 120);

    (new ForgetTwitchCache('channel_followers', (string) $user->twitch_id))->handle(app(TwitchApiService::class));

    expect(Cache::has(followersKey($user)))->toBeFalse();
});

it('leaves the channel info clear on channel.update unthrottled', function () {
    $user = User::factory()->create(['access_token' => 'token']);
    $key = 'twitch_channel_info_'.$user->twitch_id;

    throttleProcess($user, 'channel.update', ['title' => 'one']);
    Cache::put($key, ['title' => 'one'], 300);
    throttleProcess($user, 'channel.update', ['title' => 'two']);

    expect(Cache::has($key))->toBeFalse();
});
