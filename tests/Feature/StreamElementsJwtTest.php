<?php

use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function authenticatedStreamElementsUser(): User
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    test()->actingAs($user);

    return $user;
}

// ──────────────────────────────────────────────────────────────────────────────
// Show
// ──────────────────────────────────────────────────────────────────────────────

test('show renders disconnected state when no integration exists', function () {
    authenticatedStreamElementsUser();

    $this->get('/settings/integrations/streamelements')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/integrations/streamelements')
            ->where('integration.connected', false)
            ->where('integration.has_jwt', false)
        );
});

// ──────────────────────────────────────────────────────────────────────────────
// Save (JWT)
// ──────────────────────────────────────────────────────────────────────────────

test('save stores jwt encrypted, generates listener secret, and provisions controls', function () {
    $user = authenticatedStreamElementsUser();

    $this->post('/settings/integrations/streamelements', [
        'jwt_token' => 'fake.jwt.token',
    ])->assertRedirect();

    $integration = ExternalIntegration::where('user_id', $user->id)
        ->where('service', 'streamelements')
        ->first();

    expect($integration)->not()->toBeNull()
        ->and($integration->enabled)->toBeTrue();

    $credentials = $integration->getCredentialsDecrypted();
    expect($credentials['jwt_token'])->toBe('fake.jwt.token')
        ->and($credentials['listener_secret'])->toBeString()->toHaveLength(64);

    $controls = OverlayControl::where('user_id', $user->id)
        ->where('source', 'streamelements')
        ->where('source_managed', true)
        ->get();

    expect($controls)->toHaveCount(6);
});

test('save preserves existing listener secret when replacing JWT', function () {
    $user = authenticatedStreamElementsUser();

    $this->post('/settings/integrations/streamelements', ['jwt_token' => 'first.jwt.token']);

    $integration = ExternalIntegration::where('user_id', $user->id)
        ->where('service', 'streamelements')
        ->first();
    $originalSecret = $integration->getCredentialsDecrypted()['listener_secret'];

    $this->post('/settings/integrations/streamelements', ['jwt_token' => 'second.jwt.token']);

    $integration->refresh();
    $credentials = $integration->getCredentialsDecrypted();

    expect($credentials['jwt_token'])->toBe('second.jwt.token')
        ->and($credentials['listener_secret'])->toBe($originalSecret);
});

test('save does not re-provision controls on subsequent saves', function () {
    $user = authenticatedStreamElementsUser();

    $this->post('/settings/integrations/streamelements', ['jwt_token' => 'first.jwt.token']);
    $this->post('/settings/integrations/streamelements', ['jwt_token' => 'second.jwt.token']);

    $controls = OverlayControl::where('user_id', $user->id)
        ->where('source', 'streamelements')
        ->where('source_managed', true)
        ->count();

    expect($controls)->toBe(6);
});

test('save rejects missing jwt_token', function () {
    authenticatedStreamElementsUser();

    $this->post('/settings/integrations/streamelements', [])
        ->assertSessionHasErrors('jwt_token');
});

// ──────────────────────────────────────────────────────────────────────────────
// Disconnect
// ──────────────────────────────────────────────────────────────────────────────

test('disconnect deletes integration and deprovisions controls', function () {
    $user = authenticatedStreamElementsUser();

    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'streamelements',
        'enabled' => true,
    ]);

    OverlayControl::provisionServiceControl($user, 'streamelements', [
        'key' => 'donations_received', 'type' => 'counter', 'label' => 'Donations', 'value' => '0',
    ]);

    $this->delete('/settings/integrations/streamelements')
        ->assertRedirect(route('settings.integrations.index'));

    expect(ExternalIntegration::find($integration->id))->toBeNull();

    $controls = OverlayControl::where('user_id', $user->id)
        ->where('source', 'streamelements')
        ->count();

    expect($controls)->toBe(0);
});
