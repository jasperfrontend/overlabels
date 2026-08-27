<?php

use App\Enums\DeliveryOutcome;
use App\Models\ExternalEvent;
use App\Models\StreamSession;
use App\Models\TwitchEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * The Delivery tab on /dashboard/stream-sessions, from the ledger. Matched by
 * the session time window like every other loader on that page - the
 * stream_session_id FK is left null here on purpose.
 */
function deliveryUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'token_expires_at' => now()->addHours(3),
    ], $attrs));
}

function deliverySession(User $user): StreamSession
{
    return StreamSession::create([
        'user_id' => $user->id,
        'started_at' => now()->subHours(3),
        'ended_at' => now()->subHour(),
    ]);
}

function ledgerEvent(User $user, ?DeliveryOutcome $outcome, Carbon $at, ?int $latencyMs = null, ?int $connections = null, string $type = 'channel.follow'): void
{
    $event = new TwitchEvent([
        'user_id' => $user->id,
        'event_type' => $type,
        'event_data' => [],
        'twitch_timestamp' => $at,
        'alert_id' => $outcome?->isScored() ? (string) Str::uuid() : null,
        'outcome' => $outcome?->value,
        'connections' => $connections,
        'delivered_at' => $latencyMs !== null ? $at->copy()->addMilliseconds($latencyMs) : null,
    ]);
    $event->created_at = $at;
    $event->save();
}

function ledgerExternal(User $user, DeliveryOutcome $outcome, Carbon $at): void
{
    $event = new ExternalEvent([
        'user_id' => $user->id, 'service' => 'kofi', 'event_type' => 'donation',
        'message_id' => (string) Str::uuid(), 'raw_payload' => [], 'normalized_payload' => [],
        'alert_id' => (string) Str::uuid(), 'outcome' => $outcome->value,
    ]);
    $event->created_at = $at;
    $event->save();
}

function deliveryFor(User $user): ?array
{
    $sessions = test()->actingAs($user)->get('/dashboard/stream-sessions')->assertOk()
        ->viewData('page')['props']['sessions'];

    return $sessions[0]['delivery'];
}

it('scores the delivery family and reports no_target as context', function () {
    $user = deliveryUser();
    deliverySession($user);
    $mid = now()->subHours(2);

    // Ledger timestamps are whole seconds (timestamp(0)), so latency is too.
    ledgerEvent($user, DeliveryOutcome::Delivered, $mid, 1000, 2);
    ledgerEvent($user, DeliveryOutcome::Delivered, $mid->copy()->addMinute(), 3000, 1);
    ledgerEvent($user, DeliveryOutcome::NoListener, $mid->copy()->addMinutes(2), null, 0);
    ledgerEvent($user, DeliveryOutcome::Failed, $mid->copy()->addMinutes(3));
    ledgerExternal($user, DeliveryOutcome::Delivered, $mid->copy()->addMinutes(4));
    ledgerEvent($user, DeliveryOutcome::NoMapping, $mid->copy()->addMinutes(5));
    ledgerEvent($user, DeliveryOutcome::ChatOnly, $mid->copy()->addMinutes(6));
    ledgerEvent($user, null, $mid->copy()->addMinutes(7)); // before the ledger

    $d = deliveryFor($user);

    expect($d['scored'])->toBe(5)
        ->and($d['delivered'])->toBe(3)
        ->and($d['no_listener'])->toBe(1)
        ->and($d['failed'])->toBe(1)
        ->and($d['no_target'])->toBe(['no_mapping' => 1, 'muted' => 0, 'chat_only' => 1, 'unknown_user' => 0, 'total' => 2])
        ->and($d['latency_p50_ms'])->toBe(2000)
        ->and($d['latency_p95_ms'])->toBe(2900)
        ->and($d['first_no_listener_at'])->not->toBeNull()
        ->and($d['token_expired_at'])->toBeNull();

    expect($d['failures'])->toHaveCount(2)
        ->and($d['failures'][0]['outcome'])->toBe('failed')
        ->and($d['failures'][1]['outcome'])->toBe('no_listener');
});

it('is null for a stream with no scored row, even one full of no_target events', function () {
    $user = deliveryUser();
    deliverySession($user);
    ledgerEvent($user, DeliveryOutcome::NoMapping, now()->subHours(2));
    ledgerEvent($user, null, now()->subHours(2));

    expect(deliveryFor($user))->toBeNull();
});

it('dates the expired login when alerts failed on the token', function () {
    $user = deliveryUser(['token_expires_at' => Carbon::parse('2026-06-14 08:55:54')]);
    deliverySession($user);
    ledgerEvent($user, DeliveryOutcome::TokenInvalid, now()->subHours(2));

    $d = deliveryFor($user);

    expect($d['token_invalid'])->toBe(1)
        ->and($d['token_expired_at'])->toStartWith('2026-06-14');
});

it('lists the 20 newest failures, newest first', function () {
    $user = deliveryUser();
    deliverySession($user);
    $start = now()->subHours(2);
    foreach (range(1, 25) as $i) {
        ledgerEvent($user, DeliveryOutcome::Failed, $start->copy()->addMinutes($i));
    }

    $d = deliveryFor($user);

    expect($d['failures'])->toHaveCount(20)
        ->and(Carbon::parse($d['failures'][0]['at'])->gt(Carbon::parse($d['failures'][19]['at'])))->toBeTrue();
});

it('ignores rows outside the window and rows of other users', function () {
    $user = deliveryUser();
    deliverySession($user);
    $other = deliveryUser();

    ledgerEvent($user, DeliveryOutcome::Delivered, now()->subHours(2), 500, 1);
    ledgerEvent($user, DeliveryOutcome::Failed, now()->subHours(6));
    ledgerEvent($other, DeliveryOutcome::Failed, now()->subHours(2));

    $d = deliveryFor($user);

    expect($d['scored'])->toBe(1)->and($d['failed'])->toBe(0)->and($d['failures'])->toBe([]);
});

it('includes the outcome word on each row of the events feed', function () {
    $user = deliveryUser();
    ledgerEvent($user, DeliveryOutcome::TokenInvalid, now()->subMinute());
    ledgerEvent($user, null, now()->subMinutes(2));

    $events = test()->actingAs($user)->get('/dashboard/events')->assertOk()
        ->viewData('page')['props']['events']['data'];

    expect($events[0]['outcome'])->toBe('token_invalid')
        ->and($events[1]['outcome'])->toBeNull();
});
