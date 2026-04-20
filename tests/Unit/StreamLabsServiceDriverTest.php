<?php

use App\Models\ExternalIntegration;
use App\Services\External\Drivers\StreamLabsServiceDriver;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->driver = new StreamLabsServiceDriver;
});

// ──────────────────────────────────────────────────────────────────────────────
// getServiceKey
// ──────────────────────────────────────────────────────────────────────────────

test('getServiceKey returns streamlabs', function () {
    expect($this->driver->getServiceKey())->toBe('streamlabs');
});

// ──────────────────────────────────────────────────────────────────────────────
// parseEventType
// ──────────────────────────────────────────────────────────────────────────────

test('parseEventType maps donation to donation', function () {
    expect($this->driver->parseEventType(['type' => 'donation']))->toBe('donation');
});

test('parseEventType returns null for unknown type', function () {
    expect($this->driver->parseEventType(['type' => 'follow']))->toBeNull();
    expect($this->driver->parseEventType(['type' => 'subscription']))->toBeNull();
    expect($this->driver->parseEventType([]))->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// normalizeEvent
// ──────────────────────────────────────────────────────────────────────────────

test('normalizeEvent correctly maps StreamLabs donation payload', function () {
    $payload = [
        'type' => 'donation',
        'event_id' => 'evt_abc123',
        'message' => [
            [
                'id' => 96164121,
                'name' => 'TestDonor',
                'amount' => '13.37',
                'formatted_amount' => '$13.37',
                'formattedAmount' => '$13.37',
                'message' => 'Great stream!',
                'currency' => 'USD',
                'from' => 'TestDonor',
                '_id' => '0820c9d5bafd768c9843f5e35c885e71',
            ],
        ],
    ];

    $event = $this->driver->normalizeEvent($payload, 'donation');

    expect($event->getService())->toBe('streamlabs');
    expect($event->getEventType())->toBe('donation');
    expect($event->getMessageId())->toBe('evt_abc123');
    expect($event->getFromName())->toBe('TestDonor');
    expect($event->getMessage())->toBe('Great stream!');
    expect($event->getAmount())->toBe('13.37');
    expect($event->getCurrency())->toBe('USD');

    $tags = $event->getTemplateTags();
    expect($tags['event.from_name'])->toBe('TestDonor');
    expect($tags['event.amount'])->toBe('13.37');
    expect($tags['event.currency'])->toBe('USD');
    expect($tags['event.formatted_amount'])->toBe('$13.37');
    expect($tags['event.source'])->toBe('StreamLabs');
    expect($tags['event.transaction_id'])->toBe('evt_abc123');
});

test('normalizeEvent falls back to _id when event_id missing', function () {
    $payload = [
        'type' => 'donation',
        'message' => [
            [
                'id' => 123,
                '_id' => 'fallback_id_abc',
                'from' => 'Someone',
                'amount' => '5.00',
                'currency' => 'EUR',
            ],
        ],
    ];

    $event = $this->driver->normalizeEvent($payload, 'donation');
    expect($event->getMessageId())->toBe('fallback_id_abc');
});

test('normalizeEvent generates id when no identifiers present', function () {
    $payload = ['type' => 'donation', 'message' => [['from' => 'Anon']]];
    $event = $this->driver->normalizeEvent($payload, 'donation');

    expect($event->getMessageId())->toBeString()->not()->toBeEmpty();
});

test('normalizeEvent handles empty message array', function () {
    $payload = ['type' => 'donation', 'message' => []];
    $event = $this->driver->normalizeEvent($payload, 'donation');

    expect($event->getFromName())->toBeNull();
    expect($event->getAmount())->toBeNull();
});

test('normalizeEvent decodes HTML entities from message and donor name', function () {
    $payload = [
        'type' => 'donation',
        'event_id' => 'evt_html',
        'message' => [[
            'id' => 1,
            'name' => 'Floris &amp; Co',
            'from' => 'Floris &amp; Co',
            'amount' => '7.50',
            'currency' => 'EUR',
            'message' => 'i haven&#39;t been here &lt;3',
        ]],
    ];

    $event = $this->driver->normalizeEvent($payload, 'donation');

    expect($event->getMessage())->toBe("i haven't been here <3");
    expect($event->getFromName())->toBe('Floris & Co');
    expect($event->getTemplateTags()['event.message'])->toBe("i haven't been here <3");
    expect($event->getTemplateTags()['event.from_name'])->toBe('Floris & Co');
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
        'type' => 'donation',
        'event_id' => 'evt_001',
        'message' => [
            [
                'from' => 'Bob',
                'amount' => '10.00',
                'currency' => 'USD',
                'message' => 'Nice work!',
            ],
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
        'type' => 'follow',
        'message' => [['name' => 'Carol']],
    ];
    // Force a non-donation event type
    $event = $this->driver->normalizeEvent($payload, 'follow');
    $updates = $this->driver->getControlUpdates($event);

    expect($updates)->toBeEmpty();
});
