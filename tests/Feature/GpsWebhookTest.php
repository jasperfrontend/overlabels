<?php

use App\Events\ControlValuesBatchUpdated;
use App\Events\ControlValueUpdated;
use App\Models\ExternalEvent;
use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Models\User;
use App\Services\External\Drivers\GpsServiceDriver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

uses(DatabaseTransactions::class);

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function makeMobileIntegration(string $token = 'test-mobile-token', array $settings = []): array
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'gps',
        'enabled' => true,
        'credentials' => Crypt::encryptString(json_encode(['token' => $token])),
        'settings' => $settings,
    ]);

    return [$user, $integration];
}

function mobilePayload(array $overrides = []): array
{
    return array_merge([
        'latitude' => 52.3676,
        'longitude' => 4.9041,
        'speed' => 13.89, // ~50 km/h
        'altitude' => 10.5,
        'accuracy' => 5.0,
        'timestamp' => time(),
        'serial' => '1',
    ], $overrides);
}

function postMobile(string $webhookToken, array $payload, string $token = 'test-mobile-token'): TestResponse
{
    return test()->postJson(
        "/api/webhooks/overlabels-mobile/{$webhookToken}",
        $payload,
        ['X-GPSLogger-Token' => $token],
    );
}

// ──────────────────────────────────────────────────────────────────────────────
// Verification
// ──────────────────────────────────────────────────────────────────────────────

test('returns 403 when token does not match', function () {
    [, $integration] = makeMobileIntegration('correct-token');

    postMobile($integration->webhook_token, mobilePayload(), 'wrong-token')
        ->assertStatus(403);
});

// ──────────────────────────────────────────────────────────────────────────────
// Happy path
// ──────────────────────────────────────────────────────────────────────────────

test('returns 200 and stores event for valid GPS payload', function () {
    [$user, $integration] = makeMobileIntegration();
    $ts = time();

    postMobile($integration->webhook_token, mobilePayload(['timestamp' => $ts, 'serial' => '1']))
        ->assertStatus(200)
        ->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('external_events', [
        'user_id' => $user->id,
        'service' => 'gps',
        'event_type' => 'location_update',
        'message_id' => "gps_{$ts}_1",
    ]);
});

test('updates last_received_at on successful webhook', function () {
    [, $integration] = makeMobileIntegration();

    postMobile($integration->webhook_token, mobilePayload())->assertStatus(200);

    $integration->refresh();
    expect($integration->last_received_at)->not()->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Deduplication
// ──────────────────────────────────────────────────────────────────────────────

test('returns 200 with duplicate status for duplicate timestamp+serial', function () {
    [, $integration] = makeMobileIntegration();
    $payload = mobilePayload(['timestamp' => 1234567890, 'serial' => '42']);

    postMobile($integration->webhook_token, $payload)->assertStatus(200)->assertJson(['status' => 'ok']);
    postMobile($integration->webhook_token, $payload)->assertStatus(200)->assertJson(['status' => 'duplicate']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Control updates
// ──────────────────────────────────────────────────────────────────────────────

test('updates speed, lat, lng controls on location update', function () {
    Event::fake([ControlValuesBatchUpdated::class]);

    [$user, $integration] = makeMobileIntegration();

    // Pre-provision controls
    foreach (['speed', 'lat', 'lng', 'distance'] as $key) {
        OverlayControl::provisionServiceControl($user, 'gps', [
            'key' => $key,
            'type' => in_array($key, ['speed', 'distance']) ? 'number' : 'text',
            'label' => $key,
            'value' => '0',
        ]);
    }

    postMobile($integration->webhook_token, mobilePayload([
        'latitude' => 52.3676,
        'longitude' => 4.9041,
        'speed' => 13.89,
    ]))->assertStatus(200);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'gps',
        'key' => 'lat',
        'value' => '52.3676',
    ]);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'gps',
        'key' => 'lng',
        'value' => '4.9041',
    ]);

    // Speed stored as raw m/s; templates format with |speed:kmh / |speed:mph
    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'gps',
        'key' => 'speed',
        'value' => '13.89',
    ]);

    Event::assertDispatched(ControlValuesBatchUpdated::class);
});

