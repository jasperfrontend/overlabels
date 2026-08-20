<?php

use App\Models\ListAppender;
use App\Models\ListMetaCommand;
use App\Models\OptionSet;
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

function skillList(User $user): OptionSet
{
    return OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'list-'.fake()->unique()->lexify('??????'),
        'label' => 'Raffle',
        'items' => [],
    ]);
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

// ──────────────────────────────────────────────────────────────────────────────
// The catalogue and the facts must not drift apart
// ──────────────────────────────────────────────────────────────────────────────

test('every skill in the catalogue has a fact that answers it', function () {
    // Evaluating a skill is a lookup, so a skill declared with no matching
    // fact would read as unsatisfied forever and nothing would ever say so.
    $facts = SkillFacts::for(skillUser());

    expect(array_keys($facts))->toEqualCanonicalizing(SkillCatalog::skillKeys());
});

test('every skill a skillset references is declared', function () {
    foreach (SkillCatalog::SKILLSETS as $key => $set) {
        foreach ($set['skills'] as $skillKey) {
            expect(array_key_exists($skillKey, SkillCatalog::SKILLS))
                ->toBeTrue("skillset '{$key}' references undeclared skill '{$skillKey}'");
        }
    }
});

test('every skill points at a route that exists', function () {
    foreach (SkillCatalog::SKILLS as $key => $skill) {
        expect(Route::has($skill['route']))
            ->toBeTrue("skill '{$key}' points at unknown route '{$skill['route']}'");
    }
});

// ──────────────────────────────────────────────────────────────────────────────
// Facts are computed, never stored
// ──────────────────────────────────────────────────────────────────────────────

test('a brand new account has none of the lists skills', function () {
    $facts = SkillFacts::for(skillUser());

    expect($facts['lists.has_list'])->toBeFalse()
        ->and($facts['lists.has_appender'])->toBeFalse()
        ->and($facts['lists.has_reader'])->toBeFalse()
        ->and($facts['bot.in_chat'])->toBeFalse();
});

test('an empty list still satisfies the list skill', function () {
    // A raffle list starts empty and fills from chat, so requiring items would
    // call a perfectly good setup broken.
    $user = skillUser();
    skillList($user);

    expect(SkillFacts::for($user)['lists.has_list'])->toBeTrue();
});

test('a disabled appender does not count', function () {
    $user = skillUser();
    skillAppender($user, skillList($user), enabled: false);

    expect(SkillFacts::for($user)['lists.has_appender'])->toBeFalse();
});

test('an appender pointing at a deleted list does not count', function () {
    // The exact false positive this page exists to prevent: a row that looks
    // like a working append path but writes nowhere.
    $user = skillUser();
    $list = skillList($user);
    skillAppender($user, $list);

    expect(SkillFacts::for($user)['lists.has_appender'])->toBeTrue();

    $list->delete();

    expect(SkillFacts::for($user)['lists.has_appender'])->toBeFalse();
});

test('another user\'s lists and commands never count as yours', function () {
    $me = skillUser();
    $them = skillUser();

    $theirList = skillList($them);
    skillAppender($them, $theirList);
    ListMetaCommand::create(['user_id' => $them->id, 'command' => 'list', 'enabled' => true]);

    $facts = SkillFacts::for($me);

    expect($facts['lists.has_list'])->toBeFalse()
        ->and($facts['lists.has_appender'])->toBeFalse()
        ->and($facts['lists.has_reader'])->toBeFalse();
});

test('facts follow the account rather than a stored record', function () {
    // Delete the thing and the skill un-satisfies itself on the next request.
    // That is the property that makes a table unnecessary.
    $user = skillUser();
    $list = skillList($user);

    expect(SkillFacts::for($user)['lists.has_list'])->toBeTrue();

    $list->delete();

    expect(SkillFacts::for($user->fresh())['lists.has_list'])->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────────
// The ranking, which is the actual feature
// ──────────────────────────────────────────────────────────────────────────────

test('nothing set up is not_started, not a loose end', function () {
    // Someone who has never touched lists has not left a loose end; nagging
    // them is how this page turns into wallpaper.
    $report = SkillReport::build([
        'lists.has_list' => false,
        'lists.has_appender' => false,
        'lists.has_reader' => false,
        'bot.in_chat' => false,
    ]);

    expect($report[0]['status'])->toBe(SkillReport::NOT_STARTED);
});

test('partly set up is a loose end', function () {
    $report = SkillReport::build([
        'lists.has_list' => true,
        'lists.has_appender' => true,
        'lists.has_reader' => false,
        'bot.in_chat' => false,
    ]);

    expect($report[0]['status'])->toBe(SkillReport::LOOSE_END)
        ->and($report[0]['missing'])->toBe(2)
        ->and($report[0]['satisfied'])->toBe(2);
});

test('fully set up is complete', function () {
    $report = SkillReport::build([
        'lists.has_list' => true,
        'lists.has_appender' => true,
        'lists.has_reader' => true,
        'bot.in_chat' => true,
    ]);

    expect($report[0]['status'])->toBe(SkillReport::COMPLETE)
        ->and($report[0]['missing'])->toBe(0);
});

test('status ranks loose ends above not-started above complete', function () {
    // Pinned on the pure helper so the ordering survives new skillsets being
    // added without a database anywhere near it.
    expect(SkillReport::status(0, 4))->toBe(SkillReport::NOT_STARTED)
        ->and(SkillReport::status(1, 4))->toBe(SkillReport::LOOSE_END)
        ->and(SkillReport::status(3, 4))->toBe(SkillReport::LOOSE_END)
        ->and(SkillReport::status(4, 4))->toBe(SkillReport::COMPLETE);
});

test('a missing skill carries the consequence, not just the gap', function () {
    $report = SkillReport::build([
        'lists.has_list' => true,
        'lists.has_appender' => false,
        'lists.has_reader' => false,
        'bot.in_chat' => false,
    ]);

    $appender = collect($report[0]['skills'])->firstWhere('key', 'lists.has_appender');

    expect($appender['satisfied'])->toBeFalse()
        ->and($appender['missing'])->not->toBeEmpty()
        ->and($appender['cta'])->not->toBeEmpty();
});

// ──────────────────────────────────────────────────────────────────────────────
// The page
// ──────────────────────────────────────────────────────────────────────────────

test('the skills page requires a login', function () {
    $this->get('/skills')->assertRedirect();
});

test('the skills page reports a real loose end for the account', function () {
    $user = skillUser();
    $list = skillList($user);
    skillAppender($user, $list);

    // List and appender, but nothing reads it back and no bot: 2 of 4.
    $this->actingAs($user)
        ->get('/skills')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('skills/index')
                ->where('looseEnds', 1)
                ->where('skillsets.0.key', 'lists')
                ->where('skillsets.0.status', SkillReport::LOOSE_END)
                ->where('skillsets.0.satisfied', 2)
                ->where('skillsets.0.missing', 2)
        );
});

test('the skills page shows no loose ends once the chain is complete', function () {
    $user = skillUser(['bot_enabled' => true]);
    $list = skillList($user);
    skillAppender($user, $list);
    ListMetaCommand::create(['user_id' => $user->id, 'command' => 'list', 'enabled' => true]);

    $this->actingAs($user)
        ->get('/skills')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('looseEnds', 0)
                ->where('skillsets.0.status', SkillReport::COMPLETE)
        );
});
