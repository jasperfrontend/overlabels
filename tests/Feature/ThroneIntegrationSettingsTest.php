<?php

use App\Models\ExternalIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function authenticatedThroneUser(): User
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    test()->actingAs($user);

    return $user;
}

function connectThrone(User $user): ExternalIntegration
{
    test()->post('/settings/integrations/throne')->assertRedirect();

    return ExternalIntegration::where('user_id', $user->id)->where('service', 'throne')->firstOrFail();
}

// --- Show ----------------------------------------------------------------------

test('throne show renders the disconnected state when no integration exists', function () {
    authenticatedThroneUser();

    $this->get('/settings/integrations/throne')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/integrations/throne')
            ->where('integration.connected', false)
            ->where('integration.webhook_url', null)
        );
});

// --- Connect -------------------------------------------------------------------

test('throne connect creates an enabled integration with a generated webhook token', function () {
    $user = authenticatedThroneUser();

    $integration = connectThrone($user);

    expect($integration->enabled)->toBeTrue()
        ->and($integration->webhook_token)->toBeString()->not()->toBeEmpty()
        // Connecting stores no credentials: Throne uses its own global signing key.
        ->and($integration->getCredentialsDecrypted())->toBe([]);
});

test('throne connect is idempotent and does not duplicate the integration', function () {
    $user = authenticatedThroneUser();

    connectThrone($user);
    $token = ExternalIntegration::where('user_id', $user->id)->where('service', 'throne')->value('webhook_token');

    // A second connect must not create a second row or rotate the token.
    $this->post('/settings/integrations/throne')->assertRedirect();

    expect(ExternalIntegration::where('user_id', $user->id)->where('service', 'throne')->count())->toBe(1)
        ->and(ExternalIntegration::where('user_id', $user->id)->where('service', 'throne')->value('webhook_token'))->toBe($token);
});

test('throne show surfaces the webhook url once connected', function () {
    $user = authenticatedThroneUser();
    $integration = connectThrone($user);

    $this->get('/settings/integrations/throne')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('integration.connected', true)
            ->where('integration.webhook_url', url("/api/webhooks/throne/{$integration->webhook_token}"))
        );
});

// --- Test mode -----------------------------------------------------------------

test('throne test-mode toggle persists', function () {
    $user = authenticatedThroneUser();
    connectThrone($user);

    $this->patch('/settings/integrations/throne/test-mode', ['test_mode' => true])
        ->assertOk()
        ->assertJson(['test_mode' => true]);

    expect(ExternalIntegration::where('user_id', $user->id)->where('service', 'throne')->value('test_mode'))->toBeTrue();
});

test('throne test-mode returns 404 when not connected', function () {
    authenticatedThroneUser();

    $this->patchJson('/settings/integrations/throne/test-mode', ['test_mode' => true])
        ->assertStatus(404);
});

// --- Seed ----------------------------------------------------------------------

test('throne seed-count records the starting value', function () {
    $user = authenticatedThroneUser();
    connectThrone($user);

    $this->post('/settings/integrations/throne/seed-count', ['initial_count' => 1256])
        ->assertOk()
        ->assertJson(['donations_seed_set' => true, 'donations_seed_value' => 1256]);

    $settings = ExternalIntegration::where('user_id', $user->id)->where('service', 'throne')->value('settings');
    expect($settings['donations_seed_set'])->toBeTrue()
        ->and($settings['donations_seed_value'])->toBe(1256);
});

// --- Disconnect ----------------------------------------------------------------

test('throne disconnect removes the integration', function () {
    $user = authenticatedThroneUser();
    connectThrone($user);

    $this->delete('/settings/integrations/throne')
        ->assertRedirect(route('settings.integrations.index'));

    expect(ExternalIntegration::where('user_id', $user->id)->where('service', 'throne')->exists())->toBeFalse();
});