test('updates bearing, battery, charging controls on location update', function () {
    Event::fake([ControlValuesBatchUpdated::class]);

    [$user, $integration] = makeMobileIntegration();

    foreach (['bearing', 'battery', 'charging', 'lat', 'lng'] as $key) {
        OverlayControl::provisionServiceControl($user, 'gps', [
            'key' => $key,
            'type' => $key === 'charging' ? 'boolean' : ($key === 'bearing' || $key === 'battery' ? 'number' : 'text'),
            'label' => $key,
            'value' => '0',
        ]);
    }

    postMobile($integration->webhook_token, mobilePayload([
        'bearing' => '26',
        'battery' => '54',
        'charging' => '0',
    ]))->assertStatus(200);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'gps',
        'key' => 'bearing',
        'value' => '26',
    ]);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'gps',
        'key' => 'battery',
        'value' => '54',
    ]);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'gps',
        'key' => 'charging',
        'value' => '0',
    ]);

    Event::assertDispatched(ControlValuesBatchUpdated::class);
});

test('a location ping produces one batched broadcast, not one per control', function () {
    Event::fake([ControlValuesBatchUpdated::class, ControlValueUpdated::class]);

    [$user, $integration] = makeMobileIntegration();

    foreach (['lat', 'lng', 'speed'] as $key) {
        OverlayControl::provisionServiceControl($user, 'gps', [
            'key' => $key,
            'type' => $key === 'speed' ? 'number' : 'text',
            'label' => $key,
            'value' => '0',
        ]);
    }

    postMobile($integration->webhook_token, mobilePayload([
        'latitude' => 52.0, 'longitude' => 4.0, 'speed' => 12.0,
    ]))->assertStatus(200);

    // The whole ping collapses into a single broadcast carrying every changed
    // key, instead of one ControlValueUpdated per control instance.
    Event::assertDispatched(ControlValuesBatchUpdated::class, 1);
    Event::assertDispatched(ControlValuesBatchUpdated::class, fn ($e) => count($e->updates) >= 3);
    Event::assertNotDispatched(ControlValueUpdated::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// Distance accumulation
// ──────────────────────────────────────────────────────────────────────────────

test('first ping stores position without adding distance', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $integration] = makeMobileIntegration();

    OverlayControl::provisionServiceControl($user, 'gps', [
        'key' => 'distance', 'type' => 'number', 'label' => 'Distance', 'value' => '0',
    ]);

    postMobile($integration->webhook_token, mobilePayload([
        'latitude' => 52.3676,
        'longitude' => 4.9041,
        'timestamp' => time(),
        'serial' => '1',
    ]))->assertStatus(200);

    // Distance should remain 0 (no previous position)
    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'gps',
        'key' => 'distance',
        'value' => '0',
    ]);

    // But last_lat/last_lng should be stored
    $integration->refresh();
    expect((float) $integration->settings['last_lat'])->toBe(52.3676);
    expect((float) $integration->settings['last_lng'])->toBe(4.9041);
});

