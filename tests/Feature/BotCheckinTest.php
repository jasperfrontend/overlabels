<?php

use App\Events\CheckinsUpdated;
use App\Models\Checkin;
use App\Models\ExternalEvent;
use App\Models\ExternalIntegration;
use App\Models\GeoPlace;
use App\Models\OverlayControl;
use App\Models\StreamSession;
use App\Models\StreamState;
use App\Models\User;
use App\Services\External\ExternalControlService;
use App\Services\External\ExternalServiceRegistry;
use App\Services\Geo\PlaceResolverService;
use App\Services\StreamSessionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

uses(DatabaseTransactions::class);

const CHECKIN_URL = '/api/internal/bot/checkin/checkinstreamer';

beforeEach(function () {
    config(['services.twitchbot.listener_secret' => 'test-bot-secret']);
    Cache::flush();
    PlaceResolverService::flushCountryNameMap();

    $this->user = User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => 'checkinstreamer'],
    ]);

    foreach ([
        ['Rotterdam', 'NL', 51.9225, 4.47917, 623652],
        ['Amsterdam', 'NL', 52.37403, 4.88969, 821752],
        ['Paris', 'FR', 48.85341, 2.3488, 2138551],
    ] as [$name, $cc, $lat, $lng, $pop]) {
        $place = GeoPlace::create([
            'geonames_id' => random_int(1, PHP_INT_MAX),
            'name' => $name,
            'ascii_name' => $name,
            'country_code' => $cc,
            'lat' => $lat,
            'lng' => $lng,
            'population' => $pop,
        ]);
        $place->names()->create(['name_normalized' => PlaceResolverService::normalize($name)]);
    }
});

function checkinLiveState(User $user, string $state): void
{
    StreamState::updateOrCreate(
        ['user_id' => $user->id],
        ['state' => $state, 'confidence' => 1.0],
    );
}

function connectCheckin(User $user, array $settings = []): ExternalIntegration
{
    $integration = ExternalIntegration::create([
        'user_id' => $user->id,
        'service' => 'checkin',
        'enabled' => true,
        'settings' => array_merge(['pin_lifetime' => 'per_stream', 'cooldown_seconds' => 30], $settings),
    ]);

    app(ExternalControlService::class)->provision($user, ExternalServiceRegistry::driver('checkin'));

    // The command is gated on isConfidentlyLive, so a connected channel in
    // these tests is a LIVE channel; the offline tests flip the state back.
    checkinLiveState($user, StreamState::STATE_LIVE);

    return $integration;
}

function postCheckin(array $overrides = []): TestResponse
{
    return test()->postJson(CHECKIN_URL, array_merge([
        'chatter_id' => '111',
        'chatter_login' => 'viewer_one',
        'chatter_display_name' => 'ViewerOne',
        'args' => 'Rotterdam, NL',
    ], $overrides), ['X-Internal-Secret' => 'test-bot-secret']);
}

function checkinControlValue(User $user, string $key): string
{
    return (string) OverlayControl::where('user_id', $user->id)
        ->where('source', 'checkin')
        ->where('key', $key)
        ->value('value');
}

// ──────────────────────────────────────────────────────────────────────────────
// Auth, routing, gating
// ──────────────────────────────────────────────────────────────────────────────

test('checkin returns 403 without the internal secret', function () {
    $this->postJson(CHECKIN_URL, ['chatter_id' => '1'])->assertStatus(403);
});

test('checkin returns 404 for an unknown channel', function () {
    $this->postJson('/api/internal/bot/checkin/nobody', [
        'chatter_id' => '111',
        'chatter_login' => 'viewer_one',
        'chatter_display_name' => 'ViewerOne',
        'args' => 'Rotterdam',
    ], ['X-Internal-Secret' => 'test-bot-secret'])->assertStatus(404);
});

test('checkin stays silent when the integration is not connected', function () {
    postCheckin()->assertOk()->assertJson(['reply' => null]);

    expect(Checkin::count())->toBe(0);
});

test('checkin stays silent when the integration is disabled', function () {
    connectCheckin($this->user)->update(['enabled' => false]);

    postCheckin()->assertOk()->assertJson(['reply' => null]);
});

test('checkin is refused while the stream is offline, with a reply saying so', function () {
    connectCheckin($this->user);
    checkinLiveState($this->user, StreamState::STATE_OFFLINE);

    $response = postCheckin();

    // A spoken refusal, not silence: a swallowed command means the viewer
    // retypes it later and Twitch's repeated-message filter eats the retry.
    expect($response->json('reply'))->toContain('live')
        ->and(Checkin::count())->toBe(0)
        ->and(ExternalEvent::count())->toBe(0)
        ->and(checkinControlValue($this->user, 'checkins_this_stream'))->toBe('0')
        ->and(checkinControlValue($this->user, 'latest_checkin_name'))->toBe('');
});

