<?php

use App\Models\BotBuiltin;
use App\Models\Checkin;
use App\Models\ExternalEvent;
use App\Models\ListAppender;
use App\Models\ListAppendHistory;
use App\Models\OverlayControl;
use App\Models\StreamState;
use App\Models\TowerBlock;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\TwitchApiService;
use App\Services\ViewerErasureService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

uses(DatabaseTransactions::class);

beforeEach(function () {
    config(['services.twitchbot.listener_secret' => 'test-bot-secret']);
});

// A viewer has no account here and most have never heard of Overlabels.
// !forgetme is the only route they have to us from the place the data was
// collected, which is why the builtin is platform-owned and everyone-tier.

function forgetMe(array $overrides = []): TestResponse
{
    return test()->withHeaders(['X-Internal-Secret' => 'test-bot-secret'])
        ->postJson('/api/internal/bot/forgetme', array_merge([
            'chatter_id' => '555000',
            'chatter_login' => 'alice',
            'chatter_display_name' => 'Alice',
        ], $overrides));
}

it('deletes the viewer from every table that is keyed to them', function () {
    $user = User::factory()->create();

    Checkin::create([
        'user_id' => $user->id,
        'chatter_twitch_id' => '555000',
        'chatter_login' => 'alice',
        'chatter_display_name' => 'Alice',
        'place_label' => 'Amsterdam, NL',
        'country_code' => 'NL',
        'lat' => 52.37,
        'lng' => 4.89,
        'checked_in_at' => now(),
    ]);

    TowerBlock::create([
        'user_id' => $user->id,
        'chatter_twitch_id' => '555000',
        'chatter_login' => 'alice',
        'chatter_display_name' => 'Alice',
        'color' => '#FF0000',
        'position' => 1,
        'offset' => 0.2,
        'x' => 0.2,
        'record' => false,
        'placed_at' => now(),
    ]);

    TwitchEvent::create([
        'user_id' => $user->id,
        'event_type' => 'channel.follow',
        'event_data' => ['user_id' => '555000', 'user_name' => 'Alice'],
        'twitch_timestamp' => now(),
        'processed' => true,
    ]);

    ExternalEvent::create([
        'user_id' => $user->id,
        'service' => 'checkin',
        'event_type' => 'checkin',
        'message_id' => 'chk-1',
        'raw_payload' => ['chatter_id' => '555000', 'place_label' => 'Amsterdam, NL'],
        'normalized_payload' => [],
    ]);

    $appender = ListAppender::factory()->create(['user_id' => $user->id]);

    ListAppendHistory::create([
        'list_appender_id' => $appender->id,
        'target_list_id' => $appender->target_list_id,
        'chatter_id' => '555000',
        'chatter_login' => 'alice',
        'value' => 'Alice',
        'stream_session_id' => null,
        'fired_at' => now(),
    ]);

    forgetMe()->assertOk();

    expect(Checkin::where('chatter_twitch_id', '555000')->count())->toBe(0)
        ->and(TowerBlock::where('chatter_twitch_id', '555000')->count())->toBe(0)
        ->and(TwitchEvent::whereRaw("event_data->>'user_id' = '555000'")->count())->toBe(0)
        ->and(ExternalEvent::whereRaw("raw_payload->>'chatter_id' = '555000'")->count())->toBe(0)
        ->and(ListAppendHistory::where('chatter_id', '555000')->count())->toBe(0);
});

it('leaves other viewers alone', function () {
    $user = User::factory()->create();

    Checkin::create([
        'user_id' => $user->id,
        'chatter_twitch_id' => '999999',
        'chatter_login' => 'bob',
        'chatter_display_name' => 'Bob',
        'place_label' => 'Berlin, DE',
        'country_code' => 'DE',
        'lat' => 52.52,
        'lng' => 13.4,
        'checked_in_at' => now(),
    ]);

    forgetMe()->assertOk();

    expect(Checkin::where('chatter_twitch_id', '999999')->count())->toBe(1);
});

it('blanks a managed control that is currently showing their name', function () {
    $user = User::factory()->create();

    $mine = OverlayControl::factory()->create([
        'user_id' => $user->id,
        'key' => 'latest_checkin_name',
        'value' => 'Alice',
        'source' => 'checkin',
        'source_managed' => true,
    ]);

    $someoneElse = OverlayControl::factory()->create([
        'user_id' => $user->id,
        'key' => 'latest_chatter_name',
        'value' => 'Bob',
        'source' => 'twitch',
        'source_managed' => true,
    ]);

    forgetMe()->assertOk();

    expect($mine->fresh()->value)->toBe('')
        ->and($someoneElse->fresh()->value)->toBe('Bob');
});