test('second ping accumulates haversine distance', function () {
    Event::fake([ControlValueUpdated::class]);

    // Amsterdam (start)
    [$user, $integration] = makeMobileIntegration('test-mobile-token', [
        'last_lat' => 52.3676,
        'last_lng' => 4.9041,
    ]);

    OverlayControl::provisionServiceControl($user, 'gps', [
        'key' => 'distance', 'type' => 'number', 'label' => 'Distance', 'value' => '0',
    ]);
    OverlayControl::provisionServiceControl($user, 'gps', [
        'key' => 'speed', 'type' => 'number', 'label' => 'Speed', 'value' => '0',
    ]);
    OverlayControl::provisionServiceControl($user, 'gps', [
        'key' => 'lat', 'type' => 'text', 'label' => 'Lat', 'value' => '',
    ]);
    OverlayControl::provisionServiceControl($user, 'gps', [
        'key' => 'lng', 'type' => 'text', 'label' => 'Lng', 'value' => '',
    ]);

    // Move to a point ~1.1 km away (Rotterdam direction)
    postMobile($integration->webhook_token, mobilePayload([
        'latitude' => 52.3776,
        'longitude' => 4.9041,
        'timestamp' => time(),
        'serial' => '2',
    ]))->assertStatus(200);

    $distanceControl = OverlayControl::where('user_id', $user->id)
        ->where('source', 'gps')
        ->where('key', 'distance')
        ->first();

    // ~1.11 km for 0.01 degree latitude change at this position
    expect((float) $distanceControl->value)->toBeGreaterThan(0.5);
    expect((float) $distanceControl->value)->toBeLessThan(2.0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Bad-fix rejection
// ──────────────────────────────────────────────────────────────────────────────

test('out-of-range coordinate is rejected: no distance, no position broadcast', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $integration] = makeMobileIntegration('test-mobile-token', [
        'last_lat' => 52.3676,
        'last_lng' => 4.9041,
        'last_fix_at_unix' => time() - 5,
        'session_distance_km' => 3.0,
    ]);

    foreach (['distance', 'session_distance', 'lat', 'lng'] as $key) {
        OverlayControl::provisionServiceControl($user, 'gps', [
            'key' => $key,
            'type' => in_array($key, ['distance', 'session_distance']) ? 'number' : 'text',
            'label' => $key,
            'value' => $key === 'distance' ? '10' : ($key === 'session_distance' ? '3' : 'prev'),
        ]);
    }

    // lon = 1e150 style garbage (out of the valid -180..180 range).
    postMobile($integration->webhook_token, mobilePayload([
        'latitude' => 1.0,
        'longitude' => 1.0e150,
        'timestamp' => time(),
        'serial' => '2',
    ]))->assertStatus(200);

    // Distance counters untouched, position controls not overwritten with junk.
    $this->assertDatabaseHas('overlay_controls', ['user_id' => $user->id, 'source' => 'gps', 'key' => 'distance', 'value' => '10']);
    $this->assertDatabaseHas('overlay_controls', ['user_id' => $user->id, 'source' => 'gps', 'key' => 'session_distance', 'value' => '3']);
    $this->assertDatabaseHas('overlay_controls', ['user_id' => $user->id, 'source' => 'gps', 'key' => 'lat', 'value' => 'prev']);

    // Last good position is preserved for the next real ping.
    $integration->refresh();
    expect((float) $integration->settings['last_lat'])->toBe(52.3676);
});

test('null-island (0,0) fix is rejected', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $integration] = makeMobileIntegration('test-mobile-token', [
        'last_lat' => 52.3676,
        'last_lng' => 4.9041,
        'last_fix_at_unix' => time() - 5,
    ]);

    OverlayControl::provisionServiceControl($user, 'gps', [
        'key' => 'distance', 'type' => 'number', 'label' => 'Distance', 'value' => '0',
    ]);

    postMobile($integration->webhook_token, mobilePayload([
        'latitude' => 0,
        'longitude' => 0,
        'timestamp' => time(),
        'serial' => '2',
    ]))->assertStatus(200);

    $this->assertDatabaseHas('overlay_controls', ['user_id' => $user->id, 'source' => 'gps', 'key' => 'distance', 'value' => '0']);
    $integration->refresh();
    expect((float) $integration->settings['last_lat'])->toBe(52.3676);
});

test('teleport fix (impossible implied speed) is rejected', function () {
    Event::fake([ControlValueUpdated::class]);

    $ts = time();

    // Last good fix in Amsterdam, 10s ago.
    [$user, $integration] = makeMobileIntegration('test-mobile-token', [
        'last_lat' => 52.3676,
        'last_lng' => 4.9041,
        'last_fix_at_unix' => $ts - 10,
        'session_distance_km' => 1.5,
    ]);

    OverlayControl::provisionServiceControl($user, 'gps', [
        'key' => 'distance', 'type' => 'number', 'label' => 'Distance', 'value' => '1.5',
    ]);

    // ~5800 km away in 10 seconds -> millions of km/h. Valid coordinate, but a teleport.
    postMobile($integration->webhook_token, mobilePayload([
        'latitude' => 0.1,
        'longitude' => 0.1,
        'timestamp' => $ts,
        'serial' => '2',
    ]))->assertStatus(200);

    // Distance unchanged, last position NOT advanced to the bad point.
    $this->assertDatabaseHas('overlay_controls', ['user_id' => $user->id, 'source' => 'gps', 'key' => 'distance', 'value' => '1.5']);
    $integration->refresh();
    expect((float) $integration->settings['last_lat'])->toBe(52.3676);
});

// ──────────────────────────────────────────────────────────────────────────────
// Speed unit conversion
// ──────────────────────────────────────────────────────────────────────────────

