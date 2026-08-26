<?php

use App\Enums\DeliveryOutcome;
use App\Models\EventTemplateMapping;
use App\Models\OverlayTemplate;
use App\Models\StreamState;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Models\UserEventsubSubscription;
use App\Services\BroadcastMeter;
use App\Support\WiringCatalog;
use App\Support\WiringFacts;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

/**
 * The alerts circuit on /wiring: four present-tense wires read from state the
 * app already holds. The one user whose alerts had failed for ten weeks in
 * prod (token expired June 14th) would have lit `alerts.token_valid` on day
 * one. None of the four is a suggestion: with no alert set up, none applies.
 */
function alertsUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'token_expires_at' => now()->addHours(3),
        'eventsub_connected_at' => now()->subDay(),
    ], $attrs));
}

function alertsMapping(User $user): void
{
    $alert = OverlayTemplate::factory()->create([
        'owner_id' => $user->id, 'fork_of_id' => null, 'type' => 'alert',
        'slug' => 'alert-'.fake()->unique()->lexify('????????'),
    ]);
    EventTemplateMapping::create([
        'user_id' => $user->id, 'event_type' => 'channel.follow', 'template_id' => $alert->id,
        'enabled' => true, 'duration_ms' => 5000,
    ]);
}

function alertsSubscription(User $user, string $status = 'enabled'): void
{
    UserEventsubSubscription::factory()->for($user)->create(['status' => $status, 'condition' => []]);
}

function alertsStates(User $user): array
{
    return WiringFacts::for($user)['alerts'][0]['states'];
}

function alertsContext(User $user): array
{
    return WiringFacts::for($user)['alerts'][0]['context'];
}

function scoredRow(User $user, DeliveryOutcome $outcome, int $minutesAgo, ?int $connections = null): void
{
    $row = TwitchEvent::create([
        'user_id' => $user->id, 'event_type' => 'channel.follow', 'event_data' => [],
        'twitch_timestamp' => now(),
        'alert_id' => (string) fake()->uuid(), 'outcome' => $outcome->value, 'connections' => $connections,
    ]);
    // created_at is not mass-assignable; the wire orders by it.
    DB::table('twitch_events')->where('id', $row->id)->update(['created_at' => now()->subMinutes($minutesAgo)]);
}

beforeEach(fn () => Cache::flush());

test('with no alert set up none of the four questions arise', function () {
    $user = alertsUser(['token_expires_at' => now()->subMonths(2)]);

    expect(array_values(alertsStates($user)))->each->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and(alertsContext($user))->toBe([]);
});

test('a valid Twitch login satisfies the token wire and an expired one is a finding', function () {
    $user = alertsUser();
    alertsMapping($user);
    alertsSubscription($user);
    expect(alertsStates($user)['alerts.token_valid'])->toBe(WiringCatalog::SATISFIED);

    $user->forceFill(['token_expires_at' => now()->subMonths(2)])->save();
    expect(alertsStates($user)['alerts.token_valid'])->toBe(WiringCatalog::MISSING)
        ->and(implode(' ', alertsContext($user)))->toContain('Twitch login expired');
});

test('the subscribed wire needs a connection and every subscription enabled', function () {
    $user = alertsUser();
    alertsMapping($user);
    alertsSubscription($user);
    alertsSubscription($user);
    expect(alertsStates($user)['alerts.subscribed'])->toBe(WiringCatalog::SATISFIED)
        ->and(implode(' ', alertsContext($user)))->toContain('2 Twitch subscriptions active');

    alertsSubscription($user, 'authorization_revoked');
    expect(alertsStates($user)['alerts.subscribed'])->toBe(WiringCatalog::MISSING)
        ->and(implode(' ', alertsContext($user)))->toContain('1 of 3 Twitch subscriptions need repair');

    $user->forceFill(['eventsub_connected_at' => null])->save();
    expect(alertsStates($user)['alerts.subscribed'])->toBe(WiringCatalog::MISSING)
        ->and(implode(' ', alertsContext($user)))->toContain('Not connected to Twitch events');
});

