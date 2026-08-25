<?php

use App\Models\OverlayControl;
use App\Models\User;
use App\Services\Bot\BotCommandResolver;
use App\Services\Bot\BotCommandValidator;
use App\Services\TwitchApiService;
use App\Support\BotTags;
use App\Support\Conditionals;
use App\Support\Dsl;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;

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

function condUser(): User
{
    return User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => 'streamer'],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

function condControl(User $user, string $key, string $value, string $type = 'counter'): void
{
    OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => $key,
        'label' => $key,
        'type' => $type,
        'value' => $value,
        'sort_order' => 0,
    ]);
}

function condResolve(User $user, string $reply, array $context = []): string
{
    return app(BotCommandResolver::class)->resolve($user, $reply, $context, dryRun: true);
}

function condSave(User $user, string $reply): string
{
    try {
        $data = app(BotCommandValidator::class)->validateAndNormalize($user->id, [
            'command' => 'cond'.fake()->unique()->randomNumber(5),
            'reply' => $reply,
            'permission_level' => 'everyone',
            'cooldown_seconds' => 0,
            'enabled' => true,
            'hidden' => false,
        ]);
    } catch (ValidationException $e) {
        return 'REFUSED: '.implode(' ', $e->errors()['reply'] ?? []);
    }

    return $data['reply'];
}

// ──────────────────────────────────────────────────────────────────────────────
// The one that started it: "1 times"
// ──────────────────────────────────────────────────────────────────────────────

it('pluralises with an inline if, so the bot never says "1 times"', function () {
    $user = condUser();
    $reply = 'Hi [[[bot:from_user|mention]]]! we have counted [[[c:countuplol]]] time[[[if:c:countuplol != 1]]]s[[[endif]]]';

    condControl($user, 'countuplol', '1');
    expect(condResolve($user, $reply, ['from_user' => 'jasper']))
        ->toBe('Hi @jasper! we have counted 1 time');

    OverlayControl::where('user_id', $user->id)->where('key', 'countuplol')->update(['value' => '2']);
    expect(condResolve($user, $reply, ['from_user' => 'jasper']))
        ->toBe('Hi @jasper! we have counted 2 times');
});

it('treats a counter that does not exist yet as 0 in a comparison, like the overlay does', function () {
    $user = condUser();

    expect(condResolve($user, 'time[[[if:c:nope != 1]]]s[[[endif]]]'))->toBe('times');
    expect(condResolve($user, '[[[if:c:nope > 0]]]some[[[else]]]none[[[endif]]]'))->toBe('none');
});

// ──────────────────────────────────────────────────────────────────────────────
// Branch selection
// ──────────────────────────────────────────────────────────────────────────────

it('takes the first true branch of if / elseif / else', function () {
    $user = condUser();
    condControl($user, 'wins', '5');
    $reply = '[[[if:c:wins >= 10]]]on fire[[[elseif:c:wins >= 3]]]warming up[[[else]]]just started[[[endif]]]';

    expect(condResolve($user, $reply))->toBe('warming up');

    OverlayControl::where('user_id', $user->id)->where('key', 'wins')->update(['value' => '12']);
    expect(condResolve($user, $reply))->toBe('on fire');

    OverlayControl::where('user_id', $user->id)->where('key', 'wins')->update(['value' => '0']);
    expect(condResolve($user, $reply))->toBe('just started');
});

it('renders nothing for an if with no else when the condition is false', function () {
    $user = condUser();
    condControl($user, 'wins', '1');

    expect(condResolve($user, 'a[[[if:c:wins > 5]]]b[[[endif]]]c'))->toBe('ac');
});

it('evaluates a bare key for truthiness with the overlay rules', function (string $value, string $expected) {
    $user = condUser();
    condControl($user, 'flag', $value, 'text');

    expect(condResolve($user, '[[[if:c:flag]]]yes[[[else]]]no[[[endif]]]'))->toBe($expected);
})->with([
    'one' => ['1', 'yes'],
    'word' => ['hello', 'yes'],
    'zero' => ['0', 'no'],
    'false' => ['false', 'no'],
    'empty' => ['', 'no'],
]);

it('compares strings with = and !=, quotes optional', function () {
    $user = condUser();
    condControl($user, 'mood', 'hype', 'text');

    expect(condResolve($user, '[[[if:c:mood = hype]]]LETS GO[[[endif]]]'))->toBe('LETS GO');
    expect(condResolve($user, '[[[if:c:mood = "hype"]]]LETS GO[[[endif]]]'))->toBe('LETS GO');
    expect(condResolve($user, '[[[if:c:mood != hype]]]calm[[[else]]]loud[[[endif]]]'))->toBe('loud');
});

it('compares numbers numerically, not as strings', function () {
    $user = condUser();
    condControl($user, 'wins', '10');

    // As strings "10" < "9"; as numbers it is not.
    expect(condResolve($user, '[[[if:c:wins > 9]]]big[[[else]]]small[[[endif]]]'))->toBe('big');
});