test('speed_unit setting does not affect stored speed (always raw m/s)', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $integration] = makeMobileIntegration('test-mobile-token', [
        'speed_unit' => 'mph',
    ]);

    OverlayControl::provisionServiceControl($user, 'gps', [
        'key' => 'speed', 'type' => 'number', 'label' => 'Speed', 'value' => '0',
    ]);
    OverlayControl::provisionServiceControl($user, 'gps', [
        'key' => 'lat', 'type' => 'text', 'label' => 'Lat', 'value' => '',
    ]);
    OverlayControl::provisionServiceControl($user, 'gps', [
        'key' => 'lng', 'type' => 'text', 'label' => 'Lng', 'value' => '',
    ]);

    postMobile($integration->webhook_token, mobilePayload([
        'speed' => 13.89,
        'timestamp' => time(),
        'serial' => '1',
    ]))->assertStatus(200);

    $speedControl = OverlayControl::where('user_id', $user->id)
        ->where('source', 'gps')
        ->where('key', 'speed')
        ->first();

    // Always stored as raw m/s regardless of speed_unit; pipe formatters handle display.
    expect((float) $speedControl->value)->toBe(13.89);
});

// ──────────────────────────────────────────────────────────────────────────────
// Distance reset
// ──────────────────────────────────────────────────────────────────────────────

test('reset-session zeroes session distance/stats but leaves lifetime and position intact', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $integration] = makeMobileIntegration('test-mobile-token', [
        'last_lat' => 52.3676,
        'last_lng' => 4.9041,
        'last_fix_at_unix' => time(),
        'session_distance_km' => 5785.36,
        'session_max_speed_ms' => 30.0,
        'session_speed_sum_ms' => 120.0,
        'session_speed_count' => 4,
    ]);

    OverlayControl::provisionServiceControl($user, 'gps', [
        'key' => 'distance', 'type' => 'number', 'label' => 'distance', 'value' => '4321',
    ]);
    foreach (['session_distance', 'session_max_speed', 'session_avg_speed', 'session_duration'] as $key) {
        OverlayControl::provisionServiceControl($user, 'gps', [
            'key' => $key, 'type' => 'number', 'label' => $key, 'value' => '999',
        ]);
    }

    $this->actingAs($user)
        ->post('/settings/integrations/overlabels-mobile/reset-session')
        ->assertStatus(200)
        ->assertJson(['status' => 'ok']);

    foreach (['session_distance', 'session_max_speed', 'session_avg_speed', 'session_duration'] as $key) {
        $this->assertDatabaseHas('overlay_controls', [
            'user_id' => $user->id, 'source' => 'gps', 'key' => $key, 'value' => '0',
        ]);
    }

    // Lifetime odometer untouched.
    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id, 'source' => 'gps', 'key' => 'distance', 'value' => '4321',
    ]);

    $integration->refresh();
    expect($integration->settings)->not()->toHaveKey('session_distance_km');
    expect($integration->settings)->not()->toHaveKey('session_speed_count');
    // Position is preserved so the session keeps measuring from here.
    expect((float) $integration->settings['last_lat'])->toBe(52.3676);
});

test('reset-lifetime zeroes the cumulative distance only', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $integration] = makeMobileIntegration('test-mobile-token', [
        'last_lat' => 52.3676,
        'last_lng' => 4.9041,
        'session_distance_km' => 12.0,
    ]);

    OverlayControl::provisionServiceControl($user, 'gps', [
        'key' => 'distance', 'type' => 'number', 'label' => 'distance', 'value' => '8000',
    ]);
    OverlayControl::provisionServiceControl($user, 'gps', [
        'key' => 'session_distance', 'type' => 'number', 'label' => 'session_distance', 'value' => '12',
    ]);

    $this->actingAs($user)
        ->post('/settings/integrations/overlabels-mobile/reset-lifetime')
        ->assertStatus(200)
        ->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id, 'source' => 'gps', 'key' => 'distance', 'value' => '0',
    ]);
    // Session distance is independent and untouched.
    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id, 'source' => 'gps', 'key' => 'session_distance', 'value' => '12',
    ]);

    Event::assertDispatched(ControlValueUpdated::class);
});

