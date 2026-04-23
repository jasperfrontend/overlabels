<?php

use App\Models\ExternalIntegration;
use App\Services\External\Drivers\FourthwallServiceDriver;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->driver = new FourthwallServiceDriver;
});

// Sample DONATION payload from https://docs.fourthwall.com/api-reference/order-events/donation
function fourthwallDonationPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => '00aa4abd-5778-4199-8161-0b49b2f212e5',
        'webhookId' => '00aa4abd-5778-4199-8161-0b49b2f212e5',
        'shopId' => 'sh_c689d374-22ca-43d3-8d29-9ef0805cc4cb',
        'type' => 'DONATION',
        'apiVersion' => 'V1',
        'createdAt' => '2020-08-13T09:05:36.939+00:00',
        'testMode' => false,
        'data' => [
            'id' => 'don_Kpcjx4HIQ1e4bTIOjX9CsA',
            'shopId' => 'sh_c689d374-22ca-43d3-8d29-9ef0805cc4cb',
            'status' => 'OPEN',
            'email' => 'supporter@fourthwall.com',
            'amounts' => [
                'total' => ['value' => 10, 'currency' => 'USD'],
            ],
            'createdAt' => '2020-08-13T09:05:36.939Z',
            'updatedAt' => '2020-08-13T09:05:36.939Z',
            'username' => 'Johnny123',
            'message' => 'Sample message',
        ],
    ], $overrides);
}

// ──────────────────────────────────────────────────────────────────────────────
// getServiceKey
// ──────────────────────────────────────────────────────────────────────────────

test('getServiceKey returns fourthwall', function () {
    expect($this->driver->getServiceKey())->toBe('fourthwall');
});

// ──────────────────────────────────────────────────────────────────────────────
// parseEventType
// ──────────────────────────────────────────────────────────────────────────────

test('parseEventType maps DONATION to donation', function () {
    expect($this->driver->parseEventType(['type' => 'DONATION']))->toBe('donation');
});

