<?php

use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function authenticatedUser(): User
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    test()->actingAs($user);

    return $user;
}

// ──────────────────────────────────────────────────────────────────────────────
// OAuth redirect
// ──────────────────────────────────────────────────────────────────────────────

test('redirect sends user to the Streamlabs v2.0 authorize URL with a state the session remembers', function () {
    authenticatedUser();

    $response = $this->get('/settings/integrations/streamlabs/redirect');

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toStartWith('https://streamlabs.com/api/v2.0/authorize')
        ->and($location)->not->toContain('v1.0')
        ->and($location)->toContain('response_type=code')
        ->and($location)->toContain('scope=socket.token+donations.read');

    parse_str(parse_url($location, PHP_URL_QUERY), $query);
    $response->assertSessionHas('streamlabs_oauth_state', $query['state']);
    expect($query['state'])->toHaveLength(40);
});

// ──────────────────────────────────────────────────────────────────────────────
// OAuth callback
// ──────────────────────────────────────────────────────────────────────────────

test('callback without code redirects back with error', function () {
    authenticatedUser();

    $this->get('/auth/callback/streamlabs')
        ->assertRedirect(route('settings.integrations.streamlabs.show'));
});

test('callback with valid code creates integration and provisions controls', function () {
    $user = authenticatedUser();

    Http::fake([
        'streamlabs.com/api/v2.0/token' => Http::response([
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'token_type' => 'Bearer',
        ]),
        'streamlabs.com/api/v2.0/socket/token' => Http::response([
            'socket_token' => 'test-socket-token',
        ]),
    ]);

    $this->withSession(['streamlabs_oauth_state' => 'test-state'])
        ->get('/auth/callback/streamlabs?code=test-auth-code&state=test-state')
        ->assertRedirect(route('settings.integrations.streamlabs.show'))
        ->assertSessionHas('success');

    // v2.0: the code goes to /token as a form body, and the socket token is
    // fetched with the access token as a Bearer header, never a query param.
    Http::assertSent(fn (Request $req) => str_starts_with($req->url(), 'https://streamlabs.com/api/v2.0/token')
        && $req->isForm()
        && $req['grant_type'] === 'authorization_code'
        && $req['code'] === 'test-auth-code');
    Http::assertSent(fn (Request $req) => $req->url() === 'https://streamlabs.com/api/v2.0/socket/token'
        && $req->hasHeader('Authorization', 'Bearer test-access-token'));
    Http::assertNotSent(fn (Request $req) => str_contains($req->url(), 'v1.0'));

    // Verify integration was created
    $integration = ExternalIntegration::where('user_id', $user->id)
        ->where('service', 'streamlabs')
        ->first();

    expect($integration)->not()->toBeNull()
        ->and($integration->enabled)->toBeTrue();

    $credentials = $integration->getCredentialsDecrypted();
    expect($credentials['access_token'])->toBe('test-access-token')
        ->and($credentials['refresh_token'])->toBe('test-refresh-token')
        ->and($credentials['socket_token'])->toBe('test-socket-token')
        ->and($credentials['listener_secret'])->toBeString()->toHaveLength(64);
    // bin2hex(32 bytes)

    // Verify controls were provisioned
    $controls = OverlayControl::where('user_id', $user->id)
        ->where('source', 'streamlabs')
        ->where('source_managed', true)
        ->get();

    expect($controls)->toHaveCount(6);
});

test('callback with a state the session did not hand out exchanges nothing', function () {
    $user = authenticatedUser();

    Http::fake();

    $this->withSession(['streamlabs_oauth_state' => 'the-real-state'])
        ->get('/auth/callback/streamlabs?code=test-auth-code&state=forged-state')
        ->assertRedirect(route('settings.integrations.streamlabs.show'))
        ->assertSessionHas('error');

    Http::assertNothingSent();
    expect(ExternalIntegration::where('user_id', $user->id)->where('service', 'streamlabs')->exists())->toBeFalse();
});

test('callback with no state in the session exchanges nothing', function () {
    $user = authenticatedUser();

    Http::fake();

    $this->get('/auth/callback/streamlabs?code=test-auth-code&state=anything')
        ->assertRedirect(route('settings.integrations.streamlabs.show'))
        ->assertSessionHas('error');

    Http::assertNothingSent();
    expect(ExternalIntegration::where('user_id', $user->id)->where('service', 'streamlabs')->exists())->toBeFalse();
});

test('the state is single use', function () {
    authenticatedUser();

    Http::fake([
        'streamlabs.com/api/v2.0/token' => Http::response(['access_token' => 'a', 'refresh_token' => 'r']),
        'streamlabs.com/api/v2.0/socket/token' => Http::response(['socket_token' => 's']),
    ]);

    $this->withSession(['streamlabs_oauth_state' => 'once'])
        ->get('/auth/callback/streamlabs?code=c1&state=once')
        ->assertSessionHas('success')
        ->assertSessionMissing('streamlabs_oauth_state');
});

test('callback when token exchange fails redirects with error', function () {
    authenticatedUser();

    Http::fake([
        'streamlabs.com/api/v2.0/token' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    $this->withSession(['streamlabs_oauth_state' => 'test-state'])
        ->get('/auth/callback/streamlabs?code=bad-code&state=test-state')
        ->assertRedirect(route('settings.integrations.streamlabs.show'))
        ->assertSessionHas('error');
});

// ──────────────────────────────────────────────────────────────────────────────
// Disconnect
// ──────────────────────────────────────────────────────────────────────────────

test('disconnect deletes integration and deprovisions controls', function () {
    $user = authenticatedUser();

    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'streamlabs',
        'enabled' => true,
    ]);

    OverlayControl::provisionServiceControl($user, 'streamlabs', [
        'key' => 'donations_received', 'type' => 'counter', 'label' => 'Donations', 'value' => '0',
    ]);

    $this->delete('/settings/integrations/streamlabs')
        ->assertRedirect(route('settings.integrations.index'));

    expect(ExternalIntegration::find($integration->id))->toBeNull();

    $controls = OverlayControl::where('user_id', $user->id)
        ->where('source', 'streamlabs')
        ->count();

    expect($controls)->toBe(0);
});
