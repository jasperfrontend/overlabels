<?php

use App\Models\BotCommand;
use App\Models\ListAppender;
use App\Models\ListMetaCommand;
use App\Models\OptionSet;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Support\SkillCatalog;
use App\Support\SkillFacts;
use App\Support\SkillReport;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;

uses(DatabaseTransactions::class);

function skillUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'bot_enabled' => false,
    ], $attrs));
}

function skillList(User $user, ?string $slug = null, array $attrs = []): OptionSet
{
    return OptionSet::create(array_merge([
        'user_id' => $user->id,
        'slug' => $slug ?? 'list_'.fake()->unique()->lexify('??????'),
        'label' => 'Raffle',
        'items' => [],
    ], $attrs));
}

function skillAppender(User $user, OptionSet $list, bool $enabled = true): ListAppender
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
    return SkillFacts::for($user)['lists'];
}

function listState(User $user, string $slug): string
{
    $subject = collect(listSubjects($user))->firstWhere('key', 'list:'.$slug);

    return $subject['states']['lists.readable'];
}

// ──────────────────────────────────────────────────────────────────────────────
// Drift guards
// ──────────────────────────────────────────────────────────────────────────────

test('every skill a skillset references is declared', function () {
    foreach (SkillCatalog::SKILLSETS as $key => $set) {
        foreach ($set['skills'] as $skillKey) {
            expect(array_key_exists($skillKey, SkillCatalog::SKILLS))
                ->toBeTrue("skillset '{$key}' references undeclared skill '{$skillKey}'");
        }
    }
});

test('every skillset has facts produced for it', function () {
    // Evaluating a skillset reads $facts[$key]; one with no producer would
    // render as permanently empty and nothing would say why.
    $facts = SkillFacts::for(skillUser());

    expect(array_keys($facts))->toEqualCanonicalizing(SkillCatalog::skillsetKeys());
});

test('every skill points at a route that exists', function () {
    foreach (SkillCatalog::SKILLS as $key => $skill) {
        expect(Route::has($skill['route']))
            ->toBeTrue("skill '{$key}' points at unknown route '{$skill['route']}'");
    }
});

test('every skill carries copy for satisfied and missing', function () {
    // not_applicable may be blank - some questions simply do not arise - but
    // a state the page renders must never come out empty.
    foreach (SkillCatalog::SKILLS as $key => $skill) {
        expect($skill['satisfied'])->not->toBeEmpty("skill '{$key}' has no satisfied copy")
            ->and($skill['missing'])->not->toBeEmpty("skill '{$key}' has no missing copy");
    }
});

// ──────────────────────────────────────────────────────────────────────────────
// Optional is not missing - what the first cut got wrong
// ──────────────────────────────────────────────────────────────────────────────

test('a list with no append command is not a finding', function () {
    // Production had two such lists and both were correct: one fed by the
    // recent-events feed, one a counter. Requiring an appender called two
    // working setups broken.
    $user = skillUser(['bot_enabled' => true]);
    skillList($user, 'subgoal');
    ListMetaCommand::create(['user_id' => $user->id, 'command' => 'list', 'enabled' => true]);

    expect(listState($user, 'subgoal'))->toBe(SkillCatalog::SATISFIED);

    $subject = collect(listSubjects($user))->firstWhere('key', 'list:subgoal');
    expect($subject['context'])->toContain('You fill this one from the dashboard');
});

test('an event-feed list reports what fills it instead of demanding a command', function () {
    $user = skillUser();
    skillList($user, 'events', ['label' => 'Recent events', 'event_feed' => ['enabled' => true]]);

    $subject = collect(listSubjects($user))->firstWhere('key', 'list:events');

    expect($subject['context'])->toContain('Filled by the recent-events feed')
        ->and($subject['context'])->not->toContain('You fill this one from the dashboard');
});

test('the bot skill is not applicable when there are no chat commands', function () {
    $facts = SkillFacts::for(skillUser());

    expect($facts['bot'][0]['states']['bot.in_chat'])->toBe(SkillCatalog::NOT_APPLICABLE);
});

