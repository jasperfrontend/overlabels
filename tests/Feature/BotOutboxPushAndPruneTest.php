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
use Illuminate\Testing\TestResponse;

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

    // Matches BotInternalApiTest: the claim endpoint authenticates on this.
    config(['services.twitchbot.listener_secret' => 'test-bot-secret']);
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

it('prunes everything older than 7 days and keeps the rest', function () {
    $user = outboxUser();

    $delivered = BotChatOutbox::create(['user_id' => $user->id, 'message' => 'ancient']);
    $delivered->forceFill(['created_at' => now()->subDays(8), 'sent_at' => now()->subDays(8)])->save();

    $discarded = BotChatOutbox::create(['user_id' => $user->id, 'message' => 'dropped']);
    $discarded->forceFill(['created_at' => now()->subDays(9), 'discarded_at' => now()->subDays(9)])->save();

    // Unsent rows are swept too now. They used to be exempt as "still owed",
    // but the claim path discards anything stale, so a 30-day-old row is never
    // going out and keeping it only grows the table.
    $ancientUnsent = BotChatOutbox::create(['user_id' => $user->id, 'message' => 'never claimed']);
    $ancientUnsent->forceFill(['created_at' => now()->subDays(30)])->save();

    $recent = BotChatOutbox::create(['user_id' => $user->id, 'message' => 'recent']);
    $recent->forceFill(['created_at' => now()->subDays(2), 'sent_at' => now()->subDays(2)])->save();

    $this->artisan('schedule:test', ['--name' => 'prune:bot-chat-outbox'])->assertSuccessful();

    expect(BotChatOutbox::find($delivered->id))->toBeNull()
        ->and(BotChatOutbox::find($discarded->id))->toBeNull()
        ->and(BotChatOutbox::find($ancientUnsent->id))->toBeNull()
        ->and(BotChatOutbox::find($recent->id))->not->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Staleness. A chat reply is worthless once the conversation has moved on, and
// posting a backlog on reconnect is worse than staying quiet.
// ──────────────────────────────────────────────────────────────────────────────

function claimOutbox(): TestResponse
{
    return test()->getJson('/api/internal/bot/outbox', ['X-Internal-Secret' => 'test-bot-secret']);
}

it('hands out a fresh message and marks it sent', function () {
    $user = outboxUser();
    $row = BotChatOutbox::create(['user_id' => $user->id, 'message' => 'won 1 times']);

    $response = claimOutbox()->assertOk();

    expect($response->json('messages'))->toHaveCount(1)
        ->and($response->json('messages.0.message'))->toBe('won 1 times')
        ->and($row->fresh()->sent_at)->not->toBeNull()
        ->and($row->fresh()->discarded_at)->toBeNull();
});

it('drops a message queued while the bot was down instead of posting it late', function () {
    $user = outboxUser();

    // The scenario: bot offline for hours, comes back, claims. Before this,
    // every one of these went out at once into a chat that had moved on.
    $stale = BotChatOutbox::create(['user_id' => $user->id, 'message' => 'won 1 times']);
    $stale->forceFill(['created_at' => now()->subHours(6)])->save();

    $response = claimOutbox()->assertOk();

    expect($response->json('messages'))->toBeEmpty()
        ->and($stale->fresh()->discarded_at)->not->toBeNull()
        ->and($stale->fresh()->sent_at)->toBeNull();
});

it('delivers the fresh messages and drops the stale ones in the same claim', function () {
    $user = outboxUser();

    $stale = BotChatOutbox::create(['user_id' => $user->id, 'message' => 'ancient reply']);
    $stale->forceFill(['created_at' => now()->subHours(2)])->save();
    $fresh = BotChatOutbox::create(['user_id' => $user->id, 'message' => 'current reply']);

    $response = claimOutbox()->assertOk();

    expect($response->json('messages'))->toHaveCount(1)
        ->and($response->json('messages.0.message'))->toBe('current reply')
        ->and($fresh->fresh()->sent_at)->not->toBeNull()
        ->and($stale->fresh()->discarded_at)->not->toBeNull();
});

it('never hands out a discarded message on a later claim', function () {
    $user = outboxUser();
    $stale = BotChatOutbox::create(['user_id' => $user->id, 'message' => 'ancient reply']);
    $stale->forceFill(['created_at' => now()->subHours(6)])->save();

    claimOutbox()->assertOk();
    // A second poll two seconds later must not resurrect it.
    claimOutbox()->assertOk()->assertJsonPath('messages', []);

    expect($stale->fresh()->sent_at)->toBeNull();
});

it('keeps a message queued during a bot restart', function () {
    $user = outboxUser();

    // The reason the cutoff is a minute rather than a few seconds: a container
    // swap should not silently eat replies queued while it happens.
    $row = BotChatOutbox::create(['user_id' => $user->id, 'message' => 'won 1 times']);
    $row->forceFill(['created_at' => now()->subSeconds(20)])->save();

    expect(claimOutbox()->assertOk()->json('messages'))->toHaveCount(1)
        ->and($row->fresh()->discarded_at)->toBeNull();
});

it('splits exactly on the configured cutoff', function () {
    $user = outboxUser();
    $cutoff = BotChatOutbox::STALE_AFTER_SECONDS;

    $justInside = BotChatOutbox::create(['user_id' => $user->id, 'message' => 'inside']);
    $justInside->forceFill(['created_at' => now()->subSeconds($cutoff - 5)])->save();

    $justOutside = BotChatOutbox::create(['user_id' => $user->id, 'message' => 'outside']);
    $justOutside->forceFill(['created_at' => now()->subSeconds($cutoff + 5)])->save();

    claimOutbox()->assertOk();

    expect($justInside->fresh()->sent_at)->not->toBeNull()
        ->and($justInside->fresh()->discarded_at)->toBeNull()
        ->and($justOutside->fresh()->discarded_at)->not->toBeNull()
        ->and($justOutside->fresh()->sent_at)->toBeNull();
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
