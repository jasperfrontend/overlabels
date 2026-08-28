<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpFoundation\Cookie;

uses(RefreshDatabase::class);

/**
 * Sessions expire after SESSION_LIFETIME - 120 minutes of idle by default, and
 * prod does not override it. Without a remember cookie there is no recovery
 * path, so two hours away from the site means re-authenticating through Twitch.
 * That is what made logging in a daily chore.
 *
 * These pin the remember cookie end to end: that the callback asks for one, that
 * the column backing it exists so the guard can cycle a token, that a banned
 * user never keeps one, and that it is never serialised out to a client.
 */
function fakeTwitchLogin(string $twitchId = '123456789'): void
{
    Http::fake();
    Queue::fake();

    $socialiteUser = (new SocialiteUser)->map([
        'id' => $twitchId,
        'nickname' => 'testbroadcaster',
        'name' => 'Test Broadcaster',
        'avatar' => 'https://example.test/avatar.png',
    ]);
    $socialiteUser->user = ['id' => $twitchId, 'login' => 'testbroadcaster'];
    $socialiteUser->token = 'access-token';
    $socialiteUser->refreshToken = 'refresh-token';
    $socialiteUser->expiresIn = 3600;
    $socialiteUser->approvedScopes = ['channel:read:subscriptions'];

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('twitch')->andReturn($provider);
}

/** The remember cookie's name is hashed per guard, so match on the prefix. */
function rememberCookie(TestResponse $response): ?Cookie
{
    foreach ($response->headers->getCookies() as $cookie) {
        if (str_starts_with($cookie->getName(), 'remember_web')) {
            return $cookie;
        }
    }

    return null;
}

it('issues a remember cookie when logging in through Twitch', function () {
    // eventsub_auto_connect false keeps the callback from dispatching the
    // subscription setup job - not what is under test here.
    $user = User::factory()->create([
        'twitch_id' => '123456789',
        'eventsub_auto_connect' => false,
        'eventsub_connected_at' => now(),
    ]);

    fakeTwitchLogin();

    $response = $this->get('/auth/callback/twitch');

    // A redirect to '/' instead means the callback threw and was swallowed by
    // its own try/catch.
    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);

    $cookie = rememberCookie($response);

    expect($cookie)->not->toBeNull()
        ->and($cookie->getValue())->not->toBeEmpty()
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addYear()->getTimestamp());
});

it('persists a remember token so the login survives session expiry', function () {
    $user = User::factory()->create([
        'twitch_id' => '123456789',
        'eventsub_auto_connect' => false,
        'eventsub_connected_at' => now(),
    ]);

    expect($user->remember_token)->toBeNull();

    fakeTwitchLogin();

    $this->get('/auth/callback/twitch')->assertRedirect('/dashboard');

    // Without the remember_token column the guard cannot cycle a token at all,
    // and the callback fails outright.
    expect($user->fresh()->remember_token)->not->toBeNull();
});

it('does not leave a banned user holding a remember cookie', function () {
    $user = User::factory()->create([
        'twitch_id' => '123456789',
        'eventsub_auto_connect' => false,
        'eventsub_connected_at' => now(),
    ]);
    $user->ban();

    fakeTwitchLogin();

    // Auth::login runs before the ban check, so a cookie is queued and then has
    // to be revoked. A 5-year login credential handed to a banned account would
    // outlive the ban itself.
    //
    // The 404 is the point: a banned requester gets the same hard 404 here as on
    // every other route. The callback's catch-all used to swallow it - an
    // HttpException is an Exception - and hand out the friendly "Authentication
    // failed" redirect instead, which is a different answer from the rest of the
    // app and tells a banned account that logging in is worth retrying.
    $response = $this->get('/auth/callback/twitch');
    $response->assertNotFound();
    $this->assertGuest();

    $cookie = rememberCookie($response);

    if ($cookie !== null) {
        expect($cookie->getValue())->toBeEmpty();
    }
});

it('never serialises the remember token', function () {
    $user = User::factory()->create();
    $user->forceFill(['remember_token' => 'a-persistent-login-credential'])->save();

    expect($user->fresh()->toArray())->not->toHaveKey('remember_token');
});
