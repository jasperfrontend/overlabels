<?php

use App\Models\ExternalEvent;
use App\Models\StreamSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * Income is matched by the session time window, NOT the stream_session_id FK -
 * so these donations deliberately leave the FK null and rely on created_at
 * falling inside the window, exactly as a real live donation does.
 */
function makeDonation(int $userId, string $service, string $amount, string $currency, string $from, Carbon $at, ?string $message = null): void
{
    $event = new ExternalEvent([
        'user_id' => $userId,
        'service' => $service,
        'event_type' => 'donation',
        'message_id' => (string) fake()->unique()->uuid(),
        'raw_payload' => [],
        'normalized_payload' => [
            'event.from_name' => $from,
            'event.message' => $message ?? '',
            'event.amount' => $amount,
            'event.currency' => $currency,
            'event.source' => $service,
            'event.type' => 'donation',
        ],
    ]);
    // Set created_at manually so it's "dirty" and Eloquent won't override it
    // with now() on insert - this is what places the event inside the window.
    $event->created_at = $at;
    $event->save();
}

it('aggregates external donation income per session, split by service and currency', function () {
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $session = StreamSession::create([
        'user_id' => $user->id,
        'started_at' => now()->subHours(3),
        'ended_at' => now()->subHour(),
    ]);

    // All inside the window (between started_at and ended_at), FK left null.
    $mid = now()->subHours(2);
    makeDonation($user->id, 'kofi', '50.00', 'USD', 'alice', $mid, 'love the stream!');
    makeDonation($user->id, 'kofi', '9.00', 'USD', 'bob', $mid);
    makeDonation($user->id, 'streamelements', '25.00', 'EUR', 'chris', $mid, 'o7');

    // Outside the window (5 hours ago) - must NOT be counted for this session.
    makeDonation($user->id, 'kofi', '999.00', 'USD', 'ghost', now()->subHours(5));

    // A non-donation external event (no amount) must be ignored, not crash the cast.
    $sub = new ExternalEvent([
        'user_id' => $user->id,
        'service' => 'kofi',
        'event_type' => 'subscription',
        'message_id' => (string) fake()->unique()->uuid(),
        'raw_payload' => [],
        'normalized_payload' => ['event.from_name' => 'dana', 'event.amount' => ''],
    ]);
    $sub->created_at = $mid;
    $sub->save();

    $response = $this->actingAs($user)->get('/dashboard/stream-sessions');

    $response->assertOk();
    $response->assertInertia(function ($page) {
        $page->component('dashboard/stream-sessions');
        $income = $page->toArray()['props']['sessions'][0]['stats']['income'];

        expect($income['count'])->toBe(3);
        expect($income['donations'])->toHaveCount(3);

        // Totals: Ko-fi USD 59.00 over 2 donations, SE EUR 25.00 over 1.
        $kofi = collect($income['totals'])->firstWhere('service', 'kofi');
        expect($kofi['currency'])->toBe('USD');
        expect($kofi['count'])->toBe(2);
        expect((float) $kofi['total'])->toEqual(59.0);

        $se = collect($income['totals'])->firstWhere('service', 'streamelements');
        expect($se['currency'])->toBe('EUR');
        expect((float) $se['total'])->toEqual(25.0);
    });
});

it('returns empty income for a session with no external events', function () {
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    StreamSession::create([
        'user_id' => $user->id,
        'started_at' => now()->subHours(2),
        'ended_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($user)->get('/dashboard/stream-sessions');

    $response->assertOk();
    $response->assertInertia(function ($page) {
        $income = $page->toArray()['props']['sessions'][0]['stats']['income'];
        expect($income['count'])->toBe(0);
        expect($income['totals'])->toBe([]);
        expect($income['donations'])->toBe([]);
    });
});
