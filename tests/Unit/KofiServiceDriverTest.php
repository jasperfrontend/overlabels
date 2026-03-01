<?php

use App\Models\ExternalIntegration;
use App\Services\External\Drivers\KofiServiceDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

beforeEach(function () {
    $this->driver = new KofiServiceDriver;
});

// ──────────────────────────────────────────────────────────────────────────────
// getServiceKey
// ──────────────────────────────────────────────────────────────────────────────

test('getServiceKey returns kofi', function () {
    expect($this->driver->getServiceKey())->toBe('kofi');
});

// ──────────────────────────────────────────────────────────────────────────────
// parseEventType
// ──────────────────────────────────────────────────────────────────────────────

test('parseEventType maps Donation to donation', function () {
    expect($this->driver->parseEventType(['type' => 'Donation']))->toBe('donation');
});

test('parseEventType maps Subscription to subscription', function () {
    expect($this->driver->parseEventType(['type' => 'Subscription']))->toBe('subscription');
});

test('parseEventType maps Shop Order to shop_order', function () {
    expect($this->driver->parseEventType(['type' => 'Shop Order']))->toBe('shop_order');
});

test('parseEventType maps Commission to commission', function () {
    expect($this->driver->parseEventType(['type' => 'Commission']))->toBe('commission');
});

test('parseEventType returns null for unknown type', function () {
    expect($this->driver->parseEventType(['type' => 'Unknown']))->toBeNull();
    expect($this->driver->parseEventType([]))->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// normalizeEvent
// ──────────────────────────────────────────────────────────────────────────────

test('normalizeEvent correctly maps donation payload', function () {
    $payload = [
        'verification_token' => 'abc123',
        'kofi_transaction_id' => 'txn-001',
        'from_name' => 'Alice',
        'message' => 'Keep it up!',
        'amount' => '5.00',
        'currency' => 'USD',
        'type' => 'Donation',
        'is_subscription_payment' => false,
        'is_first_subscription_payment' => false,
        'url' => 'https://ko-fi.com',
        'tier_name' => null,
    ];

    $event = $this->driver->normalizeEvent($payload, 'donation');

    expect($event->getService())->toBe('kofi');
    expect($event->getEventType())->toBe('donation');
    expect($event->getMessageId())->toBe('txn-001');
    expect($event->getFromName())->toBe('Alice');
    expect($event->getMessage())->toBe('Keep it up!');
    expect($event->getAmount())->toBe('5.00');
    expect($event->getCurrency())->toBe('USD');

    $tags = $event->getTemplateTags();
    expect($tags['event.from_name'])->toBe('Alice');
    expect($tags['event.amount'])->toBe('5.00');
    expect($tags['event.currency'])->toBe('USD');
    expect($tags['event.is_subscription'])->toBe('0');
    expect($tags['event.is_first_sub'])->toBe('0');
    expect($tags['event.is_shop_order'])->toBe('0');
    expect($tags['event.transaction_id'])->toBe('txn-001');
});

test('normalizeEvent falls back to uuid when kofi_transaction_id missing', function () {
    $payload = ['type' => 'Donation'];
    $event = $this->driver->normalizeEvent($payload, 'donation');

    expect($event->getMessageId())->toBeString()->not()->toBeEmpty();
});

// ──────────────────────────────────────────────────────────────────────────────
// getAutoProvisionedControls
// ──────────────────────────────────────────────────────────────────────────────

test('getAutoProvisionedControls returns expected control keys', function () {
    $controls = $this->driver->getAutoProvisionedControls();
    $keys = array_column($controls, 'key');

    expect($keys)->toContain('kofis_received');
    expect($keys)->toContain('latest_donor_name');
    expect($keys)->toContain('latest_donation_amount');
    expect($keys)->toContain('latest_donation_message');
    expect($keys)->toContain('latest_donation_currency');
    expect($keys)->toContain('total_received');
});

// ──────────────────────────────────────────────────────────────────────────────
// verifyRequest
// ──────────────────────────────────────────────────────────────────────────────

test('verifyRequest returns false when no token stored', function () {
    $integration = new ExternalIntegration(['credentials' => null]);
    $request = Request::create('/', 'POST', ['data' => json_encode(['verification_token' => 'anything'])]);

    expect($this->driver->verifyRequest($request, $integration))->toBeFalse();
});

test('verifyRequest returns false when token does not match', function () {
    $integration = new ExternalIntegration;
    $integration->setCredentialsEncrypted(['verification_token' => 'correct-token']);

    $request = Request::create('/', 'POST', ['data' => json_encode(['verification_token' => 'wrong-token'])]);

    expect($this->driver->verifyRequest($request, $integration))->toBeFalse();
});

test('verifyRequest returns true when token matches', function () {
    $integration = new ExternalIntegration;
    $integration->setCredentialsEncrypted(['verification_token' => 'secret123']);

    $request = Request::create('/', 'POST', ['data' => json_encode(['verification_token' => 'secret123'])]);

    expect($this->driver->verifyRequest($request, $integration))->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// getControlUpdates
// ──────────────────────────────────────────────────────────────────────────────

test('getControlUpdates increments kofis_received for donation', function () {
    $payload = [
        'kofi_transaction_id' => 'txn-001',
        'from_name' => 'Bob',
        'message' => 'Nice work!',
        'amount' => '10.00',
        'currency' => 'USD',
        'is_subscription_payment' => false,
        'is_first_subscription_payment' => false,
    ];
    $event = $this->driver->normalizeEvent($payload, 'donation');
    $updates = $this->driver->getControlUpdates($event);

    expect($updates['kofis_received'])->toBe(['action' => 'increment']);
    expect($updates['latest_donor_name'])->toBe('Bob');
    expect($updates['latest_donation_amount'])->toBe('10.00');
    expect($updates['total_received'])->toBe(['action' => 'add', 'amount' => 10.0]);
});

test('getControlUpdates returns empty for commission events', function () {
    $payload = ['kofi_transaction_id' => 'txn-c', 'from_name' => 'Carol'];
    $event = $this->driver->normalizeEvent($payload, 'commission');
    $updates = $this->driver->getControlUpdates($event);

    expect($updates)->toBeEmpty();
});
