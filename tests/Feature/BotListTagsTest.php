<?php

use App\Models\BotChatOutbox;
use App\Models\ListAppender;
use App\Models\OptionSet;
use App\Models\User;
use App\Services\Bot\BotExpressionResolver;
use App\Services\Lists\ListAppendService;
use App\Services\TwitchApiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Stub the Twitch fetch so the resolver makes no HTTP calls. List tags
    // need no token, but resolve() still calls loadTwitchTags for bare tags.
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

function makeListUser(string $login = 'streamer'): User
{
    return User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => $login],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

// ──────────────────────────────────────────────────────────────────────────────
// Read tags resolve the same values the overlay projection ships
// ──────────────────────────────────────────────────────────────────────────────

it('resolves :count, :first, :last, :empty for a bot reply', function () {
    $user = makeListUser();
    OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'donors',
        'items' => ['Alice', 'Bob', 'Carol'],
    ]);

    $resolver = app(BotExpressionResolver::class);

    expect($resolver->resolve($user, '[[[c:list:donors:count]]]'))->toBe('3')
        ->and($resolver->resolve($user, '[[[c:list:donors:first]]]'))->toBe('Alice')
        ->and($resolver->resolve($user, '[[[c:list:donors:last]]]'))->toBe('Carol')
        ->and($resolver->resolve($user, '[[[c:list:donors:empty]]]'))->toBe('0');
});

it('resolves :empty to "1" and value tags to empty for an empty list', function () {
    $user = makeListUser();
    OptionSet::create(['user_id' => $user->id, 'slug' => 'nobody', 'items' => []]);

    $resolver = app(BotExpressionResolver::class);

    expect($resolver->resolve($user, '[[[c:list:nobody:empty]]]'))->toBe('1')
        ->and($resolver->resolve($user, '[[[c:list:nobody:count]]]'))->toBe('0')
        ->and($resolver->resolve($user, '[[[c:list:nobody:first]]]'))->toBe('')
        ->and($resolver->resolve($user, '[[[c:list:nobody:random]]]'))->toBe('');
});

it('resolves :sum and surfaces its loud-failure string', function () {
    $user = makeListUser();
    OptionSet::create(['user_id' => $user->id, 'slug' => 'tips', 'items' => ['5', '10', '15.5']]);
    OptionSet::create(['user_id' => $user->id, 'slug' => 'broken', 'items' => ['10', 'abc']]);

    $resolver = app(BotExpressionResolver::class);

    expect($resolver->resolve($user, '[[[c:list:tips:sum]]]'))->toBe('30.5')
        ->and($resolver->resolve($user, '[[[c:list:broken:sum]]]'))
        ->toBe("ERR: list 'broken' has non-numeric item 'abc' at position 1");
});

it('resolves :random to one of the list items', function () {
    $user = makeListUser();
    OptionSet::create(['user_id' => $user->id, 'slug' => 'colors', 'items' => ['red', 'green', 'blue']]);

    $resolver = app(BotExpressionResolver::class);

    expect($resolver->resolve($user, '[[[c:list:colors:random]]]'))->toBeIn(['red', 'green', 'blue']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Expiry tags: static snapshot, no live ticking in chat
// ──────────────────────────────────────────────────────────────────────────────

it('resolves :expires_at and :countdown as a static snapshot', function () {
    $user = makeListUser();
    OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'raffle',
        'items' => [],
        'expires_at' => now()->addSeconds(90),
    ]);

    $resolver = app(BotExpressionResolver::class);

    // countdown is seconds-until-expiry, clamped >= 0. now()->addSeconds(90)
    // can land on 89 or 90 depending on sub-second timing, so allow both.
    expect((int) $resolver->resolve($user, '[[[c:list:raffle:countdown]]]'))->toBeGreaterThanOrEqual(88)
        ->and((int) $resolver->resolve($user, '[[[c:list:raffle:countdown]]]'))->toBeLessThanOrEqual(90)
        ->and($resolver->resolve($user, '[[[c:list:raffle:expires_at]]]'))->not->toBe('');
});

it('clamps :countdown to 0 for an already-expired list', function () {
    $user = makeListUser();
    OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'stale',
        'items' => [],
        'expires_at' => now()->subMinutes(5),
    ]);

    $resolver = app(BotExpressionResolver::class);

    expect($resolver->resolve($user, '[[[c:list:stale:countdown]]]'))->toBe('0');
});