it('does not touch a streamer own control that happens to hold the name', function () {
    $user = User::factory()->create();

    // Not source_managed and not a key Overlabels writes a name into: this is
    // the streamer's content, and a service hunting for names across arbitrary
    // user text is worse than the problem it solves.
    $theirs = OverlayControl::factory()->create([
        'user_id' => $user->id,
        'key' => 'shoutout_target',
        'value' => 'Alice',
        'source' => null,
        'source_managed' => false,
    ]);

    forgetMe()->assertOk();

    expect($theirs->fresh()->value)->toBe('Alice');
});

it('remembers the request so the next checkin is refused', function () {
    expect(app(ViewerErasureService::class)->isSuppressed('555000'))->toBeFalse();

    forgetMe()->assertOk();

    expect(app(ViewerErasureService::class)->isSuppressed('555000'))->toBeTrue();
    expect(DB::table('viewer_erasures')->where('twitch_id', '555000')->count())->toBe(1);
});

it('is idempotent, because asking twice is not an error', function () {
    forgetMe()->assertOk();
    forgetMe()->assertOk();

    expect(DB::table('viewer_erasures')->where('twitch_id', '555000')->count())->toBe(1);
});

it('answers even when there was nothing to delete', function () {
    $response = forgetMe(['chatter_id' => '1234567']);

    $response->assertOk();
    expect($response->json('reply'))->toContain('Overlabels held nothing about you');
});

it('points the viewer at the streamer for their own lists', function () {
    expect(forgetMe()->json('reply'))->toContain('ask them');
});

it('refuses without the internal secret', function () {
    $this->postJson('/api/internal/bot/forgetme', ['chatter_id' => '555000'])
        ->assertStatus(403);
});

// The two-edit rule: DEFAULTS covers everyone who opts in from now on, the
// backfill migration covers everyone who already had. Shipping one without the
// other makes the command silent for one of the two groups, with no error
// anywhere. It has happened twice before.
it('declares forgetme as a platform-owned everyone-tier builtin', function () {
    $declared = collect(BotBuiltin::DEFAULTS)->firstWhere('command', 'forgetme');

    expect($declared)->not->toBeNull()
        ->and($declared['permission_level'])->toBe('everyone')
        ->and($declared['owner'])->toBe(BotBuiltin::OWNER_PLATFORM);
});

it('refuses to let a streamer disable or re-tier it', function () {
    expect(BotBuiltin::isEditable('forgetme'))->toBeFalse();
});

it('seeds forgetme for an account that opts into the bot', function () {
    $user = User::factory()->create(['bot_enabled' => false]);
    $user->update(['bot_enabled' => true]);

    expect(BotBuiltin::where('user_id', $user->id)->where('command', 'forgetme')->exists())->toBeTrue();
});

// ── The promise "we won't store you again" ──────────────────────────────────
//
// Erasing rows is only half of it. Sally's audit of OL-2609-099 found the other
// half missing: two surfaces wrote an erased viewer straight back. A follow,
// sub or cheer re-created a twitch_events row carrying their id and display
// name, and the bot's chat summary rewrote latest_chatter_name with them.

function postErasureNotification(string $messageId, array $event, string $type = 'channel.follow'): TestResponse
{
    config(['app.twitch_webhook_secret' => 'test-webhook-secret']);

    $body = json_encode([
        'subscription' => ['id' => 'sub-1', 'type' => $type, 'version' => '2'],
        'event' => $event,
    ]);
    $timestamp = now()->toIso8601String();

    return test()->call('POST', '/api/twitch/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_TWITCH_EVENTSUB_MESSAGE_TYPE' => 'notification',
        'HTTP_TWITCH_EVENTSUB_MESSAGE_ID' => $messageId,
        'HTTP_TWITCH_EVENTSUB_MESSAGE_TIMESTAMP' => $timestamp,
        'HTTP_TWITCH_EVENTSUB_MESSAGE_SIGNATURE' => 'sha256='.hash_hmac('sha256', $messageId.$timestamp.$body, 'test-webhook-secret'),
    ], $body);
}

function erasureChatControl(User $user, string $key, string $type, string $value): OverlayControl
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

