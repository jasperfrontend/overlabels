<?php

use App\Models\PaddleCustomer;
use App\Models\PaddlePurchase;
use App\Models\User;
use App\Services\Paddle\PaddleSignature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;

uses(DatabaseTransactions::class);

const PADDLE_TEST_SECRET = 'pdl_ntfset_test_secret';

beforeEach(function () {
    config(['services.paddle.webhook_secret' => PADDLE_TEST_SECRET]);
});

/** A transaction.completed event in the shape Paddle sent on 2026-11-08 (sandbox). */
function paddleCompletedEvent(array $customData = [], string $occurredAt = '2026-11-08T21:28:29.687746Z', string $txn = 'txn_01m6ygy4mfkkx6cw10fgjbw7dt'): array
{
    return [
        'event_id' => 'evt_'.fake()->unique()->numerify('##########'),
        'event_type' => 'transaction.completed',
        'occurred_at' => $occurredAt,
        'notification_id' => 'ntf_test',
        'data' => [
            'id' => $txn,
            'status' => 'completed',
            'customer_id' => 'ctm_01m6ygyykndr5vsg1n4kzcccvj',
            'custom_data' => $customData,
            'currency_code' => 'EUR',
            'billed_at' => '2026-11-08T21:28:28.358583Z',
            'items' => [['price' => ['id' => 'pri_test', 'product_id' => 'pro_test'], 'quantity' => 1]],
            'details' => ['line_items' => [[
                'price_id' => 'pri_test',
                'product' => ['id' => 'pro_test'],
                'totals' => ['subtotal' => '83', 'tax' => '17', 'discount' => '0', 'total' => '100'],
            ]]],
        ],
    ];
}

function paddleAdjustmentEvent(string $action, string $type, string $status = 'approved'): array
{
    return [
        'event_id' => 'evt_'.fake()->unique()->numerify('##########'),
        'event_type' => 'adjustment.updated',
        'occurred_at' => '2026-11-09T10:00:00Z',
        'data' => [
            'id' => 'adj_test',
            'action' => $action,
            'type' => $type,
            'status' => $status,
            'transaction_id' => 'txn_01m6ygy4mfkkx6cw10fgjbw7dt',
        ],
    ];
}

