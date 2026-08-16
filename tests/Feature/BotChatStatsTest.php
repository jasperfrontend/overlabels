<?php

use App\Events\ControlValueUpdated;
use App\Models\OverlayControl;
use App\Models\StreamState;
use App\Models\User;
use App\Services\StreamSessionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

uses(DatabaseTransactions::class);

const CHAT_STATS_URL = '/api/internal/bot/chat-stats/streamer';

beforeEach(function () {
    config(['services.twitchbot.listener_secret' => 'test-bot-secret']);
    Cache::flush();

    $this->user = User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => 'streamer'],
    ]);
});

function goLive(User $user): void
{
    StreamState::updateOrCreate(
        ['user_id' => $user->id],
        ['state' => StreamState::STATE_LIVE, 'confidence' => 1.0],
    );
}

function chatControl(User $user, string $key, string $type, string $value): OverlayControl
{
    return OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => $key,
        'label' => $key,
        'type' => $type,
        'value' => $value,
        'source' => 'twitch',
        'source_managed' => true,
    ]);
}

function postChatStats(array $payload): TestResponse
{
    return test()->postJson(CHAT_STATS_URL, $payload, ['X-Internal-Secret' => 'test-bot-secret']);
}

// ──────────────────────────────────────────────────────────────────────────────
// Auth and routing
// ──────────────────────────────────────────────────────────────────────────────

test('chat-stats returns 403 without the internal secret', function () {
    $this->postJson(CHAT_STATS_URL, ['message_count' => 1])
        ->assertStatus(403);
});

test('chat-stats returns 404 for an unknown channel', function () {
    $this->postJson(
        '/api/internal/bot/chat-stats/nobody',
        ['message_count' => 1],
        ['X-Internal-Secret' => 'test-bot-secret'],
    )->assertStatus(404);
});

test('chat-stats returns 404 for a channel with the bot disabled', function () {
    // A channel with the bot off produces no data, which must not be mistaken
    // for a quiet stream.
    $this->user->update(['bot_enabled' => false]);

    postChatStats(['message_count' => 5])->assertStatus(404);
});

test('chat-stats rejects a message that exceeds the Twitch limit', function () {
    goLive($this->user);

    postChatStats([
        'message_count' => 1,
        'latest_chat_message' => str_repeat('a', 501),
    ])->assertStatus(422);
});

// ──────────────────────────────────────────────────────────────────────────────
// Live gating
// ──────────────────────────────────────────────────────────────────────────────

test('a summary is ignored while the channel is not confidently live', function () {
    $messages = chatControl($this->user, 'chat_messages_this_stream', 'counter', '0');

    postChatStats(['message_count' => 40, 'chatters' => ['alice']])
        ->assertOk()
        ->assertJson(['applied' => false]);

    expect($messages->fresh()->value)->toBe('0');
});

test('a summary below the confidence threshold is ignored', function () {
    StreamState::updateOrCreate(
        ['user_id' => $this->user->id],
        ['state' => StreamState::STATE_LIVE, 'confidence' => 0.5],
    );
    $messages = chatControl($this->user, 'chat_messages_this_stream', 'counter', '0');

    postChatStats(['message_count' => 40])->assertJson(['applied' => false]);

    expect($messages->fresh()->value)->toBe('0');
});

// ──────────────────────────────────────────────────────────────────────────────
// Applying a summary
// ──────────────────────────────────────────────────────────────────────────────

test('message counts accumulate additively across summaries', function () {
    goLive($this->user);
    $messages = chatControl($this->user, 'chat_messages_this_stream', 'counter', '0');

    postChatStats(['message_count' => 40])->assertJson(['applied' => true]);
    postChatStats(['message_count' => 12]);

    expect($messages->fresh()->value)->toBe('52');
});

test('unique chatters dedupe across summaries, not just within one', function () {
    goLive($this->user);
    $unique = chatControl($this->user, 'unique_chatters_this_stream', 'counter', '0');

    postChatStats(['message_count' => 3, 'chatters' => ['alice', 'bob']]);
    // bob already counted; only carol is new.
    postChatStats(['message_count' => 2, 'chatters' => ['bob', 'carol']]);

    expect($unique->fresh()->value)->toBe('3');
});

test('chatter logins are compared case-insensitively', function () {
    goLive($this->user);
    $unique = chatControl($this->user, 'unique_chatters_this_stream', 'counter', '0');

    postChatStats(['message_count' => 2, 'chatters' => ['Alice', 'alice', 'ALICE']]);

    expect($unique->fresh()->value)->toBe('1');
});

