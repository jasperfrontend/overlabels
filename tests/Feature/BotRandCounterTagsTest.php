<?php

use App\Events\ControlValueUpdated;
use App\Models\BotCommand;
use App\Models\OverlayControl;
use App\Models\User;
use App\Services\Bot\BotCommandResolver;
use App\Services\Bot\BotCommandService;
use App\Services\Bot\BotCommandValidator;
use App\Services\Bot\BotCounterService;
use App\Services\TwitchApiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Stub the Twitch fetch so the resolver makes no HTTP calls; rand: and
    // counter: need no token, but resolve() still loads Helix for bare tags.
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

function makeTagUser(): User
{
    return User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => 'streamer'],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

function fireCommand(User $user, string $body): string
{
    $command = BotCommand::create([
        'user_id' => $user->id,
        'command' => 'tagtest'.fake()->unique()->randomNumber(5),
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'reply' => $body,
        'enabled' => true,
        'hidden' => false,
    ]);

    return app(BotCommandService::class)->fire($command, []);
}

// ──────────────────────────────────────────────────────────────────────────────
// rand: - a pure inline read
// ──────────────────────────────────────────────────────────────────────────────

it('resolves rand: to a number inside the requested range', function () {
    $user = makeTagUser();
    $resolver = app(BotCommandResolver::class);

    // Rolled repeatedly because a single roll can pass by luck.
    for ($i = 0; $i < 40; $i++) {
        $value = $resolver->resolve($user, '[[[rand:0-69]]]');
        expect($value)->toMatch('/^\d+$/');
        expect((int) $value)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(69);
    }
});

it('resolves a single-value range to exactly that value', function () {
    $user = makeTagUser();

    expect(app(BotCommandResolver::class)->resolve($user, '[[[rand:7-7]]]'))->toBe('7');
});

it('rolls each rand: occurrence independently', function () {
    $user = makeTagUser();
    $resolver = app(BotCommandResolver::class);

    // Two tags over a wide range landing on the same number 30 times running
    // would mean one roll is being reused for both occurrences.
    $sawDifferent = false;
    for ($i = 0; $i < 30 && ! $sawDifferent; $i++) {
        [$a, $b] = explode('|', $resolver->resolve($user, '[[[rand:0-1000000]]]|[[[rand:0-1000000]]]'));
        $sawDifferent = $a !== $b;
    }

    expect($sawDifferent)->toBeTrue();
});

it('renders rand: inside a sentence and through a pipe', function () {
    $user = makeTagUser();
    $resolver = app(BotCommandResolver::class);

    expect($resolver->resolve($user, 'your Steven Level is [[[rand:5-5]]]%! Kappa.'))
        ->toBe('your Steven Level is 5%! Kappa.');

    // Composing with the existing formatters is free because rand: is an
    // ordinary tag rather than a special form.
    expect($resolver->resolve($user, '[[[rand:1000000-1000000|number]]]'))->toBe('1,000,000');
});

// ──────────────────────────────────────────────────────────────────────────────
// rand: - ranges are validated at authoring time, negatives refused
// ──────────────────────────────────────────────────────────────────────────────

it('refuses to save a command carrying a negative rand range', function (string $body) {
    $user = makeTagUser();

    expect(fn () => app(BotCommandValidator::class)->validateAndNormalize($user->id, [
        'command' => 'steven',
        'reply' => $body,
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'enabled' => true,
        'hidden' => false,
    ]))->toThrow(ValidationException::class);
})->with([
    'negative low bound' => 'level: [[[rand:-5-5]]]',
    'negative high bound' => 'level: [[[rand:5--5]]]',
    'both negative' => 'level: [[[rand:-9--1]]]',
    'non-numeric' => 'level: [[[rand:a-b]]]',
    'single bound' => 'level: [[[rand:69]]]',
    'empty range' => 'level: [[[rand:]]]',
]);

it('accepts a well-formed range and normalises a reversed one', function () {
    $user = makeTagUser();

    $data = app(BotCommandValidator::class)->validateAndNormalize($user->id, [
        'command' => 'steven',
        'reply' => 'level: [[[rand:0-69]]]',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'enabled' => true,
        'hidden' => false,
    ]);

    expect($data['command'])->toBe('steven');

    // High-first is accepted and swapped, matching OverlayControl's random mode.
    $value = (int) app(BotCommandResolver::class)->resolve($user, '[[[rand:69-0]]]');
    expect($value)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(69);
});