test('the delivering wire reads the newest scored ledger row', function () {
    $user = alertsUser();
    alertsMapping($user);
    alertsSubscription($user);

    expect(alertsStates($user)['alerts.delivering'])->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and(implode(' ', alertsContext($user)))->toContain('No alert has fired in the last 7 days');

    scoredRow($user, DeliveryOutcome::Failed, 60);
    expect(alertsStates($user)['alerts.delivering'])->toBe(WiringCatalog::MISSING);

    scoredRow($user, DeliveryOutcome::Delivered, 5, 2);
    expect(alertsStates($user)['alerts.delivering'])->toBe(WiringCatalog::SATISFIED)
        ->and(implode(' ', alertsContext($user)))->toContain('Last alert reached 2 connections');
});

test('a refused token lights the token wire, not the delivering wire', function () {
    // One cause, one finding. token_invalid is evidence for the login, and the
    // delivering wire must not repeat it as a second loose end.
    $user = alertsUser(['token_expires_at' => now()->subMonths(2)]);
    alertsMapping($user);
    alertsSubscription($user);
    scoredRow($user, DeliveryOutcome::TokenInvalid, 10);

    $states = alertsStates($user);
    expect($states['alerts.token_valid'])->toBe(WiringCatalog::MISSING)
        ->and($states['alerts.delivering'])->toBe(WiringCatalog::SATISFIED)
        ->and(implode(' ', alertsContext($user)))->toContain('Twitch refused the login');
});

test('no_target rows are never evidence for the delivering wire', function () {
    $user = alertsUser();
    alertsMapping($user);
    alertsSubscription($user);
    TwitchEvent::create([
        'user_id' => $user->id, 'event_type' => 'channel.follow', 'event_data' => [],
        'twitch_timestamp' => now(), 'outcome' => DeliveryOutcome::NoMapping->value,
    ]);

    expect(alertsStates($user)['alerts.delivering'])->toBe(WiringCatalog::NOT_APPLICABLE);
});

test('the overlay wire is only a question while live', function () {
    $user = alertsUser();
    alertsMapping($user);
    alertsSubscription($user);
    app(BroadcastMeter::class)->recordDelivery(['private-alerts.'.$user->twitch_id => 0], 'alert.triggered');

    // Offline: an overlay closed between streams is not a loose end.
    expect(alertsStates($user)['alerts.overlay_listening'])->toBe(WiringCatalog::NOT_APPLICABLE);

    StreamState::forUser($user)->forceFill(['state' => 'live', 'confidence' => 1.0])->save();
    expect(alertsStates($user)['alerts.overlay_listening'])->toBe(WiringCatalog::MISSING)
        ->and(implode(' ', alertsContext($user)))->toContain('Live now: the last update reached 0 connections');

    app(BroadcastMeter::class)->recordDelivery(['private-alerts.'.$user->twitch_id => 3], 'alert.triggered');
    expect(alertsStates($user)['alerts.overlay_listening'])->toBe(WiringCatalog::SATISFIED);
});

test('live with nothing sent yet is not a finding either', function () {
    $user = alertsUser();
    alertsMapping($user);
    alertsSubscription($user);
    StreamState::forUser($user)->forceFill(['state' => 'live', 'confidence' => 1.0])->save();

    expect(alertsStates($user)['alerts.overlay_listening'])->toBe(WiringCatalog::NOT_APPLICABLE);
});

test('the alerts circuit renders on the page for an account with alerts', function () {
    $user = alertsUser(['token_expires_at' => now()->subMonths(2)]);
    alertsMapping($user);
    alertsSubscription($user);

    $this->actingAs($user)->get(route('wiring.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('circuits.0.key', 'alerts')
            ->where('circuits.0.status', 'loose_end')
            ->where('looseEnds', 1));
});