test('parseEventType returns null for unsupported event types', function () {
    expect($this->driver->parseEventType(['type' => 'ORDER_PLACED']))->toBeNull();
    expect($this->driver->parseEventType(['type' => 'GIFT_PURCHASE']))->toBeNull();
    expect($this->driver->parseEventType(['type' => 'SUBSCRIPTION_PURCHASED']))->toBeNull();
    expect($this->driver->parseEventType([]))->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// normalizeEvent
// ──────────────────────────────────────────────────────────────────────────────

test('normalizeEvent correctly maps DONATION payload fields', function () {
    $event = $this->driver->normalizeEvent(fourthwallDonationPayload(), 'donation');

    expect($event->getService())->toBe('fourthwall');
    expect($event->getEventType())->toBe('donation');
    expect($event->getMessageId())->toBe('don_Kpcjx4HIQ1e4bTIOjX9CsA');
    expect($event->getFromName())->toBe('Johnny123');
    expect($event->getMessage())->toBe('Sample message');
    expect($event->getAmount())->toBe('10');
    expect($event->getCurrency())->toBe('USD');

    $tags = $event->getTemplateTags();
    expect($tags['event.from_name'])->toBe('Johnny123');
    expect($tags['event.amount'])->toBe('10');
    expect($tags['event.currency'])->toBe('USD');
    expect($tags['event.type'])->toBe('donation');
    expect($tags['event.source'])->toBe('Fourthwall');
    expect($tags['event.status'])->toBe('OPEN');
    expect($tags['event.transaction_id'])->toBe('don_Kpcjx4HIQ1e4bTIOjX9CsA');
});

test('normalizeEvent uses data.id as message_id, not envelope id', function () {
    // Dedup must be on the business entity - retries of the same donation
    // should collide on the same message_id.
    $payload = fourthwallDonationPayload([
        'id' => 'envelope-id-changes-on-retry',
        'data' => ['id' => 'don_stable_id'],
    ]);

    $event = $this->driver->normalizeEvent($payload, 'donation');

    expect($event->getMessageId())->toBe('don_stable_id');
});

test('normalizeEvent falls back to uuid when data.id missing', function () {
    $payload = fourthwallDonationPayload();
    unset($payload['data']['id']);

    $event = $this->driver->normalizeEvent($payload, 'donation');

    expect($event->getMessageId())->toBeString()->not()->toBeEmpty();
});

test('normalizeEvent handles missing optional fields', function () {
    $payload = [
        'type' => 'DONATION',
        'data' => ['id' => 'don_minimal'],
    ];

    $event = $this->driver->normalizeEvent($payload, 'donation');

    expect($event->getFromName())->toBeNull();
    expect($event->getMessage())->toBeNull();
    expect($event->getAmount())->toBeNull();
    expect($event->getCurrency())->toBeNull();

    $tags = $event->getTemplateTags();
    expect($tags['event.amount'])->toBe('');
    expect($tags['event.currency'])->toBe('');
});

// ──────────────────────────────────────────────────────────────────────────────
// getSupportedEventTypes / getAutoProvisionedControls
// ──────────────────────────────────────────────────────────────────────────────

test('getSupportedEventTypes returns only donation in phase 1', function () {
    expect($this->driver->getSupportedEventTypes())->toBe(['donation']);
});

test('getAutoProvisionedControls returns donation-family keys', function () {
    $keys = array_column($this->driver->getAutoProvisionedControls(), 'key');

    expect($keys)->toBe([
        'donations_received',
        'latest_donor_name',
        'latest_donation_amount',
        'latest_donation_message',
        'latest_donation_currency',
        'total_received',
    ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// verifyRequest - HMAC-SHA256 base64 in X-Fourthwall-Hmac-SHA256
// ──────────────────────────────────────────────────────────────────────────────

test('verifyRequest returns false when no webhook_secret stored', function () {
    $integration = new ExternalIntegration;
    $integration->setCredentialsEncrypted(['access_token' => 'tok']);

    $request = Request::create('/', 'POST', content: '{"foo":"bar"}');
    $request->headers->set('X-Fourthwall-Hmac-SHA256', 'anything');

    expect($this->driver->verifyRequest($request, $integration))->toBeFalse();
});

test('verifyRequest returns false when header is missing', function () {
    $integration = new ExternalIntegration;
    $integration->setCredentialsEncrypted(['webhook_secret' => 'shhh']);

    $request = Request::create('/', 'POST', content: '{"foo":"bar"}');

    expect($this->driver->verifyRequest($request, $integration))->toBeFalse();
});

test('verifyRequest returns true when signature matches raw body', function () {
    $secret = 'fw-webhook-secret';
    $body = '{"type":"DONATION","data":{"id":"don_1"}}';
    $signature = base64_encode(hash_hmac('sha256', $body, $secret, true));

    $integration = new ExternalIntegration;
    $integration->setCredentialsEncrypted(['webhook_secret' => $secret]);

    $request = Request::create('/', 'POST', content: $body);
    $request->headers->set('X-Fourthwall-Hmac-SHA256', $signature);

    expect($this->driver->verifyRequest($request, $integration))->toBeTrue();
});

test('verifyRequest returns false when body is tampered', function () {
    $secret = 'fw-webhook-secret';
    $original = '{"type":"DONATION","data":{"id":"don_1"}}';
    $tampered = '{"type":"DONATION","data":{"id":"don_2"}}';
    $signature = base64_encode(hash_hmac('sha256', $original, $secret, true));

    $integration = new ExternalIntegration;
    $integration->setCredentialsEncrypted(['webhook_secret' => $secret]);

    $request = Request::create('/', 'POST', content: $tampered);
    $request->headers->set('X-Fourthwall-Hmac-SHA256', $signature);

    expect($this->driver->verifyRequest($request, $integration))->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────────
// getControlUpdates
// ──────────────────────────────────────────────────────────────────────────────

test('getControlUpdates increments and sets latest_* fields on donation', function () {
    $event = $this->driver->normalizeEvent(fourthwallDonationPayload(), 'donation');
    $updates = $this->driver->getControlUpdates($event);

    expect($updates['donations_received'])->toBe(['action' => 'increment']);
    expect($updates['latest_donor_name'])->toBe('Johnny123');
    expect($updates['latest_donation_message'])->toBe('Sample message');
    expect($updates['latest_donation_amount'])->toBe('10');
    expect($updates['latest_donation_currency'])->toBe('USD');
    expect($updates['total_received'])->toBe(['action' => 'add', 'amount' => 10.0]);
});

test('getControlUpdates returns empty for unsupported event types', function () {
    $event = $this->driver->normalizeEvent(fourthwallDonationPayload(), 'order');

    expect($this->driver->getControlUpdates($event))->toBe([]);
});