// ──────────────────────────────────────────────────────────────────────────────
// counter: - increments once per fire, reads the post-increment value
// ──────────────────────────────────────────────────────────────────────────────

it('creates the counter control on first fire and counts from one', function () {
    $user = makeTagUser();

    $message = fireCommand($user, 'So far, Jasper has won [[[counter:wins]]] times');

    expect($message)->toBe('So far, Jasper has won 1 times');

    $control = OverlayControl::where('user_id', $user->id)->where('key', 'wins')->first();
    expect($control)->not->toBeNull()
        ->and($control->type)->toBe('counter')
        ->and($control->value)->toBe('1')
        // User-scoped so every overlay can read it, and never source-managed
        // or !set / !reset could not reach it.
        ->and($control->overlay_template_id)->toBeNull()
        ->and($control->source_managed)->toBeFalse();
});

it('increments by one on each subsequent fire', function () {
    $user = makeTagUser();

    expect(fireCommand($user, '[[[counter:wins]]]'))->toBe('1');
    expect(fireCommand($user, '[[[counter:wins]]]'))->toBe('2');
    expect(fireCommand($user, '[[[counter:wins]]]'))->toBe('3');

    expect(OverlayControl::where('user_id', $user->id)->where('key', 'wins')->value('value'))->toBe('3');
});

it('counts once even when the tag appears twice in one message', function () {
    $user = makeTagUser();

    // The bump is driven by the deduplicated key list, not by tag occurrences.
    $message = fireCommand($user, 'win [[[counter:wins]]] - that is [[[counter:wins]]] total');

    expect($message)->toBe('win 1 - that is 1 total');
    expect(OverlayControl::where('user_id', $user->id)->where('key', 'wins')->value('value'))->toBe('1');
});

it('bumps each distinct counter in one reply exactly once', function () {
    $user = makeTagUser();

    fireCommand($user, '[[[counter:wins]]] wins, [[[counter:losses]]] losses');

    expect(OverlayControl::where('user_id', $user->id)->where('key', 'wins')->value('value'))->toBe('1')
        ->and(OverlayControl::where('user_id', $user->id)->where('key', 'losses')->value('value'))->toBe('1');
});

it('reads with c: without incrementing', function () {
    $user = makeTagUser();

    fireCommand($user, '[[[counter:wins]]]');

    // The whole teachable distinction: counter: counts, c: only looks.
    expect(fireCommand($user, 'total so far: [[[c:wins]]]'))->toBe('total so far: 1');
    expect(OverlayControl::where('user_id', $user->id)->where('key', 'wins')->value('value'))->toBe('1');
});

it('continues an existing control instead of resetting it', function () {
    $user = makeTagUser();
    OverlayControl::create([
        'overlay_template_id' => null,
        'user_id' => $user->id,
        'key' => 'wins',
        'type' => 'counter',
        'value' => '41',
        'source_managed' => false,
    ]);

    expect(fireCommand($user, '[[[counter:wins]]]'))->toBe('42');
    expect(OverlayControl::where('user_id', $user->id)->where('key', 'wins')->count())->toBe(1);
});

