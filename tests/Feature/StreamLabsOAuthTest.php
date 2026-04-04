<?php

use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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

test('redirect sends user to StreamLabs authorize URL', function () {
    authenticatedUser();

    $response = $this->get('/settings/integrations/streamlabs/redirect');

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toStartWith('https://www.streamlabs.com/api/v1.0/authorize')
        ->and($location)->toContain('response_type=code')
        ->and($location)->toContain('scope=socket.token+donations.read');
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
        'streamlabs.com/api/v1.0/token' => Http::response([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
        ]),
        'streamlabs.com/api/v1.0/socket/token' => Http::response([
            'socket_token' => 'test-socket-token',
        ]),
    ]);

    $this->get('/auth/callback/streamlabs?code=test-auth-code')
        ->assertRedirect(route('settings.integrations.streamlabs.show'));

    // Verify integration was created
    $integration = ExternalIntegration::where('user_id', $user->id)
        ->where('service', 'streamlabs')
        ->first();

    expect($integration)->not()->toBeNull()
        ->and($integration->enabled)->toBeTrue();

    $credentials = $integration->getCredentialsDecrypted();
    expect($credentials['access_token'])->toBe('test-access-token')
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

test('callback when token exchange fails redirects with error', function () {
    authenticatedUser();

    Http::fake([
        'streamlabs.com/api/v2.0/token' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    $this->get('/auth/callback/streamlabs?code=bad-code')
        ->assertRedirect(route('settings.integrations.streamlabs.show'));
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
