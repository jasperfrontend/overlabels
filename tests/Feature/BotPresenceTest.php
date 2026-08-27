<?php

use App\Models\ListAppender;
use App\Models\OptionSet;
use App\Models\User;
use App\Services\BotPresence;
use App\Support\WiringCatalog;
use App\Support\WiringFacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * The bot reports which chats it is subscribed to after every channel sync;
 * the `bot.present` wire reads those reports. Chat-stats were considered and
 * rejected as the signal: the bot skips idle channels, so silence there is a
 * quiet chat far more often than an absent bot.
 */
const PRESENCE_URL = '/api/internal/bot/presence';

function postPresence(array $logins): TestResponse
{
    return test()->postJson(PRESENCE_URL, ['logins' => $logins], ['X-Internal-Secret' => 'test-bot-secret']);
}

function presenceUser(string $login, bool $enabled = true): User
{
    $user = User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'bot_enabled' => $enabled,
        'twitch_data' => ['login' => $login],
    ]);
    $list = OptionSet::create(['user_id' => $user->id, 'slug' => 'list_'.fake()->unique()->lexify('??????'), 'label' => 'Raffle', 'items' => []]);
    ListAppender::create(['user_id' => $user->id, 'target_list_id' => $list->id, 'command' => 'enter', 'permission_level' => 'everyone', 'enabled' => true]);

    return $user;
}

function presentState(User $user): string
{
    return WiringFacts::for($user->fresh())['bot'][0]['states']['bot.present'];
}

beforeEach(function () {
    config(['services.twitchbot.listener_secret' => 'test-bot-secret']);
    Cache::flush();
});

// ── the endpoint ─────────────────────────────────────────────────────────────

test('presence requires the internal secret', function () {
    test()->postJson(PRESENCE_URL, ['logins' => ['a']])->assertForbidden();
});

test('a report stamps every login, lowercased, and the report itself', function () {
    postPresence(['Streamer', 'other'])->assertOk()->assertJson(['ok' => true, 'count' => 2]);

    $presence = app(BotPresence::class);
    expect($presence->reporting())->toBeTrue()
        ->and($presence->present('streamer'))->toBeTrue()
        ->and($presence->present('other'))->toBeTrue()
        ->and($presence->present('nobody'))->toBeFalse();
});

test('an empty report is valid: the bot is running and in no chats', function () {
    postPresence([])->assertOk()->assertJson(['count' => 0]);

    expect(app(BotPresence::class)->reporting())->toBeTrue();
});

test('the report is validated', function () {
    test()->postJson(PRESENCE_URL, [], ['X-Internal-Secret' => 'test-bot-secret'])->assertStatus(422);
    test()->postJson(PRESENCE_URL, ['logins' => [str_repeat('x', 65)]], ['X-Internal-Secret' => 'test-bot-secret'])->assertStatus(422);
});

// ── the wire ─────────────────────────────────────────────────────────────────

test('the wire does not arise until the toggle is on and the bot has reported', function () {
    $off = presenceUser('quiet', enabled: false);
    expect(presentState($off))->toBe(WiringCatalog::NOT_APPLICABLE);

    $on = presenceUser('streamer');
    expect(presentState($on))->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and(implode(' ', WiringFacts::for($on)['bot'][0]['context']))->toContain('has not reported in yet');
});

test('a login in the latest report satisfies the wire', function () {
    $user = presenceUser('streamer');
    postPresence(['streamer', 'someone_else']);

    expect(presentState($user))->toBe(WiringCatalog::SATISFIED)
        ->and(implode(' ', WiringFacts::for($user)['bot'][0]['context']))->toContain('last confirmed your chat');
});

test('a login the bot has stopped reporting is a finding while the bot keeps reporting', function () {
    $user = presenceUser('streamer');
    postPresence(['streamer']);
    expect(presentState($user))->toBe(WiringCatalog::SATISFIED);

    // Six minutes on: the bot is still reporting, this login is not in it.
    $this->travel(BotPresence::WINDOW_SECONDS + 60)->seconds();
    postPresence(['someone_else']);

    expect(presentState($user))->toBe(WiringCatalog::MISSING)
        ->and(implode(' ', WiringFacts::for($user)['bot'][0]['context']))->toContain('last confirmed your chat');
});

test('a login never reported is a finding once the bot reports at all', function () {
    $user = presenceUser('streamer');
    postPresence(['someone_else']);

    expect(presentState($user))->toBe(WiringCatalog::MISSING)
        ->and(implode(' ', WiringFacts::for($user)['bot'][0]['context']))->toContain('never confirmed your chat');
});

test('a bot that has gone silent is nobody\'s loose end', function () {
    // The process is down or restarting: a platform matter, not this streamer's.
    $user = presenceUser('streamer');
    postPresence(['someone_else']);
    $this->travel(BotPresence::WINDOW_SECONDS + 60)->seconds();

    expect(presentState($user))->toBe(WiringCatalog::NOT_APPLICABLE);
});