it('reads bot context and other namespaces inside a condition', function () {
    $user = condUser();

    expect(condResolve($user, '[[[if:bot:args.0 = hi]]]hello back[[[endif]]]', ['args.0' => 'hi']))->toBe('hello back');
    expect(condResolve($user, '[[[if:bot:args.0]]]got args[[[else]]]no args[[[endif]]]', []))->toBe('no args');
});

it('handles a nested if inside the chosen branch', function () {
    $user = condUser();
    condControl($user, 'wins', '5');
    condControl($user, 'streak', '3');
    $reply = '[[[if:c:wins > 0]]]wins[[[if:c:streak > 2]]] on a streak[[[endif]]][[[else]]]none[[[endif]]]';

    expect(condResolve($user, $reply))->toBe('wins on a streak');
});

it('pairs else with the right if when nested', function () {
    $user = condUser();
    condControl($user, 'outer', '1');
    condControl($user, 'inner', '0');
    $reply = '[[[if:c:outer]]]O[[[if:c:inner]]]I[[[else]]]i[[[endif]]][[[else]]]o[[[endif]]]';

    expect(condResolve($user, $reply))->toBe('Oi');
});

it('substitutes tags inside the surviving branch only once', function () {
    $user = condUser();
    condControl($user, 'wins', '3');

    expect(condResolve($user, '[[[if:c:wins]]]you have [[[c:wins]]] wins[[[endif]]]'))->toBe('you have 3 wins');
});

it('never rescans a control value that looks like a block token', function () {
    $user = condUser();
    condControl($user, 'evil', '[[[if:c:secret]]]leak[[[endif]]]', 'text');
    condControl($user, 'secret', '1');

    expect(condResolve($user, 'value: [[[c:evil]]]'))->toBe('value: [[[if:c:secret]]]leak[[[endif]]]');
});

it('does not count a counter that is only read inside a condition', function () {
    // Conditions look, they do not declare an increment.
    expect(BotTags::counterKeys('[[[if:counter:wins > 3]]]lots[[[endif]]]'))->toBe([]);
    expect(BotTags::counterKeys('[[[if:c:wins > 3]]]win [[[counter:wins]]][[[endif]]]'))->toBe(['wins']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Save-time structure checks
// ──────────────────────────────────────────────────────────────────────────────

it('saves a well-formed conditional', function () {
    $user = condUser();
    $reply = 'counted [[[c:n]]] time[[[if:c:n != 1]]]s[[[endif]]]';

    expect(condSave($user, $reply))->toBe($reply);
});

it('refuses an if with no endif', function () {
    expect(condSave(condUser(), 'you [[[if:c:wins > 3]]]have lots'))
        ->toStartWith('REFUSED')
        ->toContain('has no [[[endif]]]');
});

it('refuses a stray else, elseif or endif', function (string $reply) {
    expect(condSave(condUser(), $reply))
        ->toStartWith('REFUSED')
        ->toContain('has no [[[if:...]]] in front of it');
})->with([
    'endif' => 'hello [[[endif]]]',
    'else' => 'hello [[[else]]] there',
    'elseif' => 'hello [[[elseif:c:wins > 1]]] there',
]);

it('refuses a branch after else', function () {
    expect(condSave(condUser(), '[[[if:c:a]]]a[[[else]]]b[[[elseif:c:c]]]c[[[endif]]]'))
        ->toStartWith('REFUSED')
        ->toContain('comes after [[[else]]]');
});

it('refuses foreach with a reason, not a generic error', function () {
    expect(condSave(condUser(), '[[[foreach:subscribers as s]]][[[s.user_name]]] [[[endforeach]]]'))
        ->toStartWith('REFUSED')
        ->toContain("loops don't work here");
});

it('still catches a misspelled namespace when it is inside a condition', function () {
    expect(condSave(condUser(), '[[[if:cc:wins > 3]]]lots[[[endif]]]'))
        ->toStartWith('REFUSED')
        ->toContain("Did you mean 'c'");
});

it('does not mistake block tokens for unknown tags', function () {
    $user = condUser();
    $reply = '[[[if:c:flag]]]yes[[[else]]]no[[[endif]]]';

    expect(condSave($user, $reply))->toBe($reply);
});

// ──────────────────────────────────────────────────────────────────────────────
// The engine alone
// ──────────────────────────────────────────────────────────────────────────────

it('leaves an unmatched if as written rather than throwing', function () {
    $out = Conditionals::render('a [[[if:x]]] b', fn () => '1');

    expect($out)->toBe('a [[[if:x]]] b');
});

it('stops recursing past the shared nesting limit', function () {
    $depth = Dsl::maxNestingDepth() + 2;
    $text = str_repeat('[[[if:x]]]', $depth).'deep'.str_repeat('[[[endif]]]', $depth);

    // Must terminate and must not throw; exact output past the limit is not a contract.
    expect(Conditionals::render($text, fn () => '1'))->toBeString();
});

it('reports the keys conditions read', function () {
    expect(Conditionals::keys('[[[if:c:a > 1]]]x[[[elseif:bot:args.0]]]y[[[endif]]]'))
        ->toBe(['c:a', 'bot:args.0']);
});
