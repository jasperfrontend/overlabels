<?php

use App\Models\ExternalIntegration;
use App\Services\External\Drivers\BMACServiceDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

beforeEach(function () {
    $this->driver = new BMACServiceDriver;
});

// ──────────────────────────────────────────────────────────────────────────────
// getServiceKey / getSupportedEventTypes
// ──────────────────────────────────────────────────────────────────────────────

test('getServiceKey returns bmac', function () {
    expect($this->driver->getServiceKey())->toBe('bmac');
});

test('getSupportedEventTypes lists all six positive event types', function () {
    expect($this->driver->getSupportedEventTypes())
        ->toBe(['donation', 'commission', 'extra', 'membership', 'recurring', 'wishlist']);
});

// ──────────────────────────────────────────────────────────────────────────────
// parseEventType
// ──────────────────────────────────────────────────────────────────────────────

test('parseEventType maps the six positive BMAC events', function () {
    expect($this->driver->parseEventType(['type' => 'donation.created']))->toBe('donation');
    expect($this->driver->parseEventType(['type' => 'commission_order.created']))->toBe('commission');
    expect($this->driver->parseEventType(['type' => 'extra_purchase.created']))->toBe('extra');
    expect($this->driver->parseEventType(['type' => 'membership.started']))->toBe('membership');
    expect($this->driver->parseEventType(['type' => 'recurring_donation.started']))->toBe('recurring');
    expect($this->driver->parseEventType(['type' => 'wishlist_payment.created']))->toBe('wishlist');
});

