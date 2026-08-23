<?php

use App\Models\BotCommand;
use App\Models\ListAppender;
use App\Models\ListMetaCommand;
use App\Models\OptionSet;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Support\WiringCatalog;
use App\Support\WiringFacts;
use App\Support\WiringReport;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;

uses(DatabaseTransactions::class);

function wiringUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'bot_enabled' => false,
    ], $attrs));
}

function wiringList(User $user, ?string $slug = null, array $attrs = []): OptionSet
{
    return OptionSet::create(array_merge([
        'user_id' => $user->id,
        'slug' => $slug ?? 'list_'.fake()->unique()->lexify('??????'),
        'label' => 'Raffle',
        'items' => [],
    ], $attrs));
}

function wiringAppender(User $user, OptionSet $list, bool $enabled = true): ListAppender
{
    return ListAppender::create([
        'user_id' => $user->id,
        'target_list_id' => $list->id,
        'command' => 'enter',
        'permission_level' => 'everyone',
        'enabled' => $enabled,
    ]);
}

/** @return list<array<string, mixed>> */
function listSubjects(User $user): array
{
    return WiringFacts::for($user)['lists'];
}

function listState(User $user, string $slug): string
{
    $subject = collect(listSubjects($user))->firstWhere('key', 'list:'.$slug);

    return $subject['states']['lists.readable'];
}

// ──────────────────────────────────────────────────────────────────────────────
// Drift guards
// ──────────────────────────────────────────────────────────────────────────────

test('every wire a circuit references is declared', function () {
    foreach (WiringCatalog::CIRCUITS as $key => $circuit) {
        foreach ($circuit['wires'] as $wireKey) {
            expect(array_key_exists($wireKey, WiringCatalog::WIRES))
                ->toBeTrue("circuit '{$key}' references undeclared wire '{$wireKey}'");
        }
    }
});

test('every circuit has facts produced for it', function () {
    // Evaluating a circuit reads $facts[$key]; one with no producer would
    // render as permanently empty and nothing would say why.
    $facts = WiringFacts::for(wiringUser());

    expect(array_keys($facts))->toEqualCanonicalizing(WiringCatalog::circuitKeys());
});

test('every wire points at a route that exists', function () {
    foreach (WiringCatalog::WIRES as $key => $wire) {
        expect(Route::has($wire['route']))
            ->toBeTrue("wire '{$key}' points at unknown route '{$wire['route']}'");
    }
});

test('every wire carries copy for satisfied and missing', function () {
    // not_applicable may be blank - some questions simply do not arise - but
    // a state the page renders must never come out empty.
    foreach (WiringCatalog::WIRES as $key => $wire) {
        expect($wire['satisfied'])->not->toBeEmpty("wire '{$key}' has no satisfied copy")
            ->and($wire['missing'])->not->toBeEmpty("wire '{$key}' has no missing copy");
    }
});

// ──────────────────────────────────────────────────────────────────────────────
// Optional is not missing - what the first cut got wrong
// ──────────────────────────────────────────────────────────────────────────────

test('a list with no append command is not a finding', function () {
    // Production had two such lists and both were correct: one fed by the
    // recent-events feed, one a counter. Requiring an appender called two
    // working setups broken.
    $user = wiringUser(['bot_enabled' => true]);
    wiringList($user, 'subgoal');
    ListMetaCommand::create(['user_id' => $user->id, 'command' => 'list', 'enabled' => true]);

    expect(listState($user, 'subgoal'))->toBe(WiringCatalog::SATISFIED);

    $subject = collect(listSubjects($user))->firstWhere('key', 'list:subgoal');
    expect($subject['context'])->toContain('You fill this one from the dashboard');
});

test('an event-feed list reports what fills it instead of demanding a command', function () {
    $user = wiringUser();
    wiringList($user, 'events', ['label' => 'Recent events', 'event_feed' => ['enabled' => true]]);

    $subject = collect(listSubjects($user))->firstWhere('key', 'list:events');

    expect($subject['context'])->toContain('Filled by the recent-events feed')
        ->and($subject['context'])->not->toContain('You fill this one from the dashboard');
});

