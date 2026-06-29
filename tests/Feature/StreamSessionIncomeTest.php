<?php

use App\Models\ExternalEvent;
use App\Models\StreamSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeDonation(int $userId, int $sessionId, string $service, string $amount, string $currency, string $from, ?string $message = null): void
{
    ExternalEvent::create([
        'user_id' => $userId,
        'service' => $service,
        'event_type' => 'donation',
        'message_id' => (string) fake()->unique()->uuid(),
        'stream_session_id' => $sessionId,
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
}

it('aggregates external donation income per session, split by service and currency', function () {
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $session = StreamSession::create([
        'user_id' => $user->id,
        'started_at' => now()->subHours(3),
        'ended_at' => now()->subHour(),
    ]);

    makeDonation($user->id, $session->id, 'kofi', '50.00', 'USD', 'alice', 'love the stream!');
    makeDonation($user->id, $session->id, 'kofi', '9.00', 'USD', 'bob');
    makeDonation($user->id, $session->id, 'streamelements', '25.00', 'EUR', 'chris', 'o7');

    // A non-donation external event (no amount) must be ignored, not crash the cast.
    ExternalEvent::create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'event_type' => 'subscription',
        'message_id' => (string) fake()->unique()->uuid(),
        'stream_session_id' => $session->id,
        'raw_payload' => [],
        'normalized_payload' => ['event.from_name' => 'dana', 'event.amount' => ''],
    ]);

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