test('parseEventType returns null for refund and update events', function () {
    expect($this->driver->parseEventType(['type' => 'donation.refunded']))->toBeNull();
    expect($this->driver->parseEventType(['type' => 'membership.cancelled']))->toBeNull();
    expect($this->driver->parseEventType(['type' => 'recurring_donation.updated']))->toBeNull();
    expect($this->driver->parseEventType([]))->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// normalizeEvent - per event type
// ──────────────────────────────────────────────────────────────────────────────

test('normalizeEvent for donation populates the full tag set', function () {
    $payload = [
        'type' => 'donation.created',
        'live_mode' => true,
        'data' => [
            'id' => 58,
            'amount' => 5,
            'currency' => 'USD',
            'message' => 'John bought you a coffee',
            'support_note' => 'Thanks for the good work',
            'support_type' => 'Supporter',
            'supporter_name' => 'John',
            'transaction_id' => 'pi_3Mc51bJEtINljGAa0zVykgUE',
            'supporter_email' => 'john@example.com',
            'coffee_count' => 1,
            'note_hidden' => 'false',
        ],
    ];

    $event = $this->driver->normalizeEvent($payload, 'donation');

    expect($event->getService())->toBe('bmac');
    expect($event->getEventType())->toBe('donation');
    expect($event->getMessageId())->toBe('bmac:donation.created:58');
    expect($event->getFromName())->toBe('John');
    expect($event->getMessage())->toBe('Thanks for the good work');
    expect($event->getAmount())->toBe('5');
    expect($event->getCurrency())->toBe('USD');

    $tags = $event->getTemplateTags();
    expect($tags['event.from_name'])->toBe('John');
    expect($tags['event.support_note'])->toBe('Thanks for the good work');
    expect($tags['event.message'])->toBe('John bought you a coffee');
    expect($tags['event.amount'])->toBe('5');
    expect($tags['event.currency'])->toBe('USD');
    expect($tags['event.support_type'])->toBe('Supporter');
    expect($tags['event.source'])->toBe('Buy Me a Coffee');
    expect($tags['event.coffee_count'])->toBe('1');
    expect($tags['event.is_recurring'])->toBe('0');
    expect($tags['event.is_membership'])->toBe('0');
    expect($tags['event.live_mode'])->toBe('1');
    expect($tags['event.transaction_id'])->toBe('pi_3Mc51bJEtINljGAa0zVykgUE');
});

test('normalizeEvent for commission populates commission_name and strips PII', function () {
    $payload = [
        'type' => 'commission_order.created',
        'data' => [
            'id' => 63,
            'amount' => 1050,
            'currency' => 'USD',
            'message' => 'John purchased a commission',
            'support_type' => 'Commission',
            'supporter_name' => 'John',
            'transaction_id' => 'pi_3McSHBJEtINljGAa1mx2Vjvk',
            'supporter_email' => 'john@example.com',
            'total_amount_charged' => '1080.75',
            'commission' => [
                'name' => 'Illustration & Sketch',
                'amount' => '1000.00',
                'shipping_address' => [
                    'zip' => '14624',
                    'city' => 'Rochester',
                    'name' => 'John Doe',
                ],
            ],
        ],
    ];

    $event = $this->driver->normalizeEvent($payload, 'commission');

    expect($event->getMessageId())->toBe('bmac:commission_order.created:63');
    expect($event->getAmount())->toBe('1050');
    expect($event->getTemplateTags()['event.commission_name'])->toBe('Illustration & Sketch');

    $raw = $event->getRaw();
    expect($raw['data'])->not()->toHaveKey('supporter_email');
    expect($raw['data'])->not()->toHaveKey('total_amount_charged');
    expect($raw['data'])->not()->toHaveKey('shipping_address');
    expect($raw['data']['commission'])->not()->toHaveKey('shipping_address');
    expect($raw['data']['commission']['name'])->toBe('Illustration & Sketch');
});

test('normalizeEvent for extra purchase populates extras_title from first item', function () {
    $payload = [
        'type' => 'extra_purchase.created',
        'data' => [
            'id' => 59,
            'amount' => 75,
            'currency' => 'USD',
            'message' => 'John just claimed Content Creation Advice',
            'supporter_name' => 'John',
            'support_type' => 'Extra',
            'transaction_id' => 'pi_3Mc5I3JEtINljGAa0XZxB3XG',
            'supporter_email' => 'john@example.com',
            'total_amount_charged' => '77.48',
            'extras' => [
                ['title' => 'Content Creation Advice', 'amount' => '75.00', 'quantity' => 2],
            ],
            'note_hidden' => 'false',
        ],
    ];

    $event = $this->driver->normalizeEvent($payload, 'extra');

    expect($event->getMessageId())->toBe('bmac:extra_purchase.created:59');
    expect($event->getTemplateTags()['event.extras_title'])->toBe('Content Creation Advice');
    expect($event->getRaw()['data'])->not()->toHaveKey('total_amount_charged');
});

test('normalizeEvent for membership with note_hidden true blanks the support_note', function () {
    $payload = [
        'type' => 'membership.started',
        'data' => [
            'id' => 16,
            'amount' => 1,
            'currency' => 'USD',
            'support_note' => 'Thanks for the good work',
            'note_hidden' => true,
            'membership_level_name' => 'Basic',
            'supporter_name' => 'John',
            'psp_id' => 'sub_1Mc70vJEtINljGAa1xaGI5q9',
            'supporter_email' => 'john@example.com',
        ],
    ];

    $event = $this->driver->normalizeEvent($payload, 'membership');

    $tags = $event->getTemplateTags();
    expect($event->getMessage())->toBe('');
    expect($tags['event.support_note'])->toBe('');
    expect($tags['event.is_membership'])->toBe('1');
    expect($tags['event.is_recurring'])->toBe('1');
    expect($tags['event.transaction_id'])->toBe('sub_1Mc70vJEtINljGAa1xaGI5q9');
});

test('normalizeEvent for recurring donation with note_hidden false keeps the support_note', function () {
    $payload = [
        'type' => 'recurring_donation.started',
        'data' => [
            'id' => 16,
            'amount' => 1,
            'currency' => 'USD',
            'support_note' => 'Thanks for the good work',
            'note_hidden' => false,
            'supporter_name' => 'John',
            'psp_id' => 'sub_recurring_001',
            'supporter_email' => 'john@example.com',
        ],
    ];

    $event = $this->driver->normalizeEvent($payload, 'recurring');

    $tags = $event->getTemplateTags();
    expect($event->getMessage())->toBe('Thanks for the good work');
    expect($tags['event.support_note'])->toBe('Thanks for the good work');
    expect($tags['event.is_recurring'])->toBe('1');
    expect($tags['event.is_membership'])->toBe('0');
});

test('normalizeEvent for wishlist payment with string note_hidden false renders the note', function () {
    $payload = [
        'type' => 'wishlist_payment.created',
        'data' => [
            'id' => 61,
            'amount' => 10,
            'currency' => 'USD',
            'message' => 'John contributed $10 to fund PS5',
            'support_note' => 'Keep up the good work',
            'note_hidden' => 'false',
            'supporter_name' => 'John',
            'support_type' => 'Wishlist',
            'transaction_id' => 'pi_3Mc7U5JEtINljGAa0GWKxfuN',
            'supporter_email' => 'john@example.com',
            'wishlist' => [
                'title' => 'PS5',
                'price' => '100.00',
            ],
        ],
    ];

    $event = $this->driver->normalizeEvent($payload, 'wishlist');

    $tags = $event->getTemplateTags();
    expect($event->getMessage())->toBe('Keep up the good work');
    expect($tags['event.wishlist_title'])->toBe('PS5');
    expect($tags['event.support_note'])->toBe('Keep up the good work');
});

// ──────────────────────────────────────────────────────────────────────────────
// PII: email captured into private metadata, raw payload sanitized
// ──────────────────────────────────────────────────────────────────────────────

test('normalizeEvent captures supporter email into hash + plaintext, strips from raw', function () {
    $payload = [
        'type' => 'donation.created',
        'data' => [
            'id' => 58,
            'amount' => 5,
            'currency' => 'USD',
            'supporter_name' => 'John',
            'transaction_id' => 'pi_strip',
            'supporter_email' => 'JOHN@example.com',
            'total_amount_charged' => '5.45',
        ],
    ];

    $event = $this->driver->normalizeEvent($payload, 'donation');

    expect($event->getSupporterEmail())->toBe('JOHN@example.com');
    expect($event->getSupporterEmailHash())->toBe(hash('sha256', 'john@example.com'));

    $raw = $event->getRaw();
    expect($raw['data'])->not()->toHaveKey('supporter_email');
    expect($raw['data'])->not()->toHaveKey('total_amount_charged');

    // template tags must never include the email
    foreach ($event->getTemplateTags() as $value) {
        expect($value)->not()->toContain('john@example.com');
    }
});

test('normalizeEvent leaves email/hash null when supporter_email missing', function () {
    $payload = [
        'type' => 'donation.created',
        'data' => [
            'id' => 99,
            'amount' => 5,
            'currency' => 'USD',
            'supporter_name' => 'Anonymous',
        ],
    ];

    $event = $this->driver->normalizeEvent($payload, 'donation');

    expect($event->getSupporterEmail())->toBeNull();
    expect($event->getSupporterEmailHash())->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// messageId stability
// ──────────────────────────────────────────────────────────────────────────────

test('normalizeEvent produces stable messageId across retries of the same event', function () {
    $payload = [
        'type' => 'donation.created',
        'data' => ['id' => 58, 'amount' => 5, 'currency' => 'USD', 'supporter_name' => 'John'],
    ];

    $first = $this->driver->normalizeEvent($payload, 'donation')->getMessageId();
    $second = $this->driver->normalizeEvent($payload, 'donation')->getMessageId();

    expect($first)->toBe($second)->toBe('bmac:donation.created:58');
});

// ──────────────────────────────────────────────────────────────────────────────
// verifyRequest (HMAC-SHA256)
// ──────────────────────────────────────────────────────────────────────────────

test('verifyRequest accepts a request with a valid HMAC signature', function () {
    $secret = 'my-bmac-secret';
    $body = json_encode(['type' => 'donation.created', 'data' => ['id' => 1]]);
    $signature = hash_hmac('sha256', $body, $secret);

    $integration = new ExternalIntegration;
    $integration->credentials = Crypt::encryptString(json_encode(['webhook_secret' => $secret]));

    $request = Request::create('/api/webhooks/bmac/x', 'POST', [], [], [], [
        'HTTP_X_SIGNATURE_SHA256' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body);

    expect($this->driver->verifyRequest($request, $integration))->toBeTrue();
});

test('verifyRequest rejects a request with a wrong HMAC signature', function () {
    $secret = 'my-bmac-secret';
    $body = json_encode(['type' => 'donation.created', 'data' => ['id' => 1]]);

    $integration = new ExternalIntegration;
    $integration->credentials = Crypt::encryptString(json_encode(['webhook_secret' => $secret]));

    $request = Request::create('/api/webhooks/bmac/x', 'POST', [], [], [], [
        'HTTP_X_SIGNATURE_SHA256' => str_repeat('0', 64),
        'CONTENT_TYPE' => 'application/json',
    ], $body);

    expect($this->driver->verifyRequest($request, $integration))->toBeFalse();
});

test('verifyRequest rejects when the signature header is missing', function () {
    $integration = new ExternalIntegration;
    $integration->credentials = Crypt::encryptString(json_encode(['webhook_secret' => 'whatever']));

    $request = Request::create('/api/webhooks/bmac/x', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], '{"type":"donation.created"}');

    expect($this->driver->verifyRequest($request, $integration))->toBeFalse();
});

test('verifyRequest rejects when no secret is stored', function () {
    $integration = new ExternalIntegration(['credentials' => null]);

    $request = Request::create('/api/webhooks/bmac/x', 'POST', [], [], [], [
        'HTTP_X_SIGNATURE_SHA256' => 'whatever',
        'CONTENT_TYPE' => 'application/json',
    ], '{}');

    expect($this->driver->verifyRequest($request, $integration))->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────────
// getControlUpdates
// ──────────────────────────────────────────────────────────────────────────────

test('getControlUpdates increments donations_received for every supported event', function () {
    foreach (['donation', 'commission', 'extra', 'membership', 'recurring', 'wishlist'] as $eventType) {
        $payload = [
            'type' => 'donation.created',
            'data' => [
                'id' => 1,
                'amount' => '12.50',
                'currency' => 'USD',
                'supporter_name' => 'Pat',
            ],
        ];
        $event = $this->driver->normalizeEvent($payload, $eventType);
        $updates = $this->driver->getControlUpdates($event);

        expect($updates['donations_received'])->toBe(['action' => 'increment']);
        expect($updates['latest_donor_name'])->toBe('Pat');
        expect($updates['latest_donation_amount'])->toBe('12.50');
        expect($updates['latest_donation_currency'])->toBe('USD');
        expect($updates['total_received'])->toBe(['action' => 'add', 'amount' => 12.50]);
    }
});

test('getAutoProvisionedControls returns the seven preset keys', function () {
    $controls = $this->driver->getAutoProvisionedControls();
    $keys = array_column($controls, 'key');

    expect($keys)->toContain('donations_received')
        ->toContain('latest_donor_name')
        ->toContain('latest_donation_amount')
        ->toContain('latest_donation_message')
        ->toContain('latest_donation_currency')
        ->toContain('total_received')
        ->toContain('latest_support_type');
});
