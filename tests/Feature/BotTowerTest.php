<?php

use App\Events\ListUpdated;
use App\Events\TowerUpdated;
use App\Models\ExternalEvent;
use App\Models\ExternalIntegration;
use App\Models\OptionSet;
use App\Models\OverlayControl;
use App\Models\StreamState;
use App\Models\TowerBlock;
use App\Models\User;
use App\Services\External\ExternalControlService;
use App\Services\External\ExternalServiceRegistry;
use App\Services\StreamSessionService;
use App\Services\Tower\TowerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

uses(DatabaseTransactions::class);

const TOWER_URL = '/api/internal/bot/tower/towerstreamer';

beforeEach(function () {
    config(['services.twitchbot.listener_secret' => 'test-bot-secret']);
    Cache::flush();

    $this->user = User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => 'towerstreamer'],
    ]);
});

function towerLiveState(User $user, string $state): void
{
    StreamState::updateOrCreate(
        ['user_id' => $user->id],
        ['state' => $state, 'confidence' => 1.0],
    );
}

function connectTower(User $user, array $settings = []): ExternalIntegration
{
    $integration = ExternalIntegration::create([
        'user_id' => $user->id,
        'service' => 'tower',
        'enabled' => true,
        'settings' => array_merge(['tower_lifetime' => 'per_stream', 'cooldown_seconds' => 30], $settings),
    ]);

    app(ExternalControlService::class)->provision($user, ExternalServiceRegistry::driver('tower'));

    // The command is gated on isConfidentlyLive, so a connected channel in
    // these tests is a LIVE channel; the offline tests flip the state back.
    towerLiveState($user, StreamState::STATE_LIVE);

    return $integration;
}

function postStack(array $overrides = []): TestResponse
{
    return test()->postJson(TOWER_URL, array_merge([
        'chatter_id' => '111',
        'chatter_login' => 'viewer_one',
        'chatter_display_name' => 'ViewerOne',
        'chatter_color' => '#ff0000',
        'action' => 'stack',
        'args' => '',
    ], $overrides), ['X-Internal-Secret' => 'test-bot-secret']);
}

function towerControlValue(User $user, string $key): string
{
    return (string) OverlayControl::where('user_id', $user->id)
        ->where('source', 'tower')
        ->where('key', $key)
        ->value('value');
}

function setTowerControl(User $user, string $key, string $value): void
{
    OverlayControl::where('user_id', $user->id)
        ->where('source', 'tower')
        ->where('key', $key)
        ->update(['value' => $value]);
}

/**
 * Stand a tower of `$height` blocks whose top sits at `$topX`, every block
 * placed by a different viewer so the roster tests have names to count.
 */
