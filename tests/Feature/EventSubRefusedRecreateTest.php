<?php

use App\Jobs\SetupUserEventSubSubscriptions;
use App\Models\User;
use App\Models\UserEventsubSubscription;
use App\Services\TwitchEventSubService;
use App\Services\UserEventSubManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

/**
 * Issue #344. The monitor used to delete a `not_found_on_twitch` row and then
 * queue a setup. When Twitch refused the recreate (403, the streamer's
 * authorization of the app is gone), nothing was stored: the monitor reported
 * 100% and stopped naming the account, /wiring stopped counting it, and the
 * streamer had no reason to log in again. Seen for real on 2026-11-22.
 *
 * Now the dead row stays until a recreate succeeds; a refusal turns it into
 * `creation_refused`, which the monitor names but never retries, and which a
 * fresh Twitch login clears by running setup again.
 */
const REFUSAL_BODY = '{"error":"Forbidden","status":403,"message":"subscription missing proper authorization"}';

/**
 * A connected streamer with every supported subscription enabled except
 * channel.follow, which is stored with $followStatus.
 */
function streamerWithDeadFollow(string $followStatus = 'not_found_on_twitch', array $scopes = ['moderator:read:followers']): User
{
    $user = User::factory()->create([
        'twitch_id' => '5550001',
        'access_token' => 'user-token',
        'twitch_scopes' => $scopes,
        'eventsub_connected_at' => now(),
    ]);

    foreach (array_keys(UserEventSubManager::SUPPORTED_EVENTS) as $type) {
        UserEventsubSubscription::factory()->create([
            'user_id' => $user->id,
            'twitch_subscription_id' => 'sub-'.$type,
            'event_type' => $type,
            'status' => $type === 'channel.follow' ? $followStatus : 'enabled',
            'last_verified_at' => now(),
        ]);
    }

    return $user;
}

/** Twitch lists every enabled row's id, plus any extra ids given. */
function twitchListsEnabledRows(User $user, array $extra = []): array
{
    $ids = UserEventsubSubscription::where('user_id', $user->id)
        ->where('status', 'enabled')
        ->pluck('twitch_subscription_id')
        ->merge($extra);

    return [
        'data' => $ids->map(fn ($id) => ['id' => $id, 'status' => 'enabled'])->all(),
        'pagination' => [],
    ];
}

function followRows(User $user)
{
    return UserEventsubSubscription::where('user_id', $user->id)->where('event_type', 'channel.follow')->get();
}

it('marks the dead row refused when Twitch refuses the recreate', function () {
    Event::fake();
    $user = streamerWithDeadFollow();
    $listing = twitchListsEnabledRows($user);

    $this->mock(TwitchEventSubService::class, function ($mock) use ($listing) {
        $mock->shouldReceive('getAppAccessToken')->andReturn('app-token');
        $mock->shouldReceive('createSubscription')->once()->andReturn([
            'error' => true, 'status' => 403, 'message' => REFUSAL_BODY,
        ]);
        $mock->shouldReceive('getSubscriptions')->andReturn($listing);
    });

    app(UserEventSubManager::class)->setupUserSubscriptions($user);

    $rows = followRows($user);
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->status)->toBe('creation_refused');
});

it('replaces the dead row when the recreate succeeds', function () {
    Event::fake();
    $user = streamerWithDeadFollow();
    $listing = twitchListsEnabledRows($user, ['new-follow']);

    $this->mock(TwitchEventSubService::class, function ($mock) use ($listing) {
        $mock->shouldReceive('getAppAccessToken')->andReturn('app-token');
        $mock->shouldReceive('createSubscription')->once()->andReturn([
            'data' => [['id' => 'new-follow', 'status' => 'enabled', 'created_at' => now()->toIso8601String()]],
        ]);
        $mock->shouldReceive('getSubscriptions')->andReturn($listing);
    });

    app(UserEventSubManager::class)->setupUserSubscriptions($user);

    $rows = followRows($user);
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->twitch_subscription_id)->toBe('new-follow')
        ->and($rows->first()->status)->toBe('enabled');
});

it('leaves the dead row for the next hour on a failure that is not a refusal', function () {
    Event::fake();
    $user = streamerWithDeadFollow();
    $listing = twitchListsEnabledRows($user);

    $this->mock(TwitchEventSubService::class, function ($mock) use ($listing) {
        $mock->shouldReceive('getAppAccessToken')->andReturn('app-token');
        $mock->shouldReceive('createSubscription')->once()->andReturn([
            'error' => true, 'status' => 503, 'message' => 'Service Unavailable',
        ]);
        $mock->shouldReceive('getSubscriptions')->andReturn($listing);
    });

    app(UserEventSubManager::class)->setupUserSubscriptions($user);

    expect(followRows($user)->first()->status)->toBe('not_found_on_twitch');
});

