<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

/**
 * After a Twitch login the callback sends you back to where you were going,
 * which the login page stores from ?redirect_to=. Anyone can write that into a
 * link, so the callback follows it only when it is on our own site: otherwise
 * /login?redirect_to=https://evil.example was an open redirect that landed
 * people on a foreign site straight after a genuine login.
 */
function loginThroughTwitchAfterVisiting(string $redirectTo)
{
    User::factory()->create([
        'twitch_id' => '123456789',
        'eventsub_auto_connect' => false,
        'eventsub_connected_at' => now(),
    ]);

    Http::fake();
    Queue::fake();

    $socialiteUser = (new SocialiteUser)->map([
        'id' => '123456789',
        'nickname' => 'testbroadcaster',
        'name' => 'Test Broadcaster',
        'avatar' => 'https://example.test/avatar.png',
    ]);
    $socialiteUser->user = ['id' => '123456789', 'login' => 'testbroadcaster'];
    $socialiteUser->token = 'access-token';
    $socialiteUser->refreshToken = 'refresh-token';
    $socialiteUser->expiresIn = 3600;
    $socialiteUser->approvedScopes = ['channel:read:subscriptions'];

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('twitch')->andReturn($provider);

    test()->get('/login?redirect_to='.urlencode($redirectTo))->assertOk();

    return test()->get('/auth/callback/twitch');
}

it('returns to a page on our own site after logging in', function () {
    $target = url('/products/twitch-chat-plinko/install');

    loginThroughTwitchAfterVisiting($target)->assertRedirect($target);
});

it('returns to a site-relative path after logging in', function () {
    loginThroughTwitchAfterVisiting('/products/twitch-chat-plinko/install')
        ->assertRedirect(url('/products/twitch-chat-plinko/install'));
});

it('sends you to the dashboard instead of another site', function (string $redirectTo) {
    loginThroughTwitchAfterVisiting($redirectTo)->assertRedirect('/dashboard');
})->with([
    'another host' => 'https://evil.example/products/twitch-chat-plinko/install',
    'protocol-relative host' => '//evil.example/phish',
]);

// These are not foreign URLs to url(): it reads them as paths and puts our own
// host in front, so the worst they do is open a 404 on Overlabels. What matters
// is that the Location the browser follows never names another host.
it('keeps tricky addresses on our own host', function (string $redirectTo) {
    $location = loginThroughTwitchAfterVisiting($redirectTo)->headers->get('Location');

    expect(parse_url($location, PHP_URL_HOST))->toBe(parse_url(url('/'), PHP_URL_HOST));
})->with([
    'backslash host' => '/\\evil.example/phish',
    'another scheme' => 'javascript:alert(1)',
]);

it('still refuses a JSON endpoint on our own site', function () {
    loginThroughTwitchAfterVisiting(url('/api/overlay/render'))->assertRedirect('/dashboard');
});
