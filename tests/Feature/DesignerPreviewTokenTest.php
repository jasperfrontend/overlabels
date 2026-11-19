<?php

use App\Models\OverlayAccessToken;
use App\Models\OverlayTemplate;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Services\TwitchApiService;
use App\Services\TwitchTokenService;
use App\Support\ProductDesigner;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * The designer's preview token sits in the frame's URL, so "Open frame in New
 * Tab" puts it in an address bar, possibly on stream. It is bound to the
 * browser that opened the designer by a cookie: without it the token renders
 * nothing, signs no channel, and it never reads the events feed or a List at
 * all. It lives two hours past its last renewal, and the open designer renews
 * it without changing it.
 */
beforeEach(function () {
    config()->set('broadcasting.connections.reverb.key', 'test-key');
    config()->set('broadcasting.connections.reverb.secret', 'test-secret');

    $this->mock(TwitchTokenService::class, function ($mock) {
        $mock->shouldReceive('ensureValidToken')->andReturnTrue();
    });
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('getExtendedUserData')->andReturn([]);
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($t, $e) => $e);
    });
});

function previewUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'previewtester'.fake()->unique()->randomNumber(5)],
        'access_token' => 'fake-twitch-token',
    ]);
}

function previewInstall(User $user): OverlayTemplate
{
    $catalog = app(RecipeCatalog::class);
    $manifest = $catalog->find('twitch-chat-overlay');
    $instance = app(RecipeInstaller::class)->install($catalog->sync($manifest), $user, RecipeInstance::instanceSlugFrom('twitch-chat-overlay'));

    return ProductDesigner::overlayFor(ProductDesigner::declared($manifest), $instance);
}

/** @return array{token: string, cookie: string} */
function openDesigner(User $user): array
{
    $response = test()->actingAs($user)->get('/products/twitch-chat-overlay/design');
    $url = $response->viewData('page')['props']['preview_url'];
    $cookie = $response->getCookie(ProductDesigner::BINDING_COOKIE, false);

    return ['token' => substr($url, strpos($url, '#') + 1), 'cookie' => $cookie?->getValue() ?? ''];
}

function obsToken(User $user): string
{
    $material = OverlayAccessToken::generateToken();
    OverlayAccessToken::factory()->create([
        'user_id' => $user->id,
        'token_hash' => $material['hash'],
        'token_prefix' => $material['prefix'],
        'is_active' => true,
        'expires_at' => null,
        'allowed_ips' => null,
        'metadata' => null,
        'abilities' => null,
    ]);

    return $material['plain'];
}

it('lives two hours and sets an http-only, strict binding cookie for the overlay API only', function () {
    $user = previewUser();
    previewInstall($user);

    $response = $this->actingAs($user)->get('/products/twitch-chat-overlay/design');
    $cookie = $response->getCookie(ProductDesigner::BINDING_COOKIE, false);

    expect($cookie)->not->toBeNull()
        ->and($cookie->getValue())->toMatch('/^[0-9a-f]{64}$/')
        ->and($cookie->getPath())->toBe('/api/overlay')
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('strict');

    $token = OverlayAccessToken::where('user_id', $user->id)->sole();
    expect($token->metadata['binding'])->toBe(hash('sha256', $cookie->getValue()))
        ->and($token->expires_at->between(now()->addMinutes(119), now()->addMinutes(121)))->toBeTrue();
});

it('renders the overlay for a preview token only in the browser that holds its cookie', function () {
    $user = previewUser();
    $template = previewInstall($user);
    ['token' => $token, 'cookie' => $cookie] = openDesigner($user);

    $body = ['slug' => $template->slug, 'token' => $token];

    $this->postJson('/api/overlay/render', $body)->assertUnauthorized();
    $this->withCredentials()->withUnencryptedCookie(ProductDesigner::BINDING_COOKIE, str_repeat('0', 64))
        ->postJson('/api/overlay/render', $body)->assertUnauthorized();
    $this->withCredentials()->withUnencryptedCookie(ProductDesigner::BINDING_COOKIE, $cookie)
        ->postJson('/api/overlay/render', $body)->assertOk();
});