it('marks the dead row refused when the scope it needs was never granted', function () {
    Event::fake();
    $user = streamerWithDeadFollow(scopes: ['channel:read:subscriptions']);
    $listing = twitchListsEnabledRows($user);

    $this->mock(TwitchEventSubService::class, function ($mock) use ($listing) {
        $mock->shouldReceive('getAppAccessToken')->andReturn('app-token');
        $mock->shouldNotReceive('createSubscription');
        $mock->shouldReceive('getSubscriptions')->andReturn($listing);
    });

    app(UserEventSubManager::class)->setupUserSubscriptions($user);

    expect(followRows($user)->first()->status)->toBe('creation_refused');
});

it('keeps a refused row refused when verifying, and stamps it checked', function () {
    $user = streamerWithDeadFollow('creation_refused');
    UserEventsubSubscription::where('user_id', $user->id)->update(['last_verified_at' => now()->subDays(3)]);
    $listing = twitchListsEnabledRows($user);

    $this->mock(TwitchEventSubService::class, function ($mock) use ($listing) {
        $mock->shouldReceive('getAppAccessToken')->andReturn('app-token');
        $mock->shouldReceive('getSubscriptions')->andReturn($listing);
    });

    app(UserEventSubManager::class)->verifyUserSubscriptions($user);

    $row = followRows($user)->first();
    expect($row->status)->toBe('creation_refused')
        ->and($row->last_verified_at->isAfter(now()->subMinute()))->toBeTrue();
});

it('names an account with refused rows and never retries it', function () {
    Bus::fake([SetupUserEventSubSubscriptions::class]);
    streamerWithDeadFollow('creation_refused');

    $this->artisan('eventsub:monitor', ['--fix' => true])
        ->expectsOutputToContain('refused by Twitch')
        ->run();

    Bus::assertNotDispatched(SetupUserEventSubSubscriptions::class);
});

it('runs setup again when a streamer with refused rows logs in through Twitch', function () {
    Http::fake();
    Queue::fake();
    $user = streamerWithDeadFollow('creation_refused');

    $socialiteUser = (new SocialiteUser)->map([
        'id' => $user->twitch_id,
        'nickname' => 'testbroadcaster',
        'name' => 'Test Broadcaster',
        'avatar' => 'https://example.test/avatar.png',
    ]);
    $socialiteUser->user = ['id' => $user->twitch_id, 'login' => 'testbroadcaster'];
    $socialiteUser->token = 'fresh-token';
    $socialiteUser->refreshToken = 'refresh-token';
    $socialiteUser->expiresIn = 3600;
    $socialiteUser->approvedScopes = ['moderator:read:followers'];

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('twitch')->andReturn($provider);

    $this->get('/auth/callback/twitch')->assertRedirect();

    Queue::assertPushed(SetupUserEventSubSubscriptions::class, function (SetupUserEventSubSubscriptions $job) use ($user) {
        return (new ReflectionProperty($job, 'user'))->getValue($job)->is($user);
    });
});

/**
 * The second door into the same blind spot. A failed subscription (Twitch
 * revoked it, or disabled it after notification failures) used to trigger a
 * FORCE recreate: every row of the account deleted, every Twitch subscription
 * matching the user deleted, then setup. A refused recreate then stored
 * nothing and the account vanished from the monitor exactly like #344. Now
 * only the failed subscription is deleted on Twitch, by its own id, and its
 * row becomes a dead row that the not-found pass recreates in the same run.
 */
it('retires only the failed subscription and recreates it without a force wipe', function () {
    Bus::fake([SetupUserEventSubSubscriptions::class]);
    $user = streamerWithDeadFollow('authorization_revoked');

    $deleted = [];
    $this->mock(TwitchEventSubService::class, function ($mock) use (&$deleted) {
        $mock->shouldReceive('getAppAccessToken')->andReturn('app-token');
        $mock->shouldNotReceive('getSubscriptions');
        $mock->shouldReceive('deleteSubscription')->andReturnUsing(function ($token, $id) use (&$deleted) {
            $deleted[] = $id;

            return true;
        });
    });

    $this->artisan('eventsub:monitor', ['--fix' => true])->run();

    expect($deleted)->toBe(['sub-channel.follow'])
        ->and(followRows($user)->first()->status)->toBe('not_found_on_twitch')
        ->and(UserEventsubSubscription::where('user_id', $user->id)->where('status', 'enabled')->count())
        ->toBe(count(UserEventSubManager::SUPPORTED_EVENTS) - 1);

    Bus::assertDispatchedTimes(SetupUserEventSubSubscriptions::class, 1);
    Bus::assertDispatched(SetupUserEventSubSubscriptions::class, function (SetupUserEventSubSubscriptions $job) {
        return (new ReflectionProperty($job, 'forceRecreate'))->getValue($job) === false;
    });
});

it('only reports failed subscriptions without --fix', function () {
    Bus::fake([SetupUserEventSubSubscriptions::class]);
    $user = streamerWithDeadFollow('authorization_revoked');

    $this->mock(TwitchEventSubService::class, function ($mock) {
        $mock->shouldNotReceive('deleteSubscription');
    });

    $this->artisan('eventsub:monitor')->run();

    expect(followRows($user)->first()->status)->toBe('authorization_revoked');
    Bus::assertNotDispatched(SetupUserEventSubSubscriptions::class);
});
