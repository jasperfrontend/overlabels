<?php

use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * The "starting total" seed on every donation-style integration writes to the
 * `total_received` control, which is money and not a tally. These tests pin the
 * one shape the endpoint accepts: a dot-separated number with at most two
 * decimals. Locale notation ("65,35") is the frontend's job to normalize, so it
 * is rejected here on purpose.
 */
$services = ['kofi', 'streamlabs', 'fourthwall', 'bmac', 'throne'];

function seedableUser(string $service): User
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    test()->actingAs($user);

    ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => $service,
        'settings' => [],
    ]);

    OverlayControl::provisionServiceControl($user, $service, [
        'key' => 'total_received',
        'type' => 'number',
        'label' => 'Total Received',
        'value' => '0',
    ]);

    return $user;
}

function seededTotal(User $user, string $service): ?string
{
    return OverlayControl::where('user_id', $user->id)
        ->where('source', $service)
        ->where('key', 'total_received')
        ->value('value');
}

test('seed accepts a fractional amount', function (string $service) {
    $user = seedableUser($service);

    $this->postJson("/settings/integrations/$service/seed-count", ['initial_count' => 65.35])
        ->assertOk()
        ->assertJson(['donations_seed_set' => true, 'donations_seed_value' => 65.35]);

    expect(seededTotal($user, $service))->toBe('65.35');

    $settings = ExternalIntegration::where('user_id', $user->id)->where('service', $service)->value('settings');
    expect($settings['donations_seed_value'])->toBe(65.35);
})->with($services);

test('seed keeps a whole amount whole', function (string $service) {
    // Guards the overlay: a seed of 1256 must not start rendering as "1256.00"
    // in templates that never had decimals.
    $user = seedableUser($service);

    $this->postJson("/settings/integrations/$service/seed-count", ['initial_count' => 1256])
        ->assertOk()
        ->assertJson(['donations_seed_value' => 1256]);

    expect(seededTotal($user, $service))->toBe('1256');
})->with($services);

test('seed rejects more precision than money has', function (string $service) {
    $user = seedableUser($service);

    $this->postJson("/settings/integrations/$service/seed-count", ['initial_count' => 65.355])
        ->assertStatus(422)
        ->assertJsonValidationErrors('initial_count');

    expect(seededTotal($user, $service))->toBe('0');
})->with($services);

test('seed rejects locale notation, which the frontend normalizes first', function (string $service) {
    $user = seedableUser($service);

    $this->postJson("/settings/integrations/$service/seed-count", ['initial_count' => '65,35'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('initial_count');

    expect(seededTotal($user, $service))->toBe('0');
})->with($services);

test('seed rejects a negative amount', function (string $service) {
    $user = seedableUser($service);

    $this->postJson("/settings/integrations/$service/seed-count", ['initial_count' => -1])
        ->assertStatus(422)
        ->assertJsonValidationErrors('initial_count');

    expect(seededTotal($user, $service))->toBe('0');
})->with($services);

// --- Admin override ------------------------------------------------------------

test('the admin override seeds the monetary total, not the donation count', function (string $service) {
    // `donations_received` counts how many donations arrived; `total_received`
    // is what they were worth. The admin panel wrote the euro amount into the
    // counter, silently inflating it. Both controls exist here so the test can
    // see the value land in one and leave the other alone.
    $user = seedableUser($service);

    OverlayControl::provisionServiceControl($user, $service, [
        'key' => 'donations_received',
        'type' => 'counter',
        'label' => 'Donations Received',
        'value' => '0',
    ]);

    $admin = User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'role' => 'admin',
    ]);

    $this->actingAs($admin)
        ->post("/admin/users/$user->id/integration-seed/$service", ['initial_count' => 65.35])
        ->assertRedirect();

    expect(seededTotal($user, $service))->toBe('65.35')
        ->and(OverlayControl::where('user_id', $user->id)
            ->where('source', $service)
            ->where('key', 'donations_received')
            ->value('value'))->toBe('0');
})->with($services);

test('the admin override accepts a seed the user already set as a decimal', function (string $service) {
    // The admin form pre-fills from donations_seed_value and posts it straight
    // back, so a user-set 65.35 has to survive the round trip.
    $user = seedableUser($service);
    $this->postJson("/settings/integrations/$service/seed-count", ['initial_count' => 65.35])->assertOk();

    $admin = User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'role' => 'admin',
    ]);

    $this->actingAs($admin)
        ->post("/admin/users/$user->id/integration-seed/$service", ['initial_count' => 65.35])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(seededTotal($user, $service))->toBe('65.35');
})->with($services);

test('a fractional seed survives a test-mode round trip', function (string $service) {
    // Turning test mode off resets service-managed controls, and the seeded
    // total is the one value that must come back instead of dropping to 0.
    $user = seedableUser($service);

    $this->postJson("/settings/integrations/$service/seed-count", ['initial_count' => 65.35])->assertOk();

    $this->patchJson("/settings/integrations/$service/test-mode", ['test_mode' => true])->assertOk();
    $this->patchJson("/settings/integrations/$service/test-mode", ['test_mode' => false])->assertOk();

    expect(seededTotal($user, $service))->toBe('65.35');
})->with($services);