it('anonymises a real twitch webhook from a viewer who asked to be forgotten', function () {
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($token, $event) => $event);
    });

    $streamer = User::factory()->create(['access_token' => 'token']);

    forgetMe()->assertOk();

    postErasureNotification('msg-erased', [
        'broadcaster_user_id' => $streamer->twitch_id,
        'user_id' => '555000',
        'user_login' => 'alice',
        'user_name' => 'Alice',
    ])->assertOk();

    $stored = TwitchEvent::latest('id')->first();

    // The streamer still gets the event and the counter that follows from it.
    // What nobody gets is a name.
    expect($stored)->not->toBeNull()
        ->and($stored->event_data['user_id'])->toBeNull()
        ->and($stored->event_data['user_name'])->toBeNull()
        ->and($stored->event_data['user_login'])->toBeNull()
        ->and($stored->event_data['broadcaster_user_id'])->toBe($streamer->twitch_id);

    expect(json_encode($stored->event_data))->not->toContain('Alice');
});

it('leaves a webhook from a viewer who has not asked completely alone', function () {
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($token, $event) => $event);
    });

    $streamer = User::factory()->create(['access_token' => 'token']);

    forgetMe()->assertOk();

    postErasureNotification('msg-kept', [
        'broadcaster_user_id' => $streamer->twitch_id,
        'user_id' => '999999',
        'user_login' => 'bob',
        'user_name' => 'Bob',
    ])->assertOk();

    expect(TwitchEvent::latest('id')->first()->event_data['user_name'])->toBe('Bob');
});

// Twitch does not call the acting viewer `user_*` in every payload shape. A
// raid names the raider `from_broadcaster_user_*` and a chat notification names
// the chatter `chatter_user_*`, so a suppression check that only reads
// `user_id` writes those two straight back.

it('deletes the events that name the viewer under Twitch other payload names', function () {
    $user = User::factory()->create();

    $raid = TwitchEvent::create([
        'user_id' => $user->id,
        'event_type' => 'channel.raid',
        'event_data' => ['from_broadcaster_user_id' => '555000', 'from_broadcaster_user_name' => 'Alice'],
        'twitch_timestamp' => now(),
        'processed' => true,
    ]);

    $notice = TwitchEvent::create([
        'user_id' => $user->id,
        'event_type' => 'channel.chat.notification',
        'event_data' => ['chatter_user_id' => '555000', 'chatter_user_name' => 'Alice'],
        'twitch_timestamp' => now(),
        'processed' => true,
    ]);

    $someoneElse = TwitchEvent::create([
        'user_id' => $user->id,
        'event_type' => 'channel.raid',
        'event_data' => ['from_broadcaster_user_id' => '999999', 'from_broadcaster_user_name' => 'Bob'],
        'twitch_timestamp' => now(),
        'processed' => true,
    ]);

    forgetMe()->assertOk();

    expect(TwitchEvent::find($raid->id))->toBeNull()
        ->and(TwitchEvent::find($notice->id))->toBeNull()
        ->and(TwitchEvent::find($someoneElse->id))->not->toBeNull();
});

it('anonymises a raid from a viewer who asked to be forgotten', function () {
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($token, $event) => $event);
    });

    $streamer = User::factory()->create(['access_token' => 'token']);

    forgetMe()->assertOk();

    postErasureNotification('msg-raid-erased', [
        'to_broadcaster_user_id' => $streamer->twitch_id,
        'to_broadcaster_user_login' => 'streamer',
        'to_broadcaster_user_name' => 'Streamer',
        'from_broadcaster_user_id' => '555000',
        'from_broadcaster_user_login' => 'alice',
        'from_broadcaster_user_name' => 'Alice',
        'viewers' => 42,
    ], 'channel.raid')->assertOk();

    $stored = TwitchEvent::latest('id')->first();

    // The raid still arrives, and still carries the number of viewers it
    // brought. What nobody gets is a name.
    expect($stored)->not->toBeNull()
        ->and($stored->event_data['from_broadcaster_user_id'])->toBeNull()
        ->and($stored->event_data['from_broadcaster_user_login'])->toBeNull()
        ->and($stored->event_data['from_broadcaster_user_name'])->toBeNull()
        ->and($stored->event_data['to_broadcaster_user_id'])->toBe($streamer->twitch_id)
        ->and($stored->event_data['viewers'])->toBe(42);

    expect(json_encode($stored->event_data))->not->toContain('Alice');
});

