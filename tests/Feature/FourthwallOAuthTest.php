<?php

use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function fwActingUser(): User
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    test()->actingAs($user);

    return $user;
}

function fwConfigureEnv(): void
{
    config([
        'services.fourthwall.client_id' => 'test-client-id',
        'services.fourthwall.client_secret' => 'test-client-secret',
        'services.fourthwall.auth_url' => 'https://my-shop.fourthwall.com/admin/platform-apps/test-client-id/connect?redirect_uri=',
        'services.fourthwall.redirect_url' => 'https://overlabels.test/auth/redirect/fw',
        'services.fourthwall.api_base' => 'https://api.fourthwall.com',
    ]);
}

// ──────────────────────────────────────────────────────────────────────────────
// OAuth redirect
// ──────────────────────────────────────────────────────────────────────────────

test('redirect sends user to Fourthwall authorize URL with state in session', function () {
    fwActingUser();
    fwConfigureEnv();

    $response = $this->get('/settings/integrations/fourthwall/redirect');

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toStartWith('https://my-shop.fourthwall.com/admin/platform-apps/test-client-id/connect?redirect_uri=')
        ->and($location)->toContain('state=');

    expect(session('fw_oauth_state'))->toBeString()->not()->toBeEmpty();
});

// ──────────────────────────────────────────────────────────────────────────────
// OAuth callback
// ──────────────────────────────────────────────────────────────────────────────

test('callback without code redirects back with error', function () {
    fwActingUser();
    fwConfigureEnv();

    $this->get('/auth/redirect/fw')
        ->assertRedirect(route('settings.integrations.fourthwall.show'));
});

test('callback with mismatched state redirects back with error', function () {
    fwActingUser();
    fwConfigureEnv();

    $this->withSession(['fw_oauth_state' => 'expected-state'])
        ->get('/auth/redirect/fw?code=foo&state=wrong-state')
        ->assertRedirect(route('settings.integrations.fourthwall.show'));

    expect(ExternalIntegration::where('service', 'fourthwall')->count())->toBe(0);
});

test('callback with valid code creates integration, registers webhook, provisions controls', function () {
    $user = fwActingUser();
    fwConfigureEnv();

    Http::fake([
        'api.fourthwall.com/open-api/v1.0/platform/token' => Http::response([
            'access_token' => 'fw-access-token',
            'refresh_token' => 'fw-refresh-token',
            'expires_in' => 300,
        ]),
        'api.fourthwall.com/open-api/v1.0/webhooks' => Http::response([
            'id' => 'wh_abc123',
            'url' => 'https://overlabels.test/api/webhooks/fourthwall/whatever',
            'allowedTypes' => ['DONATION'],
            'apiVersion' => 'V1',
        ]),
    ]);

    $this->withSession(['fw_oauth_state' => 'st8'])
        ->get('/auth/redirect/fw?code=valid-code&state=st8')
        ->assertRedirect(route('settings.integrations.fourthwall.show'));

    $integration = ExternalIntegration::where('user_id', $user->id)
        ->where('service', 'fourthwall')
        ->first();

    expect($integration)->not()->toBeNull()
        ->and($integration->enabled)->toBeTrue();

    $credentials = $integration->getCredentialsDecrypted();
    expect($credentials['access_token'])->toBe('fw-access-token')
        ->and($credentials['refresh_token'])->toBe('fw-refresh-token')
        ->and($credentials['webhook_id'])->toBe('wh_abc123')
        ->and($credentials['expires_at'])->toBeString()
        ->and($credentials)->not()->toHaveKey('webhook_secret');

    $controls = OverlayControl::where('user_id', $user->id)
        ->where('source', 'fourthwall')
        ->where('source_managed', true)
        ->get();

    expect($controls)->toHaveCount(6);
});

test('callback rolls back new integration when webhook registration fails', function () {
    $user = fwActingUser();
    fwConfigureEnv();

    Http::fake([
        'api.fourthwall.com/open-api/v1.0/platform/token' => Http::response([
            'access_token' => 'fw-access-token',
            'refresh_token' => 'fw-refresh-token',
            'expires_in' => 300,
        ]),
        'api.fourthwall.com/open-api/v1.0/webhooks' => Http::response(['error' => 'insufficient_scope'], 403),
    ]);

    $this->withSession(['fw_oauth_state' => 'st8'])
        ->get('/auth/redirect/fw?code=valid-code&state=st8')
        ->assertRedirect(route('settings.integrations.fourthwall.show'));

    expect(ExternalIntegration::where('user_id', $user->id)->where('service', 'fourthwall')->count())->toBe(0);
    expect(OverlayControl::where('user_id', $user->id)->where('source', 'fourthwall')->count())->toBe(0);
});

test('callback when token exchange fails redirects with error', function () {
    fwActingUser();
    fwConfigureEnv();

    Http::fake([
        'api.fourthwall.com/open-api/v1.0/platform/token' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    $this->withSession(['fw_oauth_state' => 'st8'])
        ->get('/auth/redirect/fw?code=bad-code&state=st8')
        ->assertRedirect(route('settings.integrations.fourthwall.show'));

    expect(ExternalIntegration::where('service', 'fourthwall')->count())->toBe(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Disconnect
// ──────────────────────────────────────────────────────────────────────────────

test('disconnect deregisters webhook, deprovisions controls, and deletes integration', function () {
    $user = fwActingUser();
    fwConfigureEnv();

    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'fourthwall',
        'enabled' => true,
        'credentials' => Crypt::encryptString(json_encode([
            'access_token' => 'fw-token',
            'refresh_token' => 'fw-refresh',
            'expires_at' => now()->addHour()->toIso8601String(),
            'webhook_id' => 'wh_to_delete',
        ])),
    ]);

    OverlayControl::provisionServiceControl($user, 'fourthwall', [
        'key' => 'donations_received', 'type' => 'counter', 'label' => 'Donations', 'value' => '0',
    ]);

    Http::fake([
        'api.fourthwall.com/open-api/v1.0/webhooks/wh_to_delete' => Http::response([], 204),
    ]);

    $this->delete('/settings/integrations/fourthwall')
        ->assertRedirect(route('settings.integrations.index'));

    expect(ExternalIntegration::find($integration->id))->toBeNull();
    expect(OverlayControl::where('user_id', $user->id)->where('source', 'fourthwall')->count())->toBe(0);

    Http::assertSent(fn ($req) => $req->method() === 'DELETE'
        && str_contains($req->url(), '/open-api/v1.0/webhooks/wh_to_delete')
    );
});

test('disconnect still removes local state when remote deregister fails', function () {
    $user = fwActingUser();
    fwConfigureEnv();

    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'fourthwall',
        'enabled' => true,
        'credentials' => Crypt::encryptString(json_encode([
            'access_token' => 'fw-token',
            'refresh_token' => 'fw-refresh',
            'expires_at' => now()->addHour()->toIso8601String(),
            'webhook_id' => 'wh_orphan',
        ])),
    ]);

    Http::fake([
        'api.fourthwall.com/*' => Http::response(['error' => 'server_error'], 500),
    ]);

    $this->delete('/settings/integrations/fourthwall')
        ->assertRedirect(route('settings.integrations.index'));

    expect(ExternalIntegration::find($integration->id))->toBeNull();
});