test('the latest chatter and message are recorded verbatim', function () {
    goLive($this->user);
    $name = chatControl($this->user, 'latest_chatter_name', 'text', '');
    $message = chatControl($this->user, 'latest_chat_message', 'text', '');

    postChatStats([
        'message_count' => 1,
        'latest_chatter_name' => 'Alice',
        'latest_chat_message' => 'i <3 you',
    ]);

    expect($name->fresh()->value)->toBe('Alice')
        // Not strip_tags()'d: that eats from `<` to end of string, which would
        // truncate this to "i ". Render-side encoding is what makes it safe.
        ->and($message->fresh()->value)->toBe('i <3 you');
});

test('a window that adds no new chatter writes nothing and broadcasts nothing', function () {
    goLive($this->user);
    $unique = chatControl($this->user, 'unique_chatters_this_stream', 'counter', '0');

    postChatStats(['message_count' => 1, 'chatters' => ['alice']]);

    Event::fake([ControlValueUpdated::class]);
    $before = $unique->fresh()->updated_at;

    postChatStats(['message_count' => 0, 'chatters' => ['alice']]);

    Event::assertNotDispatched(ControlValueUpdated::class);
    expect($unique->fresh()->updated_at->eq($before))->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// Reset lifecycle - the documented trap
// ──────────────────────────────────────────────────────────────────────────────

test('both chat counters are on the per-stream reset list', function () {
    expect(StreamSessionService::PER_STREAM_CONTROL_KEYS)
        ->toContain('chat_messages_this_stream')
        ->toContain('unique_chatters_this_stream');
});

test('neither latest_* chat control is on the per-stream reset list', function () {
    // These are most-recent values, matching latest_cheer* and all 25
    // equivalents across the five donation services, which persist across
    // streams. Adding them here is the documented regression.
    expect(StreamSessionService::PER_STREAM_CONTROL_KEYS)
        ->not->toContain('latest_chatter_name')
        ->not->toContain('latest_chat_message');
});

test('going live zeroes the chat counters but leaves the latest_* pair alone', function () {
    goLive($this->user);
    $messages = chatControl($this->user, 'chat_messages_this_stream', 'counter', '900');
    $unique = chatControl($this->user, 'unique_chatters_this_stream', 'counter', '42');
    $name = chatControl($this->user, 'latest_chatter_name', 'text', 'alice');
    $message = chatControl($this->user, 'latest_chat_message', 'text', 'hello');

    app(StreamSessionService::class)->openSession($this->user);

    expect($messages->fresh()->value)->toBe('0')
        ->and($unique->fresh()->value)->toBe('0')
        ->and($name->fresh()->value)->toBe('alice')
        ->and($message->fresh()->value)->toBe('hello');
});

test('the unique-chatter set is cleared at go-live, so the counter can fall', function () {
    // The counter only ever moves upward within a stream, so clearing the
    // control without clearing the backing set would pin it at last stream's
    // total for the whole of the next one.
    goLive($this->user);
    $unique = chatControl($this->user, 'unique_chatters_this_stream', 'counter', '0');

    postChatStats(['message_count' => 3, 'chatters' => ['alice', 'bob', 'carol']]);
    expect($unique->fresh()->value)->toBe('3');

    app(StreamSessionService::class)->openSession($this->user);
    goLive($this->user);

    postChatStats(['message_count' => 1, 'chatters' => ['dave']]);

    expect($unique->fresh()->value)->toBe('1');
});

test('the unique counter never moves downward within a stream', function () {
    // A cache flush mid-stream restarts the set from empty. The counter must
    // hold its peak rather than visibly counting down on stream.
    goLive($this->user);
    $unique = chatControl($this->user, 'unique_chatters_this_stream', 'counter', '0');

    postChatStats(['message_count' => 3, 'chatters' => ['alice', 'bob', 'carol']]);
    expect($unique->fresh()->value)->toBe('3');

    Cache::flush();

    postChatStats(['message_count' => 1, 'chatters' => ['dave']]);

    expect($unique->fresh()->value)->toBe('3');
});

// ──────────────────────────────────────────────────────────────────────────────
// Scoping
// ──────────────────────────────────────────────────────────────────────────────

test('a summary does not touch another user\'s controls', function () {
    goLive($this->user);
    $other = User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => 'someone_else'],
    ]);
    goLive($other);
    $otherMessages = chatControl($other, 'chat_messages_this_stream', 'counter', '7');

    postChatStats(['message_count' => 40]);

    expect($otherMessages->fresh()->value)->toBe('7');
});

test('a summary broadcasts each changed control to the user channel', function () {
    goLive($this->user);
    chatControl($this->user, 'chat_messages_this_stream', 'counter', '0');
    Event::fake([ControlValueUpdated::class]);

    postChatStats(['message_count' => 40]);

    Event::assertDispatched(ControlValueUpdated::class);
});
