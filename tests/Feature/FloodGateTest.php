<?php

use App\Enums\DeliveryOutcome;
use App\Events\AlertTriggered;
use App\Events\TwitchEventReceived;
use App\Jobs\FloodTick;
use App\Jobs\ForgetTwitchCache;
use App\Jobs\ProcessTwitchEvent;
use App\Jobs\SyncLivingTitle;
use App\Models\EventTemplateMapping;
use App\Models\OptionSet;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\StreamState;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\Alerts\AlertBursts;
use App\Services\Floods\FloodGate;
use App\Services\Lists\RecentEventFormatter;
use App\Services\TwitchApiService;
use App\Support\ListItems;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * Floods become numbers (#346): past the first FloodGate::PASS_THROUGH of one
 * burst, a follow or a redemption is counted at intake, never stored or
 * queued, and applied on the lane in one go every tick. When the flood is over
 * the last FloodGate::KEPT become rows and one summary row holds the rest.
 *
 * Driven through the real front desk batch endpoint. FloodTick and
 * ProcessTwitchEvent are faked; a tick is run by hand, which is "the lane, two
 * seconds later".
 */
const FLOOD_DESK_SECRET = 'flood-desk-secret';
const FLOOD_TWITCH_SECRET = 'flood-webhook-secret';

beforeEach(function () {
    config([
        'services.twitch.front_desk_secret' => FLOOD_DESK_SECRET,
        'app.twitch_webhook_secret' => FLOOD_TWITCH_SECRET,
    ]);
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($token, $event) => $event);
        $mock->shouldIgnoreMissing();
    });
    Queue::fake([ProcessTwitchEvent::class, FloodTick::class]);
    Event::fake([TwitchEventReceived::class, AlertTriggered::class]);
});

function floodMessage(string $id, string $type, array $event): array
{
    $body = json_encode(['subscription' => ['id' => 'sub-1', 'type' => $type, 'version' => '1'], 'event' => $event]);
    $timestamp = now()->toIso8601ZuluString();

    return ['id' => $id, 'timestamp' => $timestamp, 'signature' => 'sha256='.hash_hmac('sha256', $id.$timestamp.$body, FLOOD_TWITCH_SECRET), 'body' => $body];
}

/**
 * Send $count follows (or $type events) in batches of 100, as the front desk does.
 *
 * @return array<string, string> every message's result
 */
function floodSend(User $user, int $count, string $type = 'channel.follow', int $from = 0, array $extra = []): array
{
    $results = [];
    foreach (array_chunk(range($from, $from + $count - 1), 100) as $chunk) {
        $messages = array_map(fn (int $i) => floodMessage("flood-msg-{$type}-{$i}", $type, [
            'broadcaster_user_id' => (string) $user->twitch_id, 'broadcaster_user_login' => 'host', 'broadcaster_user_name' => 'Host',
            'user_id' => (string) (5000000 + $i), 'user_login' => "viewer{$i}", 'user_name' => "Viewer{$i}",
            ...$extra,
        ]), $chunk);

        $response = test()->call('POST', '/api/twitch/webhook/batch', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_FRONT_DESK_SECRET' => FLOOD_DESK_SECRET,
        ], json_encode(['messages' => $messages]))->assertOk();

        $results += $response->json('results');
    }

    return $results;
}

/** Run the queued ticks, as the lane would every two seconds. */
function floodTick(): void
{
    $jobs = Queue::pushed(FloodTick::class);
    Queue::fake([ProcessTwitchEvent::class, FloodTick::class]);
    foreach ($jobs as $job) {
        app()->call([$job, 'handle']);
    }
}

/** Let the flood end at intake and run ticks until it is finished. */
function floodFinish(): void
{
    test()->travel(FloodGate::KEEP_OPEN_SECONDS + 1)->seconds();
    for ($i = 0; $i < 3 && Queue::pushed(FloodTick::class)->isNotEmpty(); $i++) {
        floodTick();
    }
}

function floodSummary(User $user): ?TwitchEvent
{
    return TwitchEvent::where('user_id', $user->id)->where('outcome', DeliveryOutcome::FloodCounted)->first();
}

