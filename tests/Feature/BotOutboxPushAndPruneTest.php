<?php

use App\Events\BotOutboxPending;
use App\Models\BotChatOutbox;
use App\Models\BotExpression;
use App\Models\User;
use App\Services\Bot\BotExpressionService;
use App\Services\TwitchApiService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $stub = new class extends TwitchApiService
    {
        public function __construct() {}

        public function getExtendedUserData(string $accessToken, string $twitchId): array
        {
            return [];
        }
    };
    app()->instance(TwitchApiService::class, $stub);
});

function outboxUser(): User
{
    return User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => 'streamer'],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

// ──────────────────────────────────────────────────────────────────────────────
// Push: the bot drains on a nudge instead of waiting out its 2s poll.
// ──────────────────────────────────────────────────────────────────────────────

it('nudges the bot the moment a message is queued', function () {
    $user = outboxUser();
    app()->forgetScopedInstances();

    Event::fake([BotOutboxPending::class]);

    BotChatOutbox::create(['user_id' => $user->id, 'message' => 'hello chat']);

    Event::assertDispatched(BotOutboxPending::class);
});

it('nudges the bot when a chat command produces a reply', function () {
    $user = outboxUser();
    $expression = BotExpression::create([
        'user_id' => $user->id, 'command' => 'wins', 'permission_level' => 'everyone',
        'cooldown_seconds' => 0, 'expression' => 'won [[[counter:wins]]] times',
        'enabled' => true, 'hidden_from_commands' => false,
    ]);
    app()->forgetScopedInstances();

    Event::fake([BotOutboxPending::class]);

    // The end-to-end path a chatter actually triggers.
    $message = app(BotExpressionService::class)->fire($expression, []);

    expect($message)->toBe('won 1 times');
    Event::assertDispatched(BotOutboxPending::class);
});

it('nudges once however many messages one action queues', function () {
    $user = outboxUser();
    app()->forgetScopedInstances();

    Event::fake([BotOutboxPending::class]);

    // A gamejam round writes several messages in one go. The bot claims every
    // pending row in a single transaction, so one nudge covers all of them and
    // the rest would be broadcasts spent on nothing.
    foreach (range(1, 5) as $i) {
        BotChatOutbox::create(['user_id' => $user->id, 'message' => "line $i"]);
    }

    Event::assertDispatchedTimes(BotOutboxPending::class, 1);
});

it('does not nudge when the bot claims a message', function () {
    $user = outboxUser();
    $row = BotChatOutbox::create(['user_id' => $user->id, 'message' => 'hello chat']);
    app()->forgetScopedInstances();

    Event::fake([BotOutboxPending::class]);

    // Stamping sent_at is the bot taking the message. Announcing there would be
    // a broadcast per delivery, forever, telling the bot about work it just did.
    $row->update(['sent_at' => now()]);

    Event::assertNotDispatched(BotOutboxPending::class);
});

it('carries no payload, so chat text never rides a public channel', function () {
    $event = new BotOutboxPending;

    expect($event->broadcastWith())->toBe([])
        ->and($event->broadcastAs())->toBe('bot.outbox.pending')
        // Same channel the bot already holds open for command-map refreshes.
        ->and($event->broadcastOn()[0]->name)->toBe('bot-channels');
});

// ──────────────────────────────────────────────────────────────────────────────
// Prune: the outbox is a queue, and it was growing without bound.
// ──────────────────────────────────────────────────────────────────────────────

it('prunes delivered messages older than 7 days and keeps the rest', function () {
    $user = outboxUser();

    $old = BotChatOutbox::create(['user_id' => $user->id, 'message' => 'ancient']);
    $old->forceFill(['sent_at' => now()->subDays(8)])->save();

    $recent = BotChatOutbox::create(['user_id' => $user->id, 'message' => 'recent']);
    $recent->forceFill(['sent_at' => now()->subDays(2)])->save();

    // Never claimed by the bot. Still owed to the channel, whatever its age -
    // deleting it would silently drop a message the bot was going to post.
    $unsent = BotChatOutbox::create(['user_id' => $user->id, 'message' => 'still owed']);
    $unsent->forceFill(['created_at' => now()->subDays(30)])->save();

    $this->artisan('schedule:test', ['--name' => 'prune:bot-chat-outbox'])->assertSuccessful();

    expect(BotChatOutbox::find($old->id))->toBeNull()
        ->and(BotChatOutbox::find($recent->id))->not->toBeNull()
        ->and(BotChatOutbox::find($unsent->id))->not->toBeNull();
});

it('registers the prune on the schedule', function () {
    // Cheap guard that the task exists and is named as expected; the test above
    // proves what it deletes.
    $names = collect(app(Schedule::class)->events())
        ->map(fn ($event) => $event->description)
        ->filter()
        ->values();

    expect($names)->toContain('prune:bot-chat-outbox');
});