test('session_start clears last position so first ping starts a clean baseline', function () {
    Event::fake([ControlValueUpdated::class]);

    // Previous session ended in Amsterdam.
    [$user, $integration] = makeMobileIntegration('test-mobile-token', [
        'last_lat' => 52.3676,
        'last_lng' => 4.9041,
        'last_fix_at_unix' => time() - 86400,
        'session_distance_km' => 12.0,
    ]);

    OverlayControl::provisionServiceControl($user, 'gps', [
        'key' => 'distance', 'type' => 'number', 'label' => 'Distance', 'value' => '12',
    ]);
    OverlayControl::provisionServiceControl($user, 'gps', [
        'key' => 'session_distance', 'type' => 'number', 'label' => 'Session Distance', 'value' => '12',
    ]);

    $sessionId = 'fresh-baseline-session';

    postMobile($integration->webhook_token, [
        'event' => 'session_start',
        'session_id' => $sessionId,
        'timestamp' => (string) time(),
    ])->assertStatus(200);

    // Baseline wiped: a new session must not difference against the old endpoint.
    $integration->refresh();
    expect($integration->settings)->not()->toHaveKey('last_lat');
    expect((float) $integration->settings['session_distance_km'])->toBe(0.0);

    // First real ping in a far-away city adds 0 (it sets the baseline), not a phantom jump.
    postMobile($integration->webhook_token, mobilePayload([
        'latitude' => 51.5072,
        'longitude' => -0.1276, // London
        'timestamp' => time(),
        'serial' => '1',
        'session_id' => $sessionId,
    ]))->assertStatus(200);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id, 'source' => 'gps', 'key' => 'session_distance', 'value' => '0',
    ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Connect / Disconnect
// ──────────────────────────────────────────────────────────────────────────────

test('connect creates integration with auto-generated token and provisions controls', function () {
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $this->actingAs($user)
        ->post('/settings/integrations/overlabels-mobile', [
            'speed_unit' => 'kmh',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('external_integrations', [
        'user_id' => $user->id,
        'service' => 'gps',
        'enabled' => true,
    ]);

    // Token should be auto-generated
    $integration = ExternalIntegration::where('user_id', $user->id)
        ->where('service', 'gps')
        ->first();
    $credentials = $integration->getCredentialsDecrypted();
    expect($credentials['token'])->toBeString()->toHaveLength(32);

    // All driver-defined controls should be auto-provisioned
    $controlCount = OverlayControl::where('user_id', $user->id)
        ->where('source', 'gps')
        ->where('source_managed', true)
        ->count();

    $expected = count((new GpsServiceDriver)->getAutoProvisionedControls());
    expect($controlCount)->toBe($expected);
});

test('disconnect removes integration and controls', function () {
    [$user, $integration] = makeMobileIntegration();

    OverlayControl::provisionServiceControl($user, 'gps', [
        'key' => 'speed', 'type' => 'number', 'label' => 'Speed', 'value' => '0',
    ]);

    $this->actingAs($user)
        ->delete('/settings/integrations/overlabels-mobile')
        ->assertRedirect();

    $this->assertDatabaseMissing('external_integrations', [
        'user_id' => $user->id,
        'service' => 'gps',
    ]);

    $controlCount = OverlayControl::where('user_id', $user->id)
        ->where('source', 'gps')
        ->count();

    expect($controlCount)->toBe(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Regenerate token
// ──────────────────────────────────────────────────────────────────────────────

test('regenerate token changes the stored token', function () {
    [$user, $integration] = makeMobileIntegration('original-token');

    $this->actingAs($user)
        ->post('/settings/integrations/overlabels-mobile/regenerate-token')
        ->assertRedirect();

    $integration->refresh();
    $credentials = $integration->getCredentialsDecrypted();
    expect($credentials['token'])->not()->toBe('original-token');
    expect($credentials['token'])->toBeString()->toHaveLength(32);
});

// ──────────────────────────────────────────────────────────────────────────────
// Webhook landing page (GET)
// ──────────────────────────────────────────────────────────────────────────────

test('GET webhook URL returns mobile landing page', function () {
    [, $integration] = makeMobileIntegration();

    $this->get("/api/webhooks/overlabels-mobile/{$integration->webhook_token}")
        ->assertStatus(200)
        ->assertSee('Overlabels GPS')
        ->assertSee('overlabels://gps-setup');
});

test('GET webhook URL returns 404 for unknown token', function () {
    $this->get('/api/webhooks/overlabels-mobile/00000000-0000-0000-0000-000000000000')
        ->assertStatus(404);
});

// ──────────────────────────────────────────────────────────────────────────────
// Session lifecycle
// ──────────────────────────────────────────────────────────────────────────────

test('session_start sets tracking to 1 and stores event', function () {
    Event::fake([ControlValuesBatchUpdated::class]);

    [$user, $integration] = makeMobileIntegration();

    OverlayControl::provisionServiceControl($user, 'gps', [
        'key' => 'tracking', 'type' => 'boolean', 'label' => 'Tracking', 'value' => '0',
    ]);

    $sessionId = 'abc12345-def6-7890-abcd-ef1234567890';

    postMobile($integration->webhook_token, [
        'event' => 'session_start',
        'session_id' => $sessionId,
        'timestamp' => (string) time(),
    ])->assertStatus(200)->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('external_events', [
        'user_id' => $user->id,
        'service' => 'gps',
        'event_type' => 'session_start',
        'message_id' => "session_start_{$sessionId}",
    ]);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'gps',
        'key' => 'tracking',
        'value' => '1',
    ]);

    Event::assertDispatched(ControlValuesBatchUpdated::class);
});

test('session_end sets tracking to 0 and stores event', function () {
    Event::fake([ControlValuesBatchUpdated::class]);

    [$user, $integration] = makeMobileIntegration();

    OverlayControl::provisionServiceControl($user, 'gps', [
        'key' => 'tracking', 'type' => 'boolean', 'label' => 'Tracking', 'value' => '1',
    ]);

    $sessionId = 'abc12345-def6-7890-abcd-ef1234567890';

    postMobile($integration->webhook_token, [
        'event' => 'session_end',
        'session_id' => $sessionId,
        'timestamp' => (string) time(),
    ])->assertStatus(200)->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('external_events', [
        'user_id' => $user->id,
        'service' => 'gps',
        'event_type' => 'session_end',
        'message_id' => "session_end_{$sessionId}",
    ]);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'gps',
        'key' => 'tracking',
        'value' => '0',
    ]);

    Event::assertDispatched(ControlValuesBatchUpdated::class);
});

test('session_start is deduplicated by session_id', function () {
    [, $integration] = makeMobileIntegration();

    $payload = [
        'event' => 'session_start',
        'session_id' => 'dedup-test-session',
        'timestamp' => (string) time(),
    ];

    postMobile($integration->webhook_token, $payload)->assertJson(['status' => 'ok']);
    postMobile($integration->webhook_token, $payload)->assertJson(['status' => 'duplicate']);
});

test('session events do not update GPS controls or accumulate distance', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $integration] = makeMobileIntegration('test-mobile-token', [
        'last_lat' => 52.3676,
        'last_lng' => 4.9041,
    ]);

    OverlayControl::provisionServiceControl($user, 'gps', [
        'key' => 'tracking', 'type' => 'boolean', 'label' => 'Tracking', 'value' => '0',
    ]);
    OverlayControl::provisionServiceControl($user, 'gps', [
        'key' => 'distance', 'type' => 'number', 'label' => 'Distance', 'value' => '5.0',
    ]);
    OverlayControl::provisionServiceControl($user, 'gps', [
        'key' => 'speed', 'type' => 'number', 'label' => 'Speed', 'value' => '30',
    ]);

    postMobile($integration->webhook_token, [
        'event' => 'session_start',
        'session_id' => 'no-gps-update-test',
        'timestamp' => (string) time(),
    ])->assertStatus(200);

    // Distance and speed should NOT change
    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'gps',
        'key' => 'distance',
        'value' => '5.0',
    ]);
    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'gps',
        'key' => 'speed',
        'value' => '30',
    ]);
});

test('location_update with session_id stores it in payload', function () {
    [$user, $integration] = makeMobileIntegration();

    $sessionId = 'ride-session-uuid';
    $ts = time();

    postMobile($integration->webhook_token, array_merge(mobilePayload([
        'timestamp' => $ts,
        'serial' => '1',
    ]), ['session_id' => $sessionId]))
        ->assertStatus(200);

    $event = ExternalEvent::where('user_id', $user->id)
        ->where('message_id', "gps_{$ts}_1")
        ->first();

    expect($event->raw_payload['session_id'])->toBe($sessionId);
});