test('the bot skill only becomes a finding once commands exist', function () {
    $user = skillUser();
    skillAppender($user, skillList($user));

    expect(SkillFacts::for($user)['bot'][0]['states']['bot.in_chat'])->toBe(SkillCatalog::MISSING);

    $user->update(['bot_enabled' => true]);

    expect(SkillFacts::for($user->fresh())['bot'][0]['states']['bot.in_chat'])->toBe(SkillCatalog::SATISFIED);
});

// ──────────────────────────────────────────────────────────────────────────────
// Readability, per list
// ──────────────────────────────────────────────────────────────────────────────

test('a list nothing reads is a finding', function () {
    $user = skillUser();
    skillList($user, 'orphan');

    expect(listState($user, 'orphan'))->toBe(SkillCatalog::MISSING);
});

test('the list meta-command makes every list readable at once', function () {
    // Its vocabulary takes a slug, so one command covers all lists rather
    // than one each.
    $user = skillUser();
    skillList($user, 'one');
    skillList($user, 'two');

    expect(listState($user, 'one'))->toBe(SkillCatalog::MISSING);

    ListMetaCommand::create(['user_id' => $user->id, 'command' => 'list', 'enabled' => true]);

    expect(listState($user, 'one'))->toBe(SkillCatalog::SATISFIED)
        ->and(listState($user, 'two'))->toBe(SkillCatalog::SATISFIED);
});

test('an overlay that renders the list makes it readable', function () {
    $user = skillUser();
    skillList($user, 'donors');

    expect(listState($user, 'donors'))->toBe(SkillCatalog::MISSING);

    OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'static',
        'html' => '<div>[[[foreach:c:list:donors as d]]][[[d]]][[[endforeach]]]</div>',
    ]);

    expect(listState($user, 'donors'))->toBe(SkillCatalog::SATISFIED);
});

test('a bot command that reads the list makes it readable', function () {
    $user = skillUser();
    skillList($user, 'quotes');

    BotCommand::create([
        'user_id' => $user->id,
        'command' => 'quote',
        'permission_level' => 'everyone',
        'reply' => 'Random quote: [[[c:list:quotes:random]]]',
        'enabled' => true,
    ]);

    expect(listState($user, 'quotes'))->toBe(SkillCatalog::SATISFIED);
});

test('a longer slug sharing a prefix does not satisfy the shorter one', function () {
    // Without the boundary, list `q` would report itself as read by any
    // template mentioning `c:list:quotes`.
    $user = skillUser();
    skillList($user, 'q');
    skillList($user, 'quotes');

    OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'static',
        'html' => '<div>[[[c:list:quotes]]]</div>',
    ]);

    expect(listState($user, 'quotes'))->toBe(SkillCatalog::SATISFIED)
        ->and(listState($user, 'q'))->toBe(SkillCatalog::MISSING);
});

test('another user\'s overlay never makes your list readable', function () {
    $me = skillUser();
    $them = skillUser();
    skillList($me, 'mine');

    OverlayTemplate::factory()->create([
        'owner_id' => $them->id,
        'fork_of_id' => null,
        'type' => 'static',
        'html' => '<div>[[[c:list:mine]]]</div>',
    ]);

    expect(listState($me, 'mine'))->toBe(SkillCatalog::MISSING);
});

test('a disabled bot command does not make a list readable', function () {
    $user = skillUser();
    skillList($user, 'quotes');

    BotCommand::create([
        'user_id' => $user->id,
        'command' => 'quote',
        'permission_level' => 'everyone',
        'reply' => '[[[c:list:quotes:random]]]',
        'enabled' => false,
    ]);

    expect(listState($user, 'quotes'))->toBe(SkillCatalog::MISSING);
});

test('facts follow the account rather than a stored record', function () {
    $user = skillUser();
    $list = skillList($user, 'temp');

    expect(listSubjects($user))->toHaveCount(1);

    $list->delete();

    expect(listSubjects($user->fresh()))->toBeEmpty();
});

