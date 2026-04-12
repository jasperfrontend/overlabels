<?php

use App\Models\ExternalIntegration;
use App\Services\External\Drivers\StreamElementsServiceDriver;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->driver = new StreamElementsServiceDriver;
});

// ──────────────────────────────────────────────────────────────────────────────
// getServiceKey
// ──────────────────────────────────────────────────────────────────────────────

test('getServiceKey returns streamelements', function () {
    expect($this->driver->getServiceKey())->toBe('streamelements');
});

// ──────────────────────────────────────────────────────────────────────────────
// parseEventType
// ──────────────────────────────────────────────────────────────────────────────

test('parseEventType maps tip to donation', function () {
    expect($this->driver->parseEventType(['type' => 'tip']))->toBe('donation');
});

test('parseEventType returns null for unknown type', function () {
    expect($this->driver->parseEventType(['type' => 'follow']))->toBeNull();
    expect($this->driver->parseEventType(['type' => 'subscriber']))->toBeNull();
    expect($this->driver->parseEventType([]))->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// normalizeEvent
// ──────────────────────────────────────────────────────────────────────────────

test('normalizeEvent correctly maps StreamElements tip payload', function () {
    $payload = [
        '_id' => '5f8d0d55b54764421b7156c4',
        'channel' => '5e4b3a55b54764421b7156c4',
        'type' => 'tip',
        'provider' => 'paypal',
        'flagged' => false,
        'data' => [
            'username' => 'test_tipper',
            'displayName' => 'TestTipper',
            'amount' => 4.20,
            'message' => 'Great stream!',
            'currency' => 'USD',
            'tipId' => 'tip_abc123',
        ],
    ];

    $event = $this->driver->normalizeEvent($payload, 'donation');

    expect($event->getService())->toBe('streamelements');
    expect($event->getEventType())->toBe('donation');
    expect($event->getMessageId())->toBe('5f8d0d55b54764421b7156c4');
    expect($event->getFromName())->toBe('TestTipper');
    expect($event->getMessage())->toBe('Great stream!');
    expect($event->getAmount())->toBe('4.2');
    expect($event->getCurrency())->toBe('USD');

    $tags = $event->getTemplateTags();
    expect($tags['event.from_name'])->toBe('TestTipper');
    expect($tags['event.amount'])->toBe('4.2');
    expect($tags['event.currency'])->toBe('USD');
    expect($tags['event.source'])->toBe('StreamElements');
    expect($tags['event.transaction_id'])->toBe('5f8d0d55b54764421b7156c4');
});

test('normalizeEvent falls back to tipId when _id missing', function () {
    $payload = [
        'type' => 'tip',
        'data' => [
            'username' => 'someone',
            'amount' => '5.00',
            'currency' => 'EUR',
            'tipId' => 'fallback_tip_id',
        ],
    ];

    $event = $this->driver->normalizeEvent($payload, 'donation');
    expect($event->getMessageId())->toBe('fallback_tip_id');
});

test('normalizeEvent generates id when no identifiers present', function () {
    $payload = ['type' => 'tip', 'data' => ['username' => 'Anon']];
    $event = $this->driver->normalizeEvent($payload, 'donation');

    expect($event->getMessageId())->toBeString()
        ->not()->toBeEmpty()
        ->toStartWith('se_');
});

test('normalizeEvent falls back to username when displayName missing', function () {
    $payload = [
        '_id' => 'id_123',
        'type' => 'tip',
        'data' => [
            'username' => 'tipper_user',
            'amount' => '3.00',
            'currency' => 'USD',
        ],
    ];

    $event = $this->driver->normalizeEvent($payload, 'donation');
    expect($event->getFromName())->toBe('tipper_user');
});

test('normalizeEvent handles empty data array', function () {
    $payload = ['_id' => 'x', 'type' => 'tip', 'data' => []];
    $event = $this->driver->normalizeEvent($payload, 'donation');

    expect($event->getFromName())->toBeNull();
    expect($event->getAmount())->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// getAutoProvisionedControls
// ──────────────────────────────────────────────────────────────────────────────

test('getAutoProvisionedControls returns expected control keys', function () {
    $controls = $this->driver->getAutoProvisionedControls();
    $keys = array_column($controls, 'key');

    expect($controls)->toHaveCount(6);
    expect($keys)->toContain('donations_received');
    expect($keys)->toContain('latest_donor_name');
    expect($keys)->toContain('latest_donation_amount');
    expect($keys)->toContain('latest_donation_message');
    expect($keys)->toContain('latest_donation_currency');
    expect($keys)->toContain('total_received');
});

// ──────────────────────────────────────────────────────────────────────────────
// verifyRequest
// ──────────────────────────────────────────────────────────────────────────────

test('verifyRequest returns false when no listener_secret stored', function () {
    $integration = new ExternalIntegration(['credentials' => null]);
    $request = Request::create('/', 'POST', [], [], [], ['HTTP_X_LISTENER_SECRET' => 'anything']);

    expect($this->driver->verifyRequest($request, $integration))->toBeFalse();
});

test('verifyRequest returns false when secret does not match', function () {
    $integration = new ExternalIntegration;
    $integration->setCredentialsEncrypted(['listener_secret' => 'correct-secret']);

    $request = Request::create('/', 'POST', [], [], [], ['HTTP_X_LISTENER_SECRET' => 'wrong-secret']);

    expect($this->driver->verifyRequest($request, $integration))->toBeFalse();
});

test('verifyRequest returns true when secret matches', function () {
    $integration = new ExternalIntegration;
    $integration->setCredentialsEncrypted(['listener_secret' => 'my-secret-123']);

    $request = Request::create('/', 'POST', [], [], [], ['HTTP_X_LISTENER_SECRET' => 'my-secret-123']);

    expect($this->driver->verifyRequest($request, $integration))->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// getControlUpdates
// ──────────────────────────────────────────────────────────────────────────────

test('getControlUpdates increments donations_received for donation', function () {
    $payload = [
        '_id' => 'evt_001',
        'type' => 'tip',
        'data' => [
            'displayName' => 'Bob',
            'amount' => '10.00',
            'currency' => 'USD',
            'message' => 'Nice work!',
        ],
    ];
    $event = $this->driver->normalizeEvent($payload, 'donation');
    $updates = $this->driver->getControlUpdates($event);

    expect($updates['donations_received'])->toBe(['action' => 'increment']);
    expect($updates['latest_donor_name'])->toBe('Bob');
    expect($updates['latest_donation_amount'])->toBe('10.00');
    expect($updates['latest_donation_message'])->toBe('Nice work!');
    expect($updates['latest_donation_currency'])->toBe('USD');
    expect($updates['total_received'])->toBe(['action' => 'add', 'amount' => 10.0]);
});

test('getControlUpdates returns empty for non-donation events', function () {
    $payload = [
        '_id' => 'x',
        'type' => 'follow',
        'data' => ['displayName' => 'Carol'],
    ];
    $event = $this->driver->normalizeEvent($payload, 'follow');
    $updates = $this->driver->getControlUpdates($event);

    expect($updates)->toBeEmpty();
});