test('the bot wire is not applicable when there are no chat commands', function () {
    $facts = WiringFacts::for(wiringUser());

    expect($facts['bot'][0]['states']['bot.in_chat'])->toBe(WiringCatalog::NOT_APPLICABLE);
});

test('the bot wire only becomes a finding once commands exist', function () {
    $user = wiringUser();
    wiringAppender($user, wiringList($user));

    expect(WiringFacts::for($user)['bot'][0]['states']['bot.in_chat'])->toBe(WiringCatalog::MISSING);

    $user->update(['bot_enabled' => true]);

    expect(WiringFacts::for($user->fresh())['bot'][0]['states']['bot.in_chat'])->toBe(WiringCatalog::SATISFIED);
});

// ──────────────────────────────────────────────────────────────────────────────
// Readability, per list
// ──────────────────────────────────────────────────────────────────────────────

test('a list nothing reads is a finding', function () {
    $user = wiringUser();
    wiringList($user, 'orphan');

    expect(listState($user, 'orphan'))->toBe(WiringCatalog::MISSING);
});

test('the list meta-command makes every list readable at once', function () {
    // Its vocabulary takes a slug, so one command covers all lists rather
    // than one each.
    $user = wiringUser();
    wiringList($user, 'one');
    wiringList($user, 'two');

    expect(listState($user, 'one'))->toBe(WiringCatalog::MISSING);

    ListMetaCommand::create(['user_id' => $user->id, 'command' => 'list', 'enabled' => true]);

    expect(listState($user, 'one'))->toBe(WiringCatalog::SATISFIED)
        ->and(listState($user, 'two'))->toBe(WiringCatalog::SATISFIED);
});

test('an overlay that renders the list makes it readable', function () {
    $user = wiringUser();
    wiringList($user, 'donors');

    expect(listState($user, 'donors'))->toBe(WiringCatalog::MISSING);

    OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'static',
        'html' => '<div>[[[foreach:c:list:donors as d]]][[[d]]][[[endforeach]]]</div>',
    ]);

    expect(listState($user, 'donors'))->toBe(WiringCatalog::SATISFIED);
});

test('a bot command that reads the list makes it readable', function () {
    $user = wiringUser();
    wiringList($user, 'quotes');

    BotCommand::create([
        'user_id' => $user->id,
        'command' => 'quote',
        'permission_level' => 'everyone',
        'reply' => 'Random quote: [[[c:list:quotes:random]]]',
        'enabled' => true,
    ]);

    expect(listState($user, 'quotes'))->toBe(WiringCatalog::SATISFIED);
});

test('a longer slug sharing a prefix does not satisfy the shorter one', function () {
    // Without the boundary, list `q` would report itself as read by any
    // template mentioning `c:list:quotes`.
    $user = wiringUser();
    wiringList($user, 'q');
    wiringList($user, 'quotes');

    OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'static',
        'html' => '<div>[[[c:list:quotes]]]</div>',
    ]);

    expect(listState($user, 'quotes'))->toBe(WiringCatalog::SATISFIED)
        ->and(listState($user, 'q'))->toBe(WiringCatalog::MISSING);
});

test('another user\'s overlay never makes your list readable', function () {
    $me = wiringUser();
    $them = wiringUser();
    wiringList($me, 'mine');

    OverlayTemplate::factory()->create([
        'owner_id' => $them->id,
        'fork_of_id' => null,
        'type' => 'static',
        'html' => '<div>[[[c:list:mine]]]</div>',
    ]);

    expect(listState($me, 'mine'))->toBe(WiringCatalog::MISSING);
});

test('a disabled bot command does not make a list readable', function () {
    $user = wiringUser();
    wiringList($user, 'quotes');

    BotCommand::create([
        'user_id' => $user->id,
        'command' => 'quote',
        'permission_level' => 'everyone',
        'reply' => '[[[c:list:quotes:random]]]',
        'enabled' => false,
    ]);

    expect(listState($user, 'quotes'))->toBe(WiringCatalog::MISSING);
});

test('facts follow the account rather than a stored record', function () {
    $user = wiringUser();
    $list = wiringList($user, 'temp');

    expect(listSubjects($user))->toHaveCount(1);

    $list->delete();

    expect(listSubjects($user->fresh()))->toBeEmpty();
});