function floodUser(): User
{
    return User::factory()->create(['access_token' => 'token', 'twitch_id' => (string) fake()->unique()->randomNumber(8)]);
}

test('the first hundred of a flood are stored and queued, the rest are counted and acked', function () {
    $user = floodUser();

    $results = floodSend($user, 300);

    expect(array_unique(array_values($results)))->toBe(['stored'])
        ->and(TwitchEvent::where('user_id', $user->id)->count())->toBe(FloodGate::PASS_THROUGH);
    Queue::assertPushed(ProcessTwitchEvent::class, FloodGate::PASS_THROUGH);
    Queue::assertPushed(FloodTick::class, 1);
});

test('a tick applies the count in one go: one summary row, one overlay broadcast with the newest follower', function () {
    $user = floodUser();
    floodSend($user, 300);

    floodTick();

    $summary = floodSummary($user);
    expect($summary->event_data['flood_count'])->toBe(200)
        ->and($summary->event_type)->toBe('channel.follow');
    Event::assertDispatched(TwitchEventReceived::class, fn (TwitchEventReceived $e) => $e->eventData['flood_count'] === 200
        && $e->eventData['user_name'] === 'Viewer299'
        && str_starts_with($e->eventData['message_id'], 'flood-')
        && $e->feedHandled());
    Queue::assertPushed(FloodTick::class, 1); // still flooding: it comes back
});

test('the per-stream follow counter moves by the whole count', function () {
    $user = floodUser();
    StreamState::updateOrCreate(['user_id' => $user->id], ['state' => StreamState::STATE_LIVE, 'confidence' => 1.0]);
    $control = OverlayControl::create([
        'user_id' => $user->id, 'overlay_template_id' => null, 'key' => 'follows_this_stream', 'label' => 'Follows',
        'type' => 'counter', 'value' => '7', 'source' => 'twitch', 'source_managed' => true,
    ]);

    floodSend($user, 250);
    floodTick();

    expect((float) $control->fresh()->value)->toBe(157.0);
});

test('when the flood ends, the last hundred become rows and the summary holds the rest', function () {
    $user = floodUser();
    floodSend($user, 1000);
    floodTick();

    floodFinish();

    $rows = TwitchEvent::where('user_id', $user->id);
    expect(floodSummary($user)->event_data['flood_count'])->toBe(800)
        ->and((clone $rows)->where('outcome', DeliveryOutcome::BurstAbsorbed)->count())->toBe(FloodGate::KEPT)
        ->and((clone $rows)->where('outcome', DeliveryOutcome::BurstAbsorbed)->orderByDesc('id')->first()->event_data['user_name'])->toBe('Viewer999')
        // 100 passed through + 100 kept + 800 counted = every follow, once.
        ->and((clone $rows)->whereNull('outcome')->count() + FloodGate::KEPT + 800)->toBe(1000);
    Queue::assertNothingPushed();
});

test('a flood small enough to keep whole leaves no summary row', function () {
    $user = floodUser();
    floodSend($user, 150);
    floodTick();

    floodFinish();

    expect(floodSummary($user))->toBeNull()
        ->and(TwitchEvent::where('user_id', $user->id)->count())->toBe(150);
});

test('a redelivered counted follow is a duplicate and is not counted twice', function () {
    $user = floodUser();
    floodSend($user, 300);

    $again = floodSend($user, 50, from: 250);
    floodTick();

    expect(array_unique(array_values($again)))->toBe(['duplicate'])
        ->and(floodSummary($user)->event_data['flood_count'])->toBe(200);
});

test('subs are never counted away', function () {
    $user = floodUser();

    floodSend($user, 250, 'channel.subscribe', extra: ['tier' => '1000', 'is_gift' => false]);

    expect(TwitchEvent::where('user_id', $user->id)->count())->toBe(250);
    Queue::assertNotPushed(FloodTick::class);
});