function paddlePost(array|string $payload, ?string $signature = null, ?int $ts = null): TestResponse
{
    $raw = is_string($payload) ? $payload : json_encode($payload);
    $ts ??= time();
    $signature ??= 'ts='.$ts.';h1='.hash_hmac('sha256', $ts.':'.$raw, PADDLE_TEST_SECRET);

    return test()->call('POST', '/api/paddle/webhook', [], [], [], [
        'HTTP_PADDLE_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $raw);
}

// ── Signature ────────────────────────────────────────────────────────────────

it('rejects a delivery with no signature, without writing anything', function () {
    paddlePost(paddleCompletedEvent(), '')->assertStatus(401);

    expect(PaddlePurchase::count())->toBe(0);
});

it('rejects a body that was changed after signing', function () {
    $raw = json_encode(paddleCompletedEvent());
    $ts = time();
    $signature = 'ts='.$ts.';h1='.hash_hmac('sha256', $ts.':'.$raw, PADDLE_TEST_SECRET);

    paddlePost(str_replace('"100"', '"1"', $raw), $signature)->assertStatus(401);
});

it('rejects a signature made with the wrong secret', function () {
    $raw = json_encode(paddleCompletedEvent());
    $ts = time();

    paddlePost($raw, 'ts='.$ts.';h1='.hash_hmac('sha256', $ts.':'.$raw, 'not-the-secret'))->assertStatus(401);
});

it('rejects a signature older than the tolerance window', function () {
    paddlePost(paddleCompletedEvent(), null, time() - PaddleSignature::TOLERANCE_SECONDS - 10)->assertStatus(401);
});

it('accepts any one of several h1 values while a secret rotates', function () {
    $raw = json_encode(paddleCompletedEvent());
    $ts = time();
    $good = hash_hmac('sha256', $ts.':'.$raw, PADDLE_TEST_SECRET);

    paddlePost($raw, "ts={$ts};h1=deadbeef;h1={$good}")->assertOk();
});

it('answers 500, never 2xx, when the secret is not configured', function () {
    config(['services.paddle.webhook_secret' => '']);

    paddlePost(paddleCompletedEvent())->assertStatus(500);
});

// ── transaction.completed ────────────────────────────────────────────────────

it('grants the product to the user named in custom_data', function () {
    $user = User::factory()->create();

    paddlePost(paddleCompletedEvent(['overlabels_user_id' => $user->id]))->assertOk();

    $purchase = PaddlePurchase::sole();
    expect($purchase->user_id)->toBe($user->id)
        ->and($purchase->product_id)->toBe('pro_test')
        ->and($purchase->amount)->toBe('100')
        ->and($purchase->currency)->toBe('EUR')
        ->and(PaddlePurchase::userOwns($user, 'pro_test'))->toBeTrue()
        ->and(PaddleCustomer::find('ctm_01m6ygyykndr5vsg1n4kzcccvj')->user_id)->toBe($user->id);
});

it('is idempotent when Paddle delivers the same event twice', function () {
    $user = User::factory()->create();
    $event = paddleCompletedEvent(['overlabels_user_id' => $user->id]);

    paddlePost($event)->assertOk();
    paddlePost($event)->assertOk();

    expect(PaddlePurchase::count())->toBe(1)->and(PaddleCustomer::count())->toBe(1);
});

it('keeps an unlinked sale when custom_data names no real account', function (array $customData) {
    paddlePost(paddleCompletedEvent($customData))->assertOk();

    expect(PaddlePurchase::sole()->user_id)->toBeNull();
})->with([
    'missing' => [[]],
    'not a number' => [['overlabels_user_id' => 'test']],
    'unknown id' => [['overlabels_user_id' => 999999999]],
]);

it('does not let an older delivery overwrite a newer one', function () {
    $user = User::factory()->create();

    paddlePost(paddleCompletedEvent(['overlabels_user_id' => $user->id], '2026-11-08T22:00:00Z'))->assertOk();
    paddlePost(paddleCompletedEvent([], '2026-11-08T21:00:00Z'))->assertOk();

    $purchase = PaddlePurchase::sole();
    expect($purchase->user_id)->toBe($user->id)
        ->and($purchase->paddle_occurred_at->toIso8601String())->toBe('2026-11-08T22:00:00+00:00');
});

// ── Refunds and chargebacks ──────────────────────────────────────────────────

it('takes the product back on an approved full refund', function () {
    $user = User::factory()->create();
    paddlePost(paddleCompletedEvent(['overlabels_user_id' => $user->id]))->assertOk();

    paddlePost(paddleAdjustmentEvent('refund', 'full'))->assertOk();

    expect(PaddlePurchase::userOwns($user, 'pro_test'))->toBeFalse();
});

it('leaves access alone on a partial or unapproved refund', function (string $type, string $status) {
    $user = User::factory()->create();
    paddlePost(paddleCompletedEvent(['overlabels_user_id' => $user->id]))->assertOk();

    paddlePost(paddleAdjustmentEvent('refund', $type, $status))->assertOk();

    expect(PaddlePurchase::userOwns($user, 'pro_test'))->toBeTrue();
})->with([
    'partial, approved' => ['partial', 'approved'],
    'full, pending' => ['full', 'pending_approval'],
    'full, rejected' => ['full', 'rejected'],
]);

it('gives the product back when a chargeback is reversed', function () {
    $user = User::factory()->create();
    paddlePost(paddleCompletedEvent(['overlabels_user_id' => $user->id]))->assertOk();
    paddlePost(paddleAdjustmentEvent('chargeback', 'full'))->assertOk();
    expect(PaddlePurchase::userOwns($user, 'pro_test'))->toBeFalse();

    paddlePost(paddleAdjustmentEvent('chargeback_reverse', 'full'))->assertOk();

    expect(PaddlePurchase::userOwns($user, 'pro_test'))->toBeTrue();
});

// ── Other events ─────────────────────────────────────────────────────────────

it('records a customer from customer.created without any email', function () {
    paddlePost([
        'event_id' => 'evt_c', 'event_type' => 'customer.created', 'occurred_at' => '2026-11-08T21:27:00Z',
        'data' => ['id' => 'ctm_new', 'email' => 'buyer@example.com', 'status' => 'active'],
    ])->assertOk();

    $row = PaddleCustomer::find('ctm_new');
    expect($row)->not->toBeNull()
        ->and($row->user_id)->toBeNull()
        ->and(json_encode($row->getAttributes()))->not->toContain('example.com');
});

it('acknowledges subscription events and unknown types without writing', function (string $type) {
    paddlePost(['event_id' => 'evt_s', 'event_type' => $type, 'occurred_at' => '2026-11-08T21:27:00Z', 'data' => ['id' => 'sub_x']])
        ->assertOk();

    expect(PaddlePurchase::count())->toBe(0)->and(PaddleCustomer::count())->toBe(0);
})->with(['subscription.created', 'subscription.updated', 'subscription.canceled', 'payout.paid']);

it('rejects a signed body that is not a Paddle event', function () {
    paddlePost('{"hello":"world"}')->assertStatus(400);
});