it('signs the live channel and serves alert sources for a preview token only with its cookie', function () {
    $user = previewUser();
    $template = previewInstall($user);
    ['token' => $token, 'cookie' => $cookie] = openDesigner($user);

    $auth = ['slug' => $template->slug, 'token' => $token, 'socket_id' => '1.2', 'channel_name' => 'private-alerts.'.$user->twitch_id];
    $source = ['slug' => 'no-such-alert', 'token' => $token];

    $this->postJson('/api/overlay/broadcasting/auth', $auth)->assertUnauthorized();
    $this->postJson('/api/overlay/alert-source', $source)->assertUnauthorized();

    $this->withCredentials()->withUnencryptedCookie(ProductDesigner::BINDING_COOKIE, $cookie);
    $this->postJson('/api/overlay/broadcasting/auth', $auth)->assertOk();
    // Past the token check: the alert simply does not exist.
    $this->postJson('/api/overlay/alert-source', $source)->assertNotFound();
});

it('never reads the events feed or a List with a preview token, cookie or not', function () {
    $user = previewUser();
    previewInstall($user);
    ['token' => $token, 'cookie' => $cookie] = openDesigner($user);
    $obs = obsToken($user);

    $this->withCredentials()->withUnencryptedCookie(ProductDesigner::BINDING_COOKIE, $cookie);

    $this->getJson('/api/events?token='.$token)->assertUnauthorized();
    $this->getJson('/api/lists/nothing_here?token='.$token)->assertUnauthorized();

    // An ordinary token gets past the token check on both.
    $this->getJson('/api/events?token='.$obs)->assertOk();
    $this->getJson('/api/lists/nothing_here?token='.$obs)->assertNotFound();
});

it('leaves an OBS token alone, with no cookie at all', function () {
    $user = previewUser();
    $template = previewInstall($user);
    $obs = obsToken($user);

    $this->postJson('/api/overlay/render', ['slug' => $template->slug, 'token' => $obs])->assertOk();
    $this->postJson('/api/overlay/broadcasting/auth', [
        'slug' => $template->slug, 'token' => $obs, 'socket_id' => '1.2', 'channel_name' => 'private-alerts.'.$user->twitch_id,
    ])->assertOk();
});

it('renews the same token another two hours, so the frame never reloads', function () {
    $user = previewUser();
    previewInstall($user);
    ['token' => $token] = openDesigner($user);

    $this->travel(100)->minutes();

    $response = $this->actingAs($user)->post('/products/twitch-chat-overlay/design/preview-token');
    $response->assertNoContent();
    expect($response->getCookie(ProductDesigner::BINDING_COOKIE, false))->not->toBeNull();

    $row = OverlayAccessToken::findByToken($token);
    expect($row)->not->toBeNull()
        ->and($row->expires_at->between(now()->addMinutes(119), now()->addMinutes(121)))->toBeTrue()
        ->and(openDesigner($user)['token'])->toBe($token);
});

it('answers 409 once the held token is gone, and the next render mints a bound one', function () {
    $user = previewUser();
    previewInstall($user);
    ['token' => $first] = openDesigner($user);

    $this->travel(121)->minutes();

    $this->actingAs($user)->post('/products/twitch-chat-overlay/design/preview-token')->assertStatus(409);
    expect(OverlayAccessToken::findByToken($first))->toBeNull();

    ['token' => $second, 'cookie' => $cookie] = openDesigner($user);
    expect($second)->not->toBe($first)
        ->and(OverlayAccessToken::findByToken($second)->metadata['binding'])->toBe(hash('sha256', $cookie));
});

it('replaces a held token minted before the binding, which nothing would admit', function () {
    $user = previewUser();
    previewInstall($user);

    $material = OverlayAccessToken::generateToken();
    $user->overlayAccessTokens()->create([
        'name' => ProductDesigner::TOKEN_NAME,
        'token_hash' => $material['hash'],
        'token_prefix' => $material['prefix'],
        'expires_at' => now()->addDay(),
        'abilities' => 'read',
        'metadata' => ['purpose' => ProductDesigner::TOKEN_PURPOSE],
    ]);

    $this->withSession([ProductDesigner::TOKEN_SESSION_KEY => $material['plain']]);
    ['token' => $token] = openDesigner($user);

    expect($token)->not->toBe($material['plain'])
        ->and(OverlayAccessToken::findByToken($token)->metadata)->toHaveKey('binding');
});