it('broadcasts the new value so overlays move with chat', function () {
    Event::fake([ControlValueUpdated::class]);
    $user = makeTagUser();

    fireCommand($user, '[[[counter:wins]]]');

    Event::assertDispatched(ControlValueUpdated::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// counter: - the read stays pure. This is the load-bearing part of the design.
// ──────────────────────────────────────────────────────────────────────────────

it('does not increment when the resolver runs on its own', function () {
    $user = makeTagUser();
    app(BotCounterService::class)->provision($user, '[[[counter:wins]]]');

    // The builder preview and the validator both resolve without firing. If
    // the increment ever moves into BotCommandResolver::lookup(), this
    // fails - which is exactly what it is here to catch.
    $resolver = app(BotCommandResolver::class);
    $resolver->resolve($user, '[[[counter:wins]]]');
    $resolver->resolve($user, '[[[counter:wins]]]', [], dryRun: true);

    expect(OverlayControl::where('user_id', $user->id)->where('key', 'wins')->value('value'))->toBe('0');
});

it('does not increment when previewing through the settings endpoint', function () {
    $user = makeTagUser();
    app(BotCounterService::class)->provision($user, '[[[counter:wins]]]');

    $this->actingAs($user)
        ->post(route('settings.bot.commands.preview'), [
            'reply' => 'wins: [[[counter:wins]]]',
        ])
        ->assertOk();

    expect(OverlayControl::where('user_id', $user->id)->where('key', 'wins')->value('value'))->toBe('0');
});

// ──────────────────────────────────────────────────────────────────────────────
// counter: - key validation and control-type conflicts
// ──────────────────────────────────────────────────────────────────────────────

it('refuses an unusable counter key', function (string $key) {
    $user = makeTagUser();

    expect(fn () => app(BotCommandValidator::class)->validateAndNormalize($user->id, [
        'command' => 'wins',
        'reply' => "count: [[[counter:$key]]]",
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'enabled' => true,
        'hidden' => false,
    ]))->toThrow(ValidationException::class);
})->with([
    // Dashes are banned in control identifiers project-wide.
    'dashed' => 'my-wins',
    'leading digit' => '1wins',
    'uppercase' => 'Wins',
    // Service namespaces would collide with kofi: / twitch: style keys.
    'reserved source name' => 'kofi',
]);

it('refuses a counter tag pointing at a control that cannot hold a count', function () {
    $user = makeTagUser();
    OverlayControl::create([
        'overlay_template_id' => null,
        'user_id' => $user->id,
        'key' => 'motto',
        'type' => 'text',
        'value' => 'hello',
        'source_managed' => false,
    ]);

    expect(fn () => app(BotCommandValidator::class)->validateAndNormalize($user->id, [
        'command' => 'motto',
        'reply' => '[[[counter:motto]]]',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'enabled' => true,
        'hidden' => false,
    ]))->toThrow(ValidationException::class);
});

it('leaves a non-numeric control alone rather than corrupting it on fire', function () {
    $user = makeTagUser();
    OverlayControl::create([
        'overlay_template_id' => null,
        'user_id' => $user->id,
        'key' => 'motto',
        'type' => 'text',
        'value' => 'hello',
        'source_managed' => false,
    ]);

    // The validator refuses this at authoring time, so reaching fire() means a
    // row that predates the check. Silent-on-block: skip, never overwrite.
    fireCommand($user, '[[[counter:motto]]]');

    expect(OverlayControl::where('user_id', $user->id)->where('key', 'motto')->value('value'))->toBe('hello');
});

// ──────────────────────────────────────────────────────────────────────────────
// Scoping - a counter belongs to one streamer
// ──────────────────────────────────────────────────────────────────────────────

it('never touches another user\'s counter of the same name', function () {
    $mine = makeTagUser();
    $theirs = makeTagUser();
    app(BotCounterService::class)->provision($theirs, '[[[counter:wins]]]');

    fireCommand($mine, '[[[counter:wins]]]');

    expect(OverlayControl::where('user_id', $mine->id)->where('key', 'wins')->value('value'))->toBe('1')
        ->and(OverlayControl::where('user_id', $theirs->id)->where('key', 'wins')->value('value'))->toBe('0');
});

it('never touches a service-managed control of the same name', function () {
    $user = makeTagUser();
    $managed = OverlayControl::provisionServiceControl($user, 'kofi', [
        'key' => 'donations_received',
        'type' => 'counter',
        'value' => '17',
    ]);

    fireCommand($user, '[[[counter:donations_received]]]');

    // The Ko-fi counter is untouched; a separate user-scoped one was created.
    expect($managed->fresh()->value)->toBe('17');
    expect(OverlayControl::where('user_id', $user->id)
        ->where('key', 'donations_received')
        ->where('source_managed', false)
        ->value('value'))->toBe('1');
});

// ──────────────────────────────────────────────────────────────────────────────
// Provisioning is idempotent
// ──────────────────────────────────────────────────────────────────────────────

// ──────────────────────────────────────────────────────────────────────────────
// Near misses are caught while writing, not live in front of an audience.
//
// Every case here used to save cleanly and then fail silently or print
// brackets at chat. What is pinned is that saving is REFUSED and that the
// reason names the actual mistake - a generic "invalid reply" would pass
// the first half of that and be no more use than the silence it replaced.
// ──────────────────────────────────────────────────────────────────────────────

function expectSaveRefused(User $user, string $body): string
{
    try {
        app(BotCommandValidator::class)->validateAndNormalize($user->id, [
            'command' => 'oops',
            'reply' => $body,
            'permission_level' => 'everyone',
            'cooldown_seconds' => 0,
            'enabled' => true,
            'hidden' => false,
        ]);
    } catch (ValidationException $e) {
        return implode(' ', $e->errors()['reply'] ?? []);
    }

    throw new Exception("expected '$body' to be refused, but it saved");
}

it('suggests the right tag when a namespace is misspelled', function (string $body, string $wanted) {
    // These resolved to empty and the number just vanished mid-sentence.
    expect(expectSaveRefused(makeTagUser(), $body))->toContain("Did you mean '$wanted'?");
})->with([
    'rnd' => ['level: [[[rnd:0-69]]]', 'rand'],
    'random' => ['level: [[[random:0-69]]]', 'rand'],
    'countr' => ['won [[[countr:wins]]]', 'counter'],
    'counters' => ['won [[[counters:wins]]]', 'counter'],
    'bto' => ['hi [[[bto:from_user]]]', 'bot'],
]);

it('lists the usable tags when a namespace is nothing like a real one', function () {
    $message = expectSaveRefused(makeTagUser(), 'hello [[[zzzzzzzz:wins]]]');

    expect($message)->toContain('rand')->toContain('counter')->not->toContain('Did you mean');
});

it('refuses a bracket run it cannot read, which chat would print verbatim', function (string $body) {
    expect(expectSaveRefused(makeTagUser(), $body))->toContain('chat would see it exactly as written');
})->with([
    'space instead of colon' => 'won [[[counter wins]]] times',
    'space in rand' => 'level [[[rand 0-69]]]',
    'unterminated' => 'won [[[counter:wins times',
]);

it('refuses a tag with too few brackets and shows the fixed version', function () {
    expect(expectSaveRefused(makeTagUser(), 'level: [[rand:0-69]]'))
        ->toContain('three brackets')
        ->toContain('[[[rand:0-69]]]');
});

it('leaves ordinary bracketed prose alone', function () {
    // The under-bracket check only fires on something that really is a tag,
    // so this must still save.
    $data = app(BotCommandValidator::class)->validateAndNormalize(makeTagUser()->id, [
        'command' => 'shrug',
        'reply' => '[[shrug]] well, [[citation needed]]',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'enabled' => true,
        'hidden' => false,
    ]);

    expect($data['reply'])->toBe('[[shrug]] well, [[citation needed]]');
});

it('still accepts every namespace that really works in chat', function (string $body) {
    $user = makeTagUser();

    $data = app(BotCommandValidator::class)->validateAndNormalize($user->id, [
        'command' => 'fine',
        'reply' => $body,
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'enabled' => true,
        'hidden' => false,
    ]);

    expect($data['reply'])->toBe($body);
})->with([
    'control' => '[[[c:wins]]]',
    'list' => '[[[c:list:donors:count]]]',
    'bot context' => 'hi [[[bot:from_user]]]',
    'positional arg' => 'you said [[[bot:args.0]]]',
    'bare twitch tag' => 'title: [[[channel_title]]]',
    'pipe' => '[[[rand:0-1000000|number]]]',
    'default value' => '[[[c:wins ?? nobody yet]]]',
    'everything at once' => '[[[bot:from_user]]] rolled [[[rand:1-6]]], now on [[[counter:wins]]] ([[[c:list:donors:count]]] donors)',
]);

it('provisions idempotently and reports only what it created', function () {
    $user = makeTagUser();
    $counters = app(BotCounterService::class);

    expect($counters->provision($user, '[[[counter:wins]]]'))->toBe(['wins']);
    expect($counters->provision($user, '[[[counter:wins]]]'))->toBe([]);
    expect($counters->provision($user, '[[[counter:wins]]] [[[counter:losses]]]'))->toBe(['losses']);

    expect(OverlayControl::where('user_id', $user->id)->whereIn('key', ['wins', 'losses'])->count())->toBe(2);
});
