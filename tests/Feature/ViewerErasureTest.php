<?php

use App\Models\BotBuiltin;
use App\Models\Checkin;
use App\Models\ExternalEvent;
use App\Models\ListAppender;
use App\Models\ListAppendHistory;
use App\Models\OverlayControl;
use App\Models\TowerBlock;
use App\Models\TwitchEvent;
use App\Models\User;
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
