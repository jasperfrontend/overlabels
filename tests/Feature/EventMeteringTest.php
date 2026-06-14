<?php

use App\Models\ExternalIntegration;
use App\Models\User;
use App\Services\EventMeter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Testing\TestResponse;

uses(DatabaseTransactions::class);

function meteringGpsIntegration(bool $testMode = false): array
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'gps',
        'enabled' => true,
        'test_mode' => $testMode,
        'credentials' => Crypt::encryptString(json_encode(['token' => 'meter-token'])),
        'settings' => [],
    ]);

    return [$user, $integration];
}

function postMeteredPing(string $webhookToken, array $payload): TestResponse
{
    return test()->postJson(
        "/api/webhooks/overlabels-mobile/{$webhookToken}",
        $payload,
        ['X-GPSLogger-Token' => 'meter-token'],
    );
}

test('a GPS ping meters exactly one inbound event for the owning user', function () {
    [$user, $integration] = meteringGpsIntegration();

    $this->mock(EventMeter::class)
        ->shouldReceive('record')->once()->with($user->id);

    postMeteredPing($integration->webhook_token, [
        'latitude' => 52.1, 'longitude' => 4.9, 'timestamp' => time(), 'serial' => '1',
    ])->assertStatus(200);
});

test('a duplicate ping is not metered twice', function () {
    [, $integration] = meteringGpsIntegration();

    $this->mock(EventMeter::class)
        ->shouldReceive('record')->once();

    $payload = ['latitude' => 52.1, 'longitude' => 4.9, 'timestamp' => 1234567890, 'serial' => '7'];

    postMeteredPing($integration->webhook_token, $payload)->assertJson(['status' => 'ok']);
    postMeteredPing($integration->webhook_token, $payload)->assertJson(['status' => 'duplicate']);
});

test('settings_sync is not metered', function () {
    [, $integration] = meteringGpsIntegration();

    $this->mock(EventMeter::class)
        ->shouldReceive('record')->never();

    postMeteredPing($integration->webhook_token, [
        'event' => 'settings_sync', 'timestamp' => (string) time(),
    ])->assertStatus(200);
});

test('test-mode traffic is not metered', function () {
    [, $integration] = meteringGpsIntegration(testMode: true);

    $this->mock(EventMeter::class)
        ->shouldReceive('record')->never();

    postMeteredPing($integration->webhook_token, [
        'latitude' => 52.1, 'longitude' => 4.9, 'timestamp' => time(), 'serial' => '1',
    ])->assertStatus(200);
});