it('leaves a raid from a viewer who has not asked completely alone', function () {
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($token, $event) => $event);
    });

    $streamer = User::factory()->create(['access_token' => 'token']);

    forgetMe()->assertOk();

    postErasureNotification('msg-raid-kept', [
        'to_broadcaster_user_id' => $streamer->twitch_id,
        'from_broadcaster_user_id' => '999999',
        'from_broadcaster_user_login' => 'bob',
        'from_broadcaster_user_name' => 'Bob',
        'viewers' => 7,
    ], 'channel.raid')->assertOk();

    expect(TwitchEvent::latest('id')->first()->event_data['from_broadcaster_user_name'])->toBe('Bob');
});

it('anonymises a chat notification from a viewer who asked to be forgotten', function () {
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($token, $event) => $event);
    });

    $streamer = User::factory()->create(['access_token' => 'token']);

    forgetMe()->assertOk();

    postErasureNotification('msg-notice-erased', [
        'broadcaster_user_id' => $streamer->twitch_id,
        'chatter_user_id' => '555000',
        'chatter_user_login' => 'alice',
        'chatter_user_name' => 'Alice',
        'chatter_is_anonymous' => false,
        'notice_type' => 'sub',
        'system_message' => 'Alice subscribed at Tier 1.',
        'sub' => ['sub_tier' => '1000', 'is_prime' => false, 'duration_months' => 1],
    ], 'channel.chat.notification')->assertOk();

    $stored = TwitchEvent::latest('id')->first();

    // The ledger row survives - it is what a Plus Points count is built from.
    // The person named in it does not.
    expect($stored)->not->toBeNull()
        ->and($stored->event_data['chatter_user_id'])->toBeNull()
        ->and($stored->event_data['chatter_user_login'])->toBeNull()
        ->and($stored->event_data['chatter_user_name'])->toBeNull()
        ->and($stored->event_data['notice_type'])->toBe('sub')
        ->and($stored->event_data['sub']['is_prime'])->toBeFalse();

    expect(json_encode($stored->event_data))->not->toContain('Alice');
});

it('keeps an erased viewer out of latest_chatter_name while still counting them', function () {
    $user = User::factory()->create([
        'twitch_id' => '4242',
        'bot_enabled' => true,
        'twitch_data' => ['login' => 'streamer'],
    ]);

    StreamState::updateOrCreate(
        ['user_id' => $user->id],
        ['state' => StreamState::STATE_LIVE, 'confidence' => 1.0],
    );

    erasureChatControl($user, 'latest_chatter_name', 'text', '');
    erasureChatControl($user, 'latest_chat_message', 'text', '');
    erasureChatControl($user, 'chat_messages_this_stream', 'counter', '0');

    forgetMe()->assertOk();

    $this->withHeaders(['X-Internal-Secret' => 'test-bot-secret'])
        ->postJson('/api/internal/bot/chat-stats/streamer', [
            'message_count' => 3,
            'chatters' => ['alice'],
            'latest_chatter_name' => 'Alice',
            'latest_chat_message' => 'hello',
            'latest_chatter_id' => '555000',
        ])->assertOk();

    $name = OverlayControl::where('user_id', $user->id)->where('key', 'latest_chatter_name')->first();
    $message = OverlayControl::where('user_id', $user->id)->where('key', 'latest_chat_message')->first();
    $count = OverlayControl::where('user_id', $user->id)->where('key', 'chat_messages_this_stream')->first();

    expect($name?->value)->not->toBe('Alice')
        ->and($message?->value)->not->toBe('hello')
        // The count is a number, not a person, so it still moves.
        ->and((int) $count?->value)->toBe(3);
});

it('still writes latest_chatter_name for a viewer who has not asked', function () {
    $user = User::factory()->create([
        'twitch_id' => '4343',
        'bot_enabled' => true,
        'twitch_data' => ['login' => 'otherstreamer'],
    ]);

    StreamState::updateOrCreate(
        ['user_id' => $user->id],
        ['state' => StreamState::STATE_LIVE, 'confidence' => 1.0],
    );

    erasureChatControl($user, 'latest_chatter_name', 'text', '');

    $this->withHeaders(['X-Internal-Secret' => 'test-bot-secret'])
        ->postJson('/api/internal/bot/chat-stats/otherstreamer', [
            'message_count' => 1,
            'chatters' => ['bob'],
            'latest_chatter_name' => 'Bob',
            'latest_chat_message' => 'hi',
            'latest_chatter_id' => '999999',
        ])->assertOk();

    expect(OverlayControl::where('user_id', $user->id)->where('key', 'latest_chatter_name')->first()?->value)
        ->toBe('Bob');
});