function standTower(User $user, int $height, float $topX = 0.0, bool $topIsRecord = false): void
{
    for ($i = 1; $i <= $height; $i++) {
        TowerBlock::create([
            'user_id' => $user->id,
            'position' => $i,
            'chatter_twitch_id' => (string) (1000 + $i),
            'chatter_login' => "builder_{$i}",
            'chatter_display_name' => "Builder{$i}",
            'color' => null,
            'offset' => $i === $height ? $topX : 0.0,
            'x' => $i === $height ? $topX : 0.0,
            'record' => $topIsRecord && $i === $height,
            'placed_at' => now()->subMinutes($height - $i + 1),
        ]);
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// Auth, routing, gating
// ──────────────────────────────────────────────────────────────────────────────

test('stack returns 403 without the internal secret', function () {
    $this->postJson(TOWER_URL, ['chatter_id' => '1'])->assertStatus(403);
});

test('stack returns 404 for an unknown channel', function () {
    $this->postJson('/api/internal/bot/tower/nobody', [
        'chatter_id' => '111',
        'chatter_login' => 'viewer_one',
        'chatter_display_name' => 'ViewerOne',
    ], ['X-Internal-Secret' => 'test-bot-secret'])->assertStatus(404);
});

test('stack stays silent when the integration is not connected', function () {
    postStack()->assertOk()->assertJson(['reply' => null]);

    expect(TowerBlock::count())->toBe(0);
});

test('stack is refused while the stream is offline, with a reply saying so', function () {
    connectTower($this->user);
    towerLiveState($this->user, StreamState::STATE_OFFLINE);

    $response = postStack();

    expect($response->json('reply'))->toContain('live')
        ->and(TowerBlock::count())->toBe(0)
        ->and(ExternalEvent::count())->toBe(0)
        ->and(towerControlValue($this->user, 'tower_height'))->toBe('0')
        ->and(towerControlValue($this->user, 'blocks_stacked_this_stream'))->toBe('0');
});

test('a second stack by the same viewer inside the cooldown is silently dropped', function () {
    connectTower($this->user, ['cooldown_seconds' => 30]);

    postStack()->assertOk();
    postStack(['args' => 'left'])->assertOk()->assertJson(['reply' => null]);

    expect(TowerBlock::count())->toBe(1);

    postStack(['chatter_id' => '222', 'chatter_login' => 'viewer_two', 'chatter_display_name' => 'ViewerTwo'])->assertOk();

    expect(TowerBlock::count())->toBe(2);
});

// The backstop used to floor every cooldown at 5 seconds, so a streamer who
// asked for a faster game silently got a slower one. 1 is now the floor.
test('a one-second cooldown lets the same viewer stack again after one second', function () {
    connectTower($this->user, ['cooldown_seconds' => 1]);

    postStack()->assertOk();
    postStack()->assertOk()->assertJson(['reply' => null]);

    expect(TowerBlock::count())->toBe(1);

    $this->travel(2)->seconds();

    postStack()->assertOk();

    expect(TowerBlock::count())->toBe(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// Stacking
// ──────────────────────────────────────────────────────────────────────────────

test('the first stack lays block one, moves the controls, stores the event and is silent', function () {
    Event::fake([TowerUpdated::class]);
    connectTower($this->user);

    postStack()->assertOk()->assertJson(['reply' => null]);

    $block = TowerBlock::where('user_id', $this->user->id)->first();

    expect($block->position)->toBe(1)
        ->and($block->chatter_login)->toBe('viewer_one')
        ->and($block->color)->toBe('#FF0000')
        ->and(abs($block->offset))->toBeLessThanOrEqual(1.0)
        ->and($block->x)->toBe($block->offset)
        ->and(towerControlValue($this->user, 'tower_height'))->toBe('1')
        ->and(towerControlValue($this->user, 'tower_lean'))->toBe((string) $block->x)
        ->and(towerControlValue($this->user, 'last_stacker_name'))->toBe('ViewerOne')
        ->and(towerControlValue($this->user, 'blocks_stacked_this_stream'))->toBe('1')
        ->and(towerControlValue($this->user, 'blocks_stacked_total'))->toBe('1')
        ->and(towerControlValue($this->user, 'tallest_tower_this_stream'))->toBe('1')
        // The record moves from zero quietly - the first block of an account
        // with no record yet is not news.
        ->and(towerControlValue($this->user, 'tallest_tower_record'))->toBe('1')
        ->and(ExternalEvent::where('service', 'tower')->where('event_type', 'stack')->count())->toBe(1);

    Event::assertDispatched(TowerUpdated::class, fn (TowerUpdated $e) => $e->height === 1
        && $e->cleared === false
        && $e->block['position'] === '1'
        && $e->block['login'] === 'viewer_one');
});

test('an aimed stack lands on the aimed side, an unknown word is an unaimed stack', function () {
    connectTower($this->user, ['cooldown_seconds' => 5]);

    postStack(['chatter_id' => '1', 'args' => 'left'])->assertOk();
    postStack(['chatter_id' => '2', 'args' => 'r'])->assertOk();
    postStack(['chatter_id' => '3', 'args' => 'sideways'])->assertOk();

    [$left, $right, $unaimed] = TowerBlock::where('user_id', $this->user->id)->orderBy('position')->get();

    expect($left->offset)->toBeLessThanOrEqual(-0.45)
        ->and($left->offset)->toBeGreaterThanOrEqual(-1.6)
        ->and($right->offset)->toBeGreaterThanOrEqual(0.45)
        ->and($right->offset)->toBeLessThanOrEqual(1.6)
        ->and(abs($unaimed->offset))->toBeLessThanOrEqual(1.0)
        // x is the running sum: the lean is where the top block ended up.
        ->and($unaimed->x)->toBe(round($left->offset + $right->offset + $unaimed->offset, 3));
});

test('a chat colour that is not #RRGGBB is dropped rather than stored', function () {
    connectTower($this->user);

    postStack(['chatter_color' => 'red'])->assertOk();

    expect(TowerBlock::first()->color)->toBeNull();
});

test('every tenth block gets a bot line', function () {
    connectTower($this->user);
    standTower($this->user, 9);

    $reply = postStack()->assertOk()->json('reply');

    expect($reply)->toContain('10 high');
});

// ──────────────────────────────────────────────────────────────────────────────
// Toppling
// ──────────────────────────────────────────────────────────────────────────────

test('a block that crosses the fall line brings the tower down and names the viewer', function () {
    Event::fake([TowerUpdated::class]);
    connectTower($this->user);
    setTowerControl($this->user, 'tallest_tower_this_stream', '9');
    setTowerControl($this->user, 'tallest_tower_record', '20');
    // Nine blocks, top at 3.4: sway at ten is 0.55, so any aimed-right block
    // (at least 0.45 out) puts the top of the sway past 4.
    standTower($this->user, 9, 3.4);

    $reply = postStack(['args' => 'right'])->assertOk()->json('reply');

    expect($reply)->toContain('fell at 10')
        ->and($reply)->toContain('ViewerOne placed the last block')
        ->and($reply)->toContain('Record stands at 20')
        ->and(TowerBlock::where('user_id', $this->user->id)->count())->toBe(0)
        ->and(towerControlValue($this->user, 'tower_height'))->toBe('0')
        ->and(towerControlValue($this->user, 'tower_lean'))->toBe('0')
        ->and(towerControlValue($this->user, 'last_topple_by'))->toBe('ViewerOne')
        ->and(towerControlValue($this->user, 'last_topple_height'))->toBe('10')
        ->and(towerControlValue($this->user, 'topples_this_stream'))->toBe('1')
        ->and(towerControlValue($this->user, 'blocks_stacked_this_stream'))->toBe('1')
        // Only a standing tower counts: the block that fell raised nothing.
        ->and(towerControlValue($this->user, 'tallest_tower_this_stream'))->toBe('9')
        ->and(towerControlValue($this->user, 'tallest_tower_record'))->toBe('20')
        ->and(ExternalEvent::where('service', 'tower')->where('event_type', 'topple')->count())->toBe(1);

    Event::assertDispatched(TowerUpdated::class, fn (TowerUpdated $e) => $e->cleared === true
        && $e->height === 0
        && $e->toppled['height'] === 10
        && $e->toppled['login'] === 'viewer_one'
        && $e->block['position'] === '10');
});

test('toppling the record tower says so', function () {
    connectTower($this->user);
    setTowerControl($this->user, 'tallest_tower_record', '9');
    // The top block is a record block: this tower set the record as it stood.
    standTower($this->user, 9, 3.4, topIsRecord: true);

    $reply = postStack(['args' => 'right'])->assertOk()->json('reply');

    expect($reply)->toContain('That tower was the record.');
});

// ──────────────────────────────────────────────────────────────────────────────
// The record and its roster
// ──────────────────────────────────────────────────────────────────────────────

test('the first block past the record announces it and rewrites the roster list', function () {
    Event::fake([TowerUpdated::class, ListUpdated::class]);
    connectTower($this->user, ['cooldown_seconds' => 5]);
    setTowerControl($this->user, 'tallest_tower_record', '2');
    OptionSet::create([
        'user_id' => $this->user->id,
        'slug' => TowerService::RECORD_LIST_SLUG,
        'label' => 'Tallest tower',
        'items' => [],
        'next_item_id' => 1,
    ]);
    standTower($this->user, 2);

    $reply = postStack()->assertOk()->json('reply');

    expect($reply)->toContain('NEW RECORD')
        ->and($reply)->toContain('3 high')
        ->and($reply)->toContain('old record was 2')
        ->and(towerControlValue($this->user, 'tallest_tower_record'))->toBe('3');

    $roster = OptionSet::where('user_id', $this->user->id)->where('slug', TowerService::RECORD_LIST_SLUG)->first();

    expect(array_column($roster->items, 'value'))->toBe(['Builder1', 'Builder2', 'ViewerOne']);
    Event::assertDispatched(ListUpdated::class);

    // The next block keeps raising the record quietly, and a repeat builder
    // is one roster entry, not two.
    $reply = postStack(['chatter_id' => '1001', 'chatter_login' => 'builder_1', 'chatter_display_name' => 'Builder1'])->assertOk()->json('reply');

    expect($reply)->toBeNull()
        ->and(towerControlValue($this->user, 'tallest_tower_record'))->toBe('4')
        ->and(array_column($roster->fresh()->items, 'value'))->toBe(['Builder1', 'Builder2', 'ViewerOne']);
});

test('without a roster list the record height still moves', function () {
    connectTower($this->user);
    setTowerControl($this->user, 'tallest_tower_record', '1');
    standTower($this->user, 1);

    postStack()->assertOk();

    expect(towerControlValue($this->user, 'tallest_tower_record'))->toBe('2')
        ->and(OptionSet::where('user_id', $this->user->id)->count())->toBe(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// !tower
// ──────────────────────────────────────────────────────────────────────────────

test('tower status reads the tower and works offline', function () {
    connectTower($this->user);
    towerLiveState($this->user, StreamState::STATE_OFFLINE);

    $reply = postStack(['action' => 'status'])->assertOk()->json('reply');

    expect($reply)->toContain('No tower right now')
        ->and($reply)->toContain('No record yet');

    Cache::flush();
    setTowerControl($this->user, 'tallest_tower_record', '12');
    standTower($this->user, 3, 1.2);

    $reply = postStack(['action' => 'status'])->assertOk()->json('reply');

    expect($reply)->toContain('3 high')
        ->and($reply)->toContain('leaning right')
        ->and($reply)->toContain('Record 12')
        ->and(TowerBlock::count())->toBe(3);

    // A read, but still not free: the second ask inside the window is silent.
    postStack(['action' => 'status'])->assertOk()->assertJson(['reply' => null]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Go-live reset
// ──────────────────────────────────────────────────────────────────────────────

test('going live resets the per-stream tower counters and clears a per-stream tower', function () {
    Event::fake([TowerUpdated::class]);
    connectTower($this->user, ['tower_lifetime' => 'per_stream']);
    standTower($this->user, 3);
    setTowerControl($this->user, 'tower_height', '3');
    setTowerControl($this->user, 'blocks_stacked_this_stream', '7');
    setTowerControl($this->user, 'tallest_tower_this_stream', '9');
    setTowerControl($this->user, 'topples_this_stream', '2');
    setTowerControl($this->user, 'tallest_tower_record', '9');
    setTowerControl($this->user, 'last_topple_by', 'Builder2');

    app(StreamSessionService::class)->openSession($this->user);

    expect(TowerBlock::where('user_id', $this->user->id)->count())->toBe(0)
        ->and(towerControlValue($this->user, 'tower_height'))->toBe('0')
        ->and(towerControlValue($this->user, 'blocks_stacked_this_stream'))->toBe('0')
        ->and(towerControlValue($this->user, 'tallest_tower_this_stream'))->toBe('0')
        ->and(towerControlValue($this->user, 'topples_this_stream'))->toBe('0')
        ->and(towerControlValue($this->user, 'tallest_tower_record'))->toBe('9')
        ->and(towerControlValue($this->user, 'last_topple_by'))->toBe('Builder2');

    Event::assertDispatched(TowerUpdated::class, fn (TowerUpdated $e) => $e->cleared === true && $e->toppled === null);
});

test('a persistent tower survives going live while its counters still reset', function () {
    Event::fake([TowerUpdated::class]);
    connectTower($this->user, ['tower_lifetime' => 'persistent']);
    standTower($this->user, 3);
    setTowerControl($this->user, 'tower_height', '3');
    setTowerControl($this->user, 'blocks_stacked_this_stream', '7');

    app(StreamSessionService::class)->openSession($this->user);

    expect(TowerBlock::where('user_id', $this->user->id)->count())->toBe(3)
        ->and(towerControlValue($this->user, 'tower_height'))->toBe('3')
        ->and(towerControlValue($this->user, 'blocks_stacked_this_stream'))->toBe('0');

    Event::assertNotDispatched(TowerUpdated::class);
});
