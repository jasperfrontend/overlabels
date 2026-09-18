<?php

use App\Models\ExternalEvent;
use App\Models\ExternalIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Testing\TestResponse;

uses(DatabaseTransactions::class);

// external_events.raw_payload is plain jsonb and is deliberately readable, so
// what a driver hands to it is what we hold in the clear for 90 days. Verified
// against production on 2026-09-18: Ko-fi rows carried the donor's email and
// our own verification_token, every one of them. Fourthwall's donation payload
// carries data.email, which the repo's own fixtures already showed.
//
// These tests assert the stored row, not the driver return value, because the
// row is the thing that has to be clean.

function piiKofiIntegration(string $verificationToken = 'kofi-secret'): array
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'enabled' => true,
        'credentials' => Crypt::encryptString(json_encode(['verification_token' => $verificationToken])),
    ]);

    return [$user, $integration];
}

function piiPostKofi(string $webhookToken, array $payload): TestResponse
{
    return test()->post("/api/webhooks/kofi/{$webhookToken}", ['data' => json_encode($payload)]);
}

function piiFourthwallIntegration(string $appHmac = 'fw-secret'): array
{
    config(['services.fourthwall.hmac' => $appHmac]);

    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'fourthwall',
        'enabled' => true,
        'credentials' => Crypt::encryptString(json_encode(['access_token' => 'tok'])),
    ]);

    return [$user, $integration];
}

function piiPostFourthwall(string $webhookToken, array $payload, string $secret = 'fw-secret'): TestResponse
{
    $body = json_encode($payload);

    return test()->call(
        'POST',
        "/api/webhooks/fourthwall/{$webhookToken}",
        [], [], [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_FOURTHWALL_HMAC_APPS_SHA256' => base64_encode(hash_hmac('sha256', $body, $secret, true)),
        ],
        $body,
    );
}

it('stores no donor email, address or discord identity from a Ko-fi commission', function () {
    [$user, $integration] = piiKofiIntegration();

    piiPostKofi($integration->webhook_token, [
        'verification_token' => 'kofi-secret',
        'kofi_transaction_id' => 'txn-commission-1',
        'message_id' => (string) fake()->uuid(),
        'from_name' => 'Alice',
        'message' => 'Please draw my cat',
        'amount' => '50.00',
        'currency' => 'EUR',
        'type' => 'Commission',
        'email' => 'alice@example.com',
        'discord_username' => 'alice#0001',
        'discord_userid' => '99887766',
        'shipping' => [
            'full_name' => 'Alice Example',
            'street_address' => '1 Example Road',
            'city' => 'Amsterdam',
            'postal_code' => '1011AA',
            'country' => 'Netherlands',
            'telephone' => '+3100000000',
        ],
        'shop_items' => [['direct_link_code' => 'abc123']],
    ])->assertOk();

    $raw = ExternalEvent::where('user_id', $user->id)->sole()->raw_payload;
    $flat = json_encode($raw);

    expect($raw)->not->toHaveKey('email')
        ->and($raw)->not->toHaveKey('shipping')
        ->and($raw)->not->toHaveKey('discord_username')
        ->and($raw)->not->toHaveKey('discord_userid');

    // Nothing sensitive survives anywhere in the structure.
    expect($flat)->not->toContain('alice@example.com')
        ->and($flat)->not->toContain('Example Road')
        ->and($flat)->not->toContain('1011AA')
        ->and($flat)->not->toContain('+3100000000');

    // The parts an overlay actually renders are untouched.
    expect($raw['from_name'])->toBe('Alice')
        ->and($raw['message'])->toBe('Please draw my cat')
        ->and($raw['amount'])->toBe('50.00');
});

it('stores no Ko-fi verification token, so a credential never lands in the event log', function () {
    [$user, $integration] = piiKofiIntegration('kofi-secret');

    piiPostKofi($integration->webhook_token, [
        'verification_token' => 'kofi-secret',
        'kofi_transaction_id' => 'txn-token-1',
        'message_id' => (string) fake()->uuid(),
        'from_name' => 'Bob',
        'message' => 'hi',
        'amount' => '3.00',
        'currency' => 'USD',
        'type' => 'Donation',
    ])->assertOk();

    $event = ExternalEvent::where('user_id', $user->id)->sole();

    expect($event->raw_payload)->not->toHaveKey('verification_token');
    expect(json_encode($event->raw_payload))->not->toContain('kofi-secret');
});

it('stores no supporter email from a Fourthwall donation', function () {
    [$user, $integration] = piiFourthwallIntegration();

    piiPostFourthwall($integration->webhook_token, [
        'id' => (string) fake()->uuid(),
        'webhookId' => (string) fake()->uuid(),
        'shopId' => 'sh_test',
        'type' => 'DONATION',
        'apiVersion' => 'V1',
        'createdAt' => '2026-09-18T09:05:36.939+00:00',
        'testMode' => false,
        'data' => [
            'id' => 'don_scrub_1',
            'shopId' => 'sh_test',
            'status' => 'OPEN',
            'email' => 'supporter@fourthwall.com',
            'amounts' => ['total' => ['value' => 10, 'currency' => 'USD']],
            'username' => 'Johnny123',
            'message' => 'Sample message',
        ],
    ])->assertOk();

    $raw = ExternalEvent::where('user_id', $user->id)->sole()->raw_payload;

    expect($raw['data'])->not->toHaveKey('email');
    expect(json_encode($raw))->not->toContain('supporter@fourthwall.com');

    expect($raw['data']['username'])->toBe('Johnny123')
        ->and($raw['data']['message'])->toBe('Sample message');
});