it('resolves expiry tags to empty when the list has no deadline', function () {
    $user = makeListUser();
    OptionSet::create(['user_id' => $user->id, 'slug' => 'forever', 'items' => ['x']]);

    $resolver = app(BotExpressionResolver::class);

    expect($resolver->resolve($user, '[[[c:list:forever:expires_at]]]'))->toBe('')
        ->and($resolver->resolve($user, '[[[c:list:forever:countdown]]]'))->toBe('');
});

// ──────────────────────────────────────────────────────────────────────────────
// Deliberately unsupported in chat: bare array dump + :json
// ──────────────────────────────────────────────────────────────────────────────

it('drops the bare c:list:<slug> array tag to empty in chat', function () {
    $user = makeListUser();
    OptionSet::create(['user_id' => $user->id, 'slug' => 'donors', 'items' => ['Alice', 'Bob']]);

    $resolver = app(BotExpressionResolver::class);

    // No raw JSON array dumped into chat.
    expect($resolver->resolve($user, '[[[c:list:donors]]]'))->toBe('')
        ->and($resolver->resolve($user, '[[[c:list:donors:json]]]'))->toBe('');
});

// ──────────────────────────────────────────────────────────────────────────────
// Pipe formatters and ?? defaults compose with list tags
// ──────────────────────────────────────────────────────────────────────────────

it('applies a pipe formatter to a list tag', function () {
    $user = makeListUser();
    OptionSet::create(['user_id' => $user->id, 'slug' => 'raffle', 'items' => [], 'expires_at' => now()->addSeconds(125)]);

    $resolver = app(BotExpressionResolver::class);

    // 125s -> mm:ss. Allow the boundary second from sub-second timing.
    expect($resolver->resolve($user, '[[[c:list:raffle:countdown|duration:mm:ss]]]'))
        ->toBeIn(['02:05', '02:04']);
});

it('falls back to a ?? default when a list value is empty', function () {
    $user = makeListUser();
    OptionSet::create(['user_id' => $user->id, 'slug' => 'quotes', 'items' => []]);

    $resolver = app(BotExpressionResolver::class);

    expect($resolver->resolve($user, '[[[c:list:quotes:last ?? no quotes yet]]]'))->toBe('no quotes yet');
});

// ──────────────────────────────────────────────────────────────────────────────
// Scoping: a list belongs to one user
// ──────────────────────────────────────────────────────────────────────────────

it('does not resolve another user\'s list', function () {
    $owner = makeListUser('owner');
    $other = makeListUser('other');
    OptionSet::create(['user_id' => $owner->id, 'slug' => 'secret', 'items' => ['a', 'b']]);

    $resolver = app(BotExpressionResolver::class);

    expect($resolver->resolve($other, '[[[c:list:secret:count]]]'))->toBe('');
});

// ──────────────────────────────────────────────────────────────────────────────
// End-to-end: the !sighting success reply reports the post-append count
// ──────────────────────────────────────────────────────────────────────────────

it('reports the post-append count in the success reply', function () {
    $user = makeListUser('sighting_streamer');
    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'sightings',
        'items' => ['enc 1', 'enc 2', 'enc 3', 'enc 4', 'enc 5', 'enc 6'],
        'next_item_id' => 7,
    ]);
    $appender = ListAppender::create([
        'user_id' => $user->id,
        'target_list_id' => $list->id,
        'command' => 'sighting',
        'value_template' => "We've logged a sighting",
        'dedup_policy' => ListAppender::DEDUP_NONE,
        'success_reply' => '[[[bot:from_user|mention]]], your sighting [[[c:list:sightings:count]]] has been logged.',
    ]);

    $result = app(ListAppendService::class)->fire($appender, $user, [
        'channel_login' => 'sighting_streamer',
        'command' => 'sighting',
        'chatter_id' => '99',
        'chatter_login' => 'alice',
        'chatter_display_name' => 'Alice',
        'args' => '',
    ]);

    // The 7th item was just appended, so the reply must read 7 (not 6).
    expect($result['fired'])->toBeTrue()
        ->and($result['reply'])->toBe('@Alice, your sighting 7 has been logged.');

    $outbox = BotChatOutbox::where('user_id', $user->id)->first();
    expect($outbox)->not->toBeNull()
        ->and($outbox->message)->toBe('@Alice, your sighting 7 has been logged.');
});