test('redemptions flood per reward', function () {
    $user = floodUser();
    $reward = fn (string $id) => ['reward' => ['id' => $id, 'title' => "Reward {$id}", 'cost' => 100, 'prompt' => ''], 'id' => fake()->uuid()];
    $type = 'channel.channel_points_custom_reward_redemption.add';

    floodSend($user, 150, $type, 0, $reward('a'));
    floodSend($user, 90, $type, 1000, $reward('b'));

    expect(TwitchEvent::where('user_id', $user->id)->count())->toBe(190);
    Queue::assertPushed(FloodTick::class, fn (FloodTick $t) => $t->kind === "{$type}:a");
    Queue::assertNotPushed(FloodTick::class, fn (FloodTick $t) => $t->kind === "{$type}:b");
});

test('a flood keeps a long burst announcing as it doubles', function () {
    $user = floodUser();
    $alert = OverlayTemplate::factory()->create(['owner_id' => $user->id, 'type' => 'alert', 'html' => '<div>[[[event.burst_count]]]</div>', 'template_tags' => ['event.burst_count']]);
    EventTemplateMapping::create(['user_id' => $user->id, 'event_type' => 'channel.follow', 'template_id' => $alert->id, 'duration_ms' => 6000, 'enabled' => true]);
    // An open burst announced at 2, long enough ago to be due again once it doubles.
    Cache::put("alert-burst:{$user->id}:channel.follow", [
        'phase' => 'open', 'count' => 2, 'started_at' => now()->subSeconds(30)->timestamp, 'actors' => [],
        'announced' => 2, 'announced_at' => now()->subSeconds(AlertBursts::REANNOUNCE_MIN_SECONDS)->timestamp,
    ], AlertBursts::KEEP_OPEN_SECONDS);

    floodSend($user, 300);
    floodTick();

    $alerts = Event::dispatched(AlertTriggered::class)->map(fn ($e) => $e[0]->broadcastWith()['alert']['data']['event.burst_count'] ?? null)->all();
    expect($alerts)->toBe([202])
        // The announcing follow became a row of its own and left the count.
        ->and(floodSummary($user)->event_data['flood_count'])->toBe(199);
});

test('the flood line in a recent-events feed is one number', function () {
    $user = floodUser();
    $list = OptionSet::create([
        'user_id' => $user->id, 'slug' => 'recent_feed', 'items' => [], 'next_item_id' => 1, 'min_items' => 0,
        'user_editable' => true, 'event_feed' => ['enabled' => true, 'types' => ['channel.follow']],
    ]);

    floodSend($user, 1000);
    floodTick();
    floodFinish();

    expect(ListItems::values($list->fresh()->items))->toBe(['+800 more follows']);
});

test('the formatter writes a summary row as one number', function () {
    $f = app(RecentEventFormatter::class);

    expect($f->format('twitch', 'channel.follow', ['flood_count' => 5400], null))->toBe('+5,400 more follows')
        ->and($f->format('twitch', 'channel.channel_points_custom_reward_redemption.add', ['flood_count' => 12, 'reward' => ['title' => 'Hydrate']], null))
        ->toBe('+12 more redemptions: Hydrate');
});

test('a tick refreshes the follower caches and schedules the living title, once', function () {
    Queue::fake([ProcessTwitchEvent::class, FloodTick::class, ForgetTwitchCache::class, SyncLivingTitle::class]);
    $user = floodUser();
    $user->forceFill(['preferences' => ['living_title' => ['enabled' => true, 'template' => '[[[followers_total]]] followers']]])->save();

    floodSend($user, 300);
    // The 100 passed-through follows never ran (ProcessTwitchEvent is faked), so only the tick acts.
    // Run by hand: floodTick() re-fakes the queue and would drop the two fakes above.
    foreach (Queue::pushed(FloodTick::class) as $job) {
        app()->call([$job, 'handle']);
    }

    Queue::assertPushed(ForgetTwitchCache::class, fn (ForgetTwitchCache $job) => $job->cache === 'channel_followers' && $job->broadcasterId === (string) $user->twitch_id);
    Queue::assertPushed(SyncLivingTitle::class, 1);
});
