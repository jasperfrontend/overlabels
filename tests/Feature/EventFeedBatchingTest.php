<?php

use App\Events\ListUpdated;
use App\Events\TwitchEventReceived;
use App\Http\Controllers\TwitchEventSubController;
use App\Jobs\FlushEventFeed;
use App\Jobs\ProcessTwitchEvent;
use App\Models\OptionSet;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\Lists\EventFeedService;
use App\Support\ListItems;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * The recent-events feed on the Twitch event lane writes once per window, not
 * once per event (#343). In a 1,000-follow flood the per-event feed write and
 * its ListUpdated broadcast were 5.5 of a follow's 16.8 ms on the lane, plus a
 * second lookup of the broadcaster in EventFeedAppender.
 */
beforeEach(function () {
    config(['services.twitch.client_id' => 'test-client-id']);
    Http::fake(['*' => Http::response(['data' => []])]);
    Queue::fake([FlushEventFeed::class]);
});

function batchUser(): User
{
    return User::factory()->create(['access_token' => 'token', 'twitch_id' => (string) fake()->unique()->randomNumber(9)]);
}

function batchFeed(User $user, array $types = [], string $slug = 'recent_feed'): OptionSet
{
    return OptionSet::create([
        'user_id' => $user->id,
        'slug' => $slug,
        'items' => [],
        'next_item_id' => 1,
        'min_items' => 0,
        'user_editable' => true,
        'event_feed' => ['enabled' => true, 'types' => $types],
    ]);
}

function batchOnLane(User $user, string $name, string $type = 'channel.follow'): void
{
    app(EventFeedService::class)->handleTwitchEventOnLane($user, $type, ['user_name' => $name, 'bits' => 10]);
}

function batchFlush(User $user): void
{
    (new FlushEventFeed($user->id, (string) $user->twitch_id))->handle(app(EventFeedService::class));
}

it('writes the first event of a quiet window straight away', function () {
    $user = batchUser();
    $list = batchFeed($user);

    batchOnLane($user, 'Alice');

    expect(ListItems::values($list->fresh()->items))->toBe(['Alice followed']);
    Queue::assertNotPushed(FlushEventFeed::class);
});

it('holds the rest of the window and writes them in order in one go', function () {
    Event::fake([ListUpdated::class]);
    $user = batchUser();
    $list = batchFeed($user);

    batchOnLane($user, 'Alice');
    batchOnLane($user, 'Bob');
    batchOnLane($user, 'Carol');

    expect(ListItems::values($list->fresh()->items))->toBe(['Alice followed']);
    Queue::assertPushed(FlushEventFeed::class, 1);

    batchFlush($user);

    expect(ListItems::values($list->fresh()->items))->toBe(['Alice followed', 'Bob followed', 'Carol followed'])
        ->and(collect($list->fresh()->items)->pluck('id')->all())->toBe([1, 2, 3])
        ->and($list->fresh()->next_item_id)->toBe(4);
    Event::assertDispatchedTimes(ListUpdated::class, 2);
});

it('lets the next event after the window write what was waiting, before itself', function () {
    $user = batchUser();
    $list = batchFeed($user);

    batchOnLane($user, 'Alice');
    batchOnLane($user, 'Bob');
    $this->travel(EventFeedService::BATCH_WINDOW_SECONDS + 1)->seconds();
    batchOnLane($user, 'Carol');

    expect(ListItems::values($list->fresh()->items))->toBe(['Alice followed', 'Bob followed', 'Carol followed']);

    // The trailing flush then finds nothing left and writes nothing.
    batchFlush($user);
    expect(ListItems::values($list->fresh()->items))->toHaveCount(3);
});

it('gives each list only the event types it accepts', function () {
    $user = batchUser();
    $follows = batchFeed($user, ['channel.follow'], 'follows');
    $cheers = batchFeed($user, ['channel.cheer'], 'cheers');

    batchOnLane($user, 'Alice');
    batchOnLane($user, 'Bob', 'channel.cheer');
    batchOnLane($user, 'Carol');
    batchFlush($user);

    expect(ListItems::values($follows->fresh()->items))->toBe(['Alice followed', 'Carol followed'])
        ->and(ListItems::values($cheers->fresh()->items))->toBe(['Bob cheered 10 bits']);
});

it('keeps the FIFO cap when a batch is bigger than the list', function () {
    $user = batchUser();
    $list = batchFeed($user);
    $list->update(['max_items' => 2]);

    batchOnLane($user, 'One');
    batchOnLane($user, 'Two');
    batchOnLane($user, 'Three');
    batchFlush($user);

    expect(ListItems::values($list->fresh()->items))->toBe(['Two followed', 'Three followed']);
});

it('does nothing for a streamer without a feed', function () {
    $user = batchUser();

    batchOnLane($user, 'Alice');
    batchOnLane($user, 'Bob');

    Queue::assertNotPushed(FlushEventFeed::class);
});

it('runs the trailing flush on the streamer\'s own lane', function () {
    $user = batchUser();

    expect((new FlushEventFeed($user->id, (string) $user->twitch_id))->queue)
        ->toBe(ProcessTwitchEvent::queueFor((string) $user->twitch_id));
});

it('a processed follow lands in the feed once, not again through the listener', function () {
    $user = batchUser();
    $list = batchFeed($user);
    $event = ['broadcaster_user_id' => $user->twitch_id, 'user_id' => '42', 'user_login' => 'viewer', 'user_name' => 'Viewer'];
    $row = TwitchEvent::create(['user_id' => $user->id, 'event_type' => 'channel.follow', 'event_data' => $event, 'twitch_timestamp' => now(), 'processed' => false]);

    app(TwitchEventSubController::class)->processStoredEvent($row, ['subscription' => ['type' => 'channel.follow'], 'event' => $event]);

    expect(ListItems::values($list->fresh()->items))->toBe(['Viewer followed']);
});

it('an event the lane did not handle still reaches the feed through the listener', function () {
    $user = batchUser();
    $list = batchFeed($user);

    TwitchEventReceived::dispatch('channel.follow', ['user_name' => 'Dave'], (string) $user->twitch_id);

    expect(ListItems::values($list->fresh()->items))->toBe(['Dave followed']);
});

it('keeps the handled flag out of the broadcast payload', function () {
    $received = (new TwitchEventReceived('channel.follow', ['user_name' => 'Eve'], '123'))->markFeedHandled();

    $payload = (fn () => $this->getPayloadFromEvent($received))->call(new BroadcastEvent($received));

    expect($payload)->toHaveKeys(['eventType', 'eventData', 'broadcasterId', 'timestamp'])
        ->not->toHaveKey('feedHandled');
});