test('the offline refusal sits behind the per-viewer cooldown', function () {
    connectCheckin($this->user);
    checkinLiveState($this->user, StreamState::STATE_OFFLINE);

    postCheckin();
    $second = postCheckin();

    expect($second->json('reply'))->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Replies
// ──────────────────────────────────────────────────────────────────────────────

test('empty args get a usage reply and store nothing', function () {
    connectCheckin($this->user);

    $response = postCheckin(['args' => '']);

    expect($response->json('reply'))->toContain('!checkin')
        ->and(Checkin::count())->toBe(0)
        ->and(ExternalEvent::count())->toBe(0);
});

test('an unresolvable place gets a friendly miss and stores nothing', function () {
    connectCheckin($this->user);

    $response = postCheckin(['args' => 'xyzzyplugh']);

    expect($response->json('reply'))->toContain("Couldn't find")
        ->and(Checkin::count())->toBe(0)
        ->and(ExternalEvent::count())->toBe(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// The happy path
// ──────────────────────────────────────────────────────────────────────────────

test('a checkin pins the viewer, stores the event, and updates controls', function () {
    Event::fake([CheckinsUpdated::class]);
    connectCheckin($this->user);

    $response = postCheckin();

    expect($response->json('reply'))->toContain('ViewerOne')
        ->and($response->json('reply'))->toContain('Rotterdam, NL');

    $pin = Checkin::where('user_id', $this->user->id)->first();
    expect($pin)->not->toBeNull()
        ->and($pin->place_label)->toBe('Rotterdam, NL')
        ->and($pin->country_code)->toBe('NL')
        ->and($pin->distance_km)->toBeNull();

    expect(ExternalEvent::where('service', 'checkin')->count())->toBe(1)
        ->and(checkinControlValue($this->user, 'latest_checkin_name'))->toBe('ViewerOne')
        ->and(checkinControlValue($this->user, 'latest_checkin_place'))->toBe('Rotterdam, NL')
        ->and(checkinControlValue($this->user, 'checkins_this_stream'))->toBe('1')
        ->and(checkinControlValue($this->user, 'checkins_total'))->toBe('1')
        ->and(checkinControlValue($this->user, 'unique_countries_this_stream'))->toBe('1');

    Event::assertDispatched(CheckinsUpdated::class, function (CheckinsUpdated $event) {
        return $event->pin !== null
            && $event->pin['place'] === 'Rotterdam, NL'
            && $event->cleared === false;
    });

    $stored = ExternalEvent::where('service', 'checkin')->first();
    expect($stored->controls_updated)->toBeTrue();
});

test('a re-checkin moves the pin instead of adding one', function () {
    connectCheckin($this->user);

    postCheckin();
    Cache::flush(); // clear the cooldown, keep the pin
    postCheckin(['args' => 'Paris']);

    $pins = Checkin::where('user_id', $this->user->id)->get();
    expect($pins)->toHaveCount(1)
        ->and($pins->first()->place_label)->toBe('Paris, FR')
        ->and(checkinControlValue($this->user, 'checkins_total'))->toBe('1');
});

test('a pin move during the same stream does not increment the per-stream counter', function () {
    connectCheckin($this->user);
    StreamSession::create(['user_id' => $this->user->id, 'started_at' => now()->subHour(), 'ended_at' => null]);

    postCheckin();
    Cache::flush();
    postCheckin(['args' => 'Amsterdam']);

    expect(checkinControlValue($this->user, 'checkins_this_stream'))->toBe('1');
});

test('the per-viewer cooldown silences repeat checkins', function () {
    connectCheckin($this->user);

    postCheckin();
    $second = postCheckin(['args' => 'Paris']);

    expect($second->json('reply'))->toBeNull()
        ->and(Checkin::first()->place_label)->toBe('Rotterdam, NL')
        ->and(ExternalEvent::count())->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Distance and aggregates
// ──────────────────────────────────────────────────────────────────────────────

test('a home location gives checkins a distance', function () {
    connectCheckin($this->user, [
        'home_place_label' => 'Amsterdam, NL',
        'home_lat' => 52.37403,
        'home_lng' => 4.88969,
    ]);

    $response = postCheckin(['args' => 'Paris']);

    $pin = Checkin::first();
    // Amsterdam to Paris is ~430 km as the haversine flies. The reply must
    // speak the STORED value (one decimal), not a rounded opinion of it -
    // the overlay's |distance:km shows 430.2, so the bot says 430.2.
    expect($pin->distance_km)->toBeGreaterThan(400)->toBeLessThan(460)
        ->and($response->json('reply'))->toContain('km away')
        ->and($response->json('reply'))->toContain((string) $pin->distance_km)
        ->and((float) checkinControlValue($this->user, 'latest_checkin_distance'))->toBeGreaterThan(400)
        ->and((float) checkinControlValue($this->user, 'farthest_checkin_this_stream'))->toBeGreaterThan(400);
});

test('farthest checkin only ever moves up', function () {
    connectCheckin($this->user, [
        'home_place_label' => 'Amsterdam, NL',
        'home_lat' => 52.37403,
        'home_lng' => 4.88969,
    ]);

    postCheckin(['args' => 'Paris']);
    $farthest = (float) checkinControlValue($this->user, 'farthest_checkin_this_stream');

    postCheckin(['chatter_id' => '222', 'chatter_login' => 'viewer_two', 'chatter_display_name' => 'ViewerTwo', 'args' => 'Rotterdam, NL']);

    // Rotterdam is closer to Amsterdam than Paris; the record stands.
    expect((float) checkinControlValue($this->user, 'farthest_checkin_this_stream'))->toBe($farthest);
});

test('unique countries counts countries, not checkins', function () {
    connectCheckin($this->user);

    postCheckin();
    postCheckin(['chatter_id' => '222', 'chatter_login' => 'viewer_two', 'chatter_display_name' => 'ViewerTwo', 'args' => 'Amsterdam']);
    postCheckin(['chatter_id' => '333', 'chatter_login' => 'viewer_three', 'chatter_display_name' => 'ViewerThree', 'args' => 'Paris']);

    expect(checkinControlValue($this->user, 'unique_countries_this_stream'))->toBe('2');
});

test('a same-second re-checkin dedups the event but still moves the pin', function () {
    connectCheckin($this->user);

    $this->travelTo(now());

    postCheckin();
    Cache::flush();
    postCheckin(['args' => 'Paris']);

    expect(ExternalEvent::where('service', 'checkin')->count())->toBe(1)
        ->and(Checkin::first()->place_label)->toBe('Paris, FR');
});

// ──────────────────────────────────────────────────────────────────────────────
// Go-live reset
// ──────────────────────────────────────────────────────────────────────────────

test('going live resets the per-stream checkin controls and clears per-stream globes', function () {
    Event::fake([CheckinsUpdated::class]);
    connectCheckin($this->user);

    postCheckin();
    expect(checkinControlValue($this->user, 'checkins_this_stream'))->toBe('1');

    app(StreamSessionService::class)->openSession($this->user);

    expect(checkinControlValue($this->user, 'checkins_this_stream'))->toBe('0')
        ->and(checkinControlValue($this->user, 'unique_countries_this_stream'))->toBe('0')
        ->and(checkinControlValue($this->user, 'farthest_checkin_this_stream'))->toBe('0')
        // latest_* persist across streams - the latest_cheer* rule.
        ->and(checkinControlValue($this->user, 'latest_checkin_place'))->toBe('Rotterdam, NL');

    Event::assertDispatched(CheckinsUpdated::class, fn (CheckinsUpdated $e) => $e->cleared && $e->count === 0);
});

test('going live does not clear globes in persistent mode', function () {
    Event::fake([CheckinsUpdated::class]);
    connectCheckin($this->user, ['pin_lifetime' => 'persistent']);

    app(StreamSessionService::class)->openSession($this->user);

    Event::assertNotDispatched(CheckinsUpdated::class, fn (CheckinsUpdated $e) => $e->cleared);
});

test('the go-live reset forgets the country set so the counter can restart', function () {
    connectCheckin($this->user);

    postCheckin(['args' => 'Paris']);
    expect(checkinControlValue($this->user, 'unique_countries_this_stream'))->toBe('1');

    app(StreamSessionService::class)->openSession($this->user);

    // One country after the reset - not last stream's total carried by the set.
    postCheckin(['chatter_id' => '222', 'chatter_login' => 'viewer_two', 'chatter_display_name' => 'ViewerTwo', 'args' => 'Amsterdam']);
    expect(checkinControlValue($this->user, 'unique_countries_this_stream'))->toBe('1');
});