// ──────────────────────────────────────────────────────────────────────────────
// Ranking
// ──────────────────────────────────────────────────────────────────────────────

test('not_applicable counts as neither progress nor a gap', function () {
    $report = SkillReport::build([
        'bot' => [['key' => 'account', 'label' => 'Your channel', 'context' => [], 'states' => ['bot.in_chat' => SkillCatalog::NOT_APPLICABLE]]],
        'lists' => [],
    ]);

    $bot = collect($report)->firstWhere('key', 'bot');

    // Not a gap: attention stays 0. Not progress either: the subject is not
    // applicable, so the skillset is not_started rather than complete, and the
    // page renders a neutral mark instead of a tick.
    expect($bot['attention'])->toBe(0)
        ->and($bot['subjects'][0]['applicable'])->toBeFalse()
        ->and($bot['status'])->toBe(SkillReport::NOT_STARTED);
});

test('no subjects at all is not_started and stays quiet', function () {
    $report = SkillReport::build(['bot' => [], 'lists' => []]);

    expect(collect($report)->firstWhere('key', 'lists')['status'])->toBe(SkillReport::NOT_STARTED);
});

test('broken subjects sort above healthy ones', function () {
    $report = SkillReport::build([
        'bot' => [],
        'lists' => [
            ['key' => 'list:aaa', 'label' => 'Aaa', 'context' => [], 'states' => ['lists.readable' => SkillCatalog::SATISFIED]],
            ['key' => 'list:zzz', 'label' => 'Zzz', 'context' => [], 'states' => ['lists.readable' => SkillCatalog::MISSING]],
        ],
    ]);

    $lists = collect($report)->firstWhere('key', 'lists');

    expect($lists['subjects'][0]['key'])->toBe('list:zzz')
        ->and($lists['status'])->toBe(SkillReport::LOOSE_END)
        ->and($lists['attention'])->toBe(1);
});

test('a skillset with a loose end sorts above one without', function () {
    $report = SkillReport::build([
        'bot' => [['key' => 'account', 'label' => 'Your channel', 'context' => [], 'states' => ['bot.in_chat' => SkillCatalog::SATISFIED]]],
        'lists' => [
            ['key' => 'list:a', 'label' => 'A', 'context' => [], 'states' => ['lists.readable' => SkillCatalog::MISSING]],
        ],
    ]);

    expect($report[0]['key'])->toBe('lists');
});

test('a finding carries the consequence and a way to fix it', function () {
    $report = SkillReport::build([
        'bot' => [],
        'lists' => [
            ['key' => 'list:a', 'label' => 'A', 'context' => [], 'states' => ['lists.readable' => SkillCatalog::MISSING]],
        ],
    ]);

    $skill = collect($report)->firstWhere('key', 'lists')['subjects'][0]['skills'][0];

    expect($skill['state'])->toBe(SkillCatalog::MISSING)
        ->and($skill['message'])->not->toBeEmpty()
        ->and($skill['cta'])->not->toBeEmpty();
});

// ──────────────────────────────────────────────────────────────────────────────
// The page
// ──────────────────────────────────────────────────────────────────────────────

test('the skills page requires a login', function () {
    $this->get('/skills')->assertRedirect();
});

test('the page counts loose ends in subjects, not areas', function () {
    $user = skillUser(['bot_enabled' => true]);
    skillList($user, 'orphan_one');
    skillList($user, 'orphan_two');

    $this->actingAs($user)
        ->get('/skills')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('skills/index')
                ->where('looseEnds', 2)
                ->where('skillsets.0.key', 'lists')
                ->where('skillsets.0.status', SkillReport::LOOSE_END)
        );
});

test('a fully wired account reports nothing to do', function () {
    $user = skillUser(['bot_enabled' => true]);
    $list = skillList($user, 'raffle');
    skillAppender($user, $list);
    ListMetaCommand::create(['user_id' => $user->id, 'command' => 'list', 'enabled' => true]);

    $this->actingAs($user)
        ->get('/skills')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('looseEnds', 0));
});