// ──────────────────────────────────────────────────────────────────────────────
// Ranking
// ──────────────────────────────────────────────────────────────────────────────

test('not_applicable counts as neither progress nor a gap', function () {
    $report = WiringReport::build([
        'bot' => [['key' => 'account', 'label' => 'Your channel', 'context' => [], 'states' => ['bot.in_chat' => WiringCatalog::NOT_APPLICABLE]]],
        'lists' => [],
    ]);

    $bot = collect($report)->firstWhere('key', 'bot');

    // Not a gap: attention stays 0. Not progress either: the subject is not
    // applicable, so the circuit is not_started rather than complete, and the
    // page renders a neutral mark instead of a tick.
    expect($bot['attention'])->toBe(0)
        ->and($bot['subjects'][0]['applicable'])->toBeFalse()
        ->and($bot['status'])->toBe(WiringReport::NOT_STARTED);
});

test('no subjects at all is not_started and stays quiet', function () {
    $report = WiringReport::build(['bot' => [], 'lists' => []]);

    expect(collect($report)->firstWhere('key', 'lists')['status'])->toBe(WiringReport::NOT_STARTED);
});

test('broken subjects sort above healthy ones', function () {
    $report = WiringReport::build([
        'bot' => [],
        'lists' => [
            ['key' => 'list:aaa', 'label' => 'Aaa', 'context' => [], 'states' => ['lists.readable' => WiringCatalog::SATISFIED]],
            ['key' => 'list:zzz', 'label' => 'Zzz', 'context' => [], 'states' => ['lists.readable' => WiringCatalog::MISSING]],
        ],
    ]);

    $lists = collect($report)->firstWhere('key', 'lists');

    expect($lists['subjects'][0]['key'])->toBe('list:zzz')
        ->and($lists['status'])->toBe(WiringReport::LOOSE_END)
        ->and($lists['attention'])->toBe(1);
});

test('a circuit with a loose end sorts above one without', function () {
    $report = WiringReport::build([
        'bot' => [['key' => 'account', 'label' => 'Your channel', 'context' => [], 'states' => ['bot.in_chat' => WiringCatalog::SATISFIED]]],
        'lists' => [
            ['key' => 'list:a', 'label' => 'A', 'context' => [], 'states' => ['lists.readable' => WiringCatalog::MISSING]],
        ],
    ]);

    expect($report[0]['key'])->toBe('lists');
});

test('a finding carries the consequence and a way to fix it', function () {
    $report = WiringReport::build([
        'bot' => [],
        'lists' => [
            ['key' => 'list:a', 'label' => 'A', 'context' => [], 'states' => ['lists.readable' => WiringCatalog::MISSING]],
        ],
    ]);

    $wire = collect($report)->firstWhere('key', 'lists')['subjects'][0]['wires'][0];

    expect($wire['state'])->toBe(WiringCatalog::MISSING)
        ->and($wire['message'])->not->toBeEmpty()
        ->and($wire['cta'])->not->toBeEmpty();
});

// ──────────────────────────────────────────────────────────────────────────────
// The page
// ──────────────────────────────────────────────────────────────────────────────

test('the wiring page requires a login', function () {
    $this->get('/wiring')->assertRedirect();
});

test('the page counts loose ends in subjects, not areas', function () {
    $user = wiringUser(['bot_enabled' => true]);
    wiringList($user, 'orphan_one');
    wiringList($user, 'orphan_two');

    $this->actingAs($user)
        ->get('/wiring')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('wiring/index')
                ->where('looseEnds', 2)
                ->where('circuits.0.key', 'lists')
                ->where('circuits.0.status', WiringReport::LOOSE_END)
        );
});

test('a fully wired account reports nothing to do', function () {
    $user = wiringUser(['bot_enabled' => true]);
    $list = wiringList($user, 'raffle');
    wiringAppender($user, $list);
    ListMetaCommand::create(['user_id' => $user->id, 'command' => 'list', 'enabled' => true]);

    $this->actingAs($user)
        ->get('/wiring')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('looseEnds', 0));
});
