<?php

use App\Models\User;
use App\Models\UserEventsubSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * Twitch sends a `revocation` message when it stops delivering a subscription
 * (authorization_revoked, user_removed, notification_failures_exceeded, ...).
 * The payload is the subscription object with its new status and no `event` key.
 * The local row has to take that status, or the integrations page and
 * eventsub:monitor keep reporting the subscription as enabled until the
 * 24-hour verify window happens to catch it.
 */
function postTwitchRevocation(array $subscription): TestResponse
{
    config(['app.twitch_webhook_secret' => 'test-webhook-secret']);

    $body = json_encode(['subscription' => $subscription]);
    $messageId = 'msg-'.uniqid();
    $timestamp = now()->toIso8601String();
    $signature = 'sha256='.hash_hmac('sha256', $messageId.$timestamp.$body, 'test-webhook-secret');

    return test()->call('POST', '/api/twitch/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_TWITCH_EVENTSUB_MESSAGE_TYPE' => 'revocation',
        'HTTP_TWITCH_EVENTSUB_MESSAGE_ID' => $messageId,
        'HTTP_TWITCH_EVENTSUB_MESSAGE_TIMESTAMP' => $timestamp,
        'HTTP_TWITCH_EVENTSUB_MESSAGE_SIGNATURE' => $signature,
    ], $body);
}

test('a revocation writes the status Twitch sent onto the local subscription row', function () {
    Carbon::setTestNow('2026-08-26 12:00:00');

    $user = User::factory()->create();
    $subscription = UserEventsubSubscription::factory()->for($user)->create([
        'twitch_subscription_id' => 'sub-abc',
        'event_type' => 'channel.follow',
        'status' => 'enabled',
        'last_verified_at' => Carbon::parse('2026-08-25 12:00:00'),
    ]);

    postTwitchRevocation([
        'id' => 'sub-abc',
        'status' => 'authorization_revoked',
        'type' => 'channel.follow',
        'version' => '2',
        'condition' => ['broadcaster_user_id' => $user->twitch_id],
    ])->assertOk();

    $subscription->refresh();

    expect($subscription->status)->toBe('authorization_revoked')
        ->and($subscription->last_verified_at->toDateTimeString())->toBe('2026-08-26 12:00:00');
});

test('a revocation for a subscription we do not hold is absorbed with a 200', function () {
    $other = UserEventsubSubscription::factory()->create([
        'twitch_subscription_id' => 'sub-other',
        'status' => 'enabled',
    ]);

    postTwitchRevocation([
        'id' => 'sub-unknown',
        'status' => 'user_removed',
        'type' => 'channel.follow',
    ])->assertOk();

    expect($other->refresh()->status)->toBe('enabled');
});

test('a revocation without a status leaves the row alone', function () {
    $subscription = UserEventsubSubscription::factory()->create([
        'twitch_subscription_id' => 'sub-abc',
        'status' => 'enabled',
    ]);

    postTwitchRevocation([
        'id' => 'sub-abc',
        'type' => 'channel.follow',
    ])->assertOk();

    expect($subscription->refresh()->status)->toBe('enabled');
});
