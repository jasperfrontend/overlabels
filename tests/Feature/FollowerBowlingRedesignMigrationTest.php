<?php

use App\Models\BotAlias;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * The 2026-11-16 move: every installed Follower Bowling lane gets the
 * full-screen redesign wholesale, loses its gobowl switch and the two
 * moderator aliases the old product installed.
 */
function bowlingMove(): Migration
{
    return require base_path('database/migrations/2026_11_16_200000_move_installed_follower_bowling_lanes_to_the_redesign.php');
}

function bowlingMoveConstant(string $name): mixed
{
    return (new ReflectionClass(bowlingMove()))->getConstant($name);
}

function bowlingMoveUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'lane'.fake()->unique()->randomNumber(5)],
    ]);
}

function bowlingMoveInstall(User $user): RecipeInstance
{
    $catalog = app(RecipeCatalog::class);

    return app(RecipeInstaller::class)->install(
        $catalog->sync($catalog->find('follower-bowling')),
        $user,
        RecipeInstance::instanceSlugFrom('follower-bowling'),
    );
}

/** Turn a fresh install back into one of the old product: old lane, switch, two aliases. */
function makeOldLane(RecipeInstance $instance): RecipeInstance
{
    $template = OverlayTemplate::findOrFail($instance->primitive_map['overlays']['lane']);
    $template->update([
        'head' => '<link href="https://fonts.bunny.net/css2?family=Albert+Sans" rel="stylesheet">',
        'html' => '[[[if:c:gobowl]]]<div class="lane-widget">old lane, edited by hand</div>[[[endif]]]',
        'css' => '.lane-widget { color: hotpink; }',
        'template_tags' => ['if:c:gobowl', 'endif'],
    ]);
    OverlayControl::createForTemplate($template, $instance->user, [
        'key' => 'gobowl', 'label' => 'gobowl', 'type' => 'boolean', 'value' => '1',
    ]);

    $first = BotAlias::create(['user_id' => $instance->user_id, 'command' => 'fbfirst', 'target_template' => 'list lane pop first', 'permission_level' => 'moderator']);
    $draw = BotAlias::create(['user_id' => $instance->user_id, 'command' => 'fbdraw', 'target_template' => 'list lane draw', 'permission_level' => 'moderator']);
    $instance->update(['primitive_map' => array_merge($instance->primitive_map, [
        'bot_aliases' => ['fbfirst' => $first->id, 'fbdraw' => $draw->id],
    ])]);

    return $instance->fresh();
}

it('ships exactly the source a fresh install gets', function () {
    $instance = bowlingMoveInstall(bowlingMoveUser());
    $template = OverlayTemplate::findOrFail($instance->primitive_map['overlays']['lane']);

    // CSS is not compared: the migration is frozen history and the recipe's
    // stylesheet moves on (palette renames). The move recognises a fresh
    // install by its HTML, so that one must still match.
    expect(bowlingMoveConstant('HTML'))->toBe($template->html)
        ->and(bowlingMoveConstant('HEAD'))->toBe($template->head)
        ->and(bowlingMoveConstant('DESCRIPTION'))->toBe($template->description);

    // A superset of what the installer allows at this account's cap: the
    // followers loop is expanded to the largest cap any account can set.
    // The palette controls arrived after this move (2026-11-20) and are not its business.
    $tags = array_filter($template->template_tags, fn (string $tag) => ! str_ends_with($tag, '_color'));
    expect(array_values(array_diff($tags, bowlingMoveConstant('TAGS'))))->toBe([]);
    expect(bowlingMoveConstant('TAGS'))->toContain('channel_followers.49.user_name');
});

it('replaces an old lane wholesale, edits and all, and drops the switch and the aliases', function () {
    $user = bowlingMoveUser();
    $instance = makeOldLane(bowlingMoveInstall($user));
    $templateId = $instance->primitive_map['overlays']['lane'];
    $aliasIds = array_values($instance->primitive_map['bot_aliases']);

    bowlingMove()->up();

    $template = OverlayTemplate::findOrFail($templateId);
    expect($template->html)->toBe(bowlingMoveConstant('HTML'))
        ->and($template->css)->toBe(bowlingMoveConstant('CSS'))
        ->and($template->head)->toBe(bowlingMoveConstant('HEAD'))
        ->and($template->template_tags)->toBe(bowlingMoveConstant('TAGS'))
        ->and($template->controls()->where('key', 'gobowl')->exists())->toBeFalse()
        ->and($template->controls()->where('type', 'expression')->count())->toBe(20)
        ->and(BotAlias::whereIn('id', $aliasIds)->exists())->toBeFalse()
        ->and($instance->fresh()->primitive_map)->not->toHaveKey('bot_aliases')
        ->and($instance->fresh()->primitive_map['lists']['lane'] ?? null)->not->toBeNull();
});

it('leaves aliases the install did not create alone', function () {
    $user = bowlingMoveUser();
    $instance = makeOldLane(bowlingMoveInstall($user));
    $mine = BotAlias::create(['user_id' => $user->id, 'command' => 'next', 'target_template' => 'list lane pop first', 'permission_level' => 'moderator']);
    $other = bowlingMoveUser();
    $theirs = BotAlias::create(['user_id' => $other->id, 'command' => 'fbfirst', 'target_template' => 'list lane pop first', 'permission_level' => 'moderator']);

    bowlingMove()->up();

    expect(BotAlias::find($mine->id))->not->toBeNull()
        ->and(BotAlias::find($theirs->id))->not->toBeNull();
});

function bowlingAliasCleanup(): Migration
{
    return require base_path('database/migrations/2026_11_16_210000_drop_follower_bowling_aliases_left_behind_by_the_redesign_move.php');
}

it('drops the aliases of an install the move skipped because its lane overlay is gone', function () {
    $user = bowlingMoveUser();
    $instance = makeOldLane(bowlingMoveInstall($user));
    $aliasIds = array_values($instance->primitive_map['bot_aliases']);
    OverlayTemplate::whereKey($instance->primitive_map['overlays']['lane'])->delete();
    $mine = BotAlias::create(['user_id' => $user->id, 'command' => 'next', 'target_template' => 'list lane pop first', 'permission_level' => 'moderator']);

    bowlingMove()->up();

    // The move returns early on a missing overlay, aliases and all.
    expect(BotAlias::whereIn('id', $aliasIds)->count())->toBe(2);

    bowlingAliasCleanup()->up();
    bowlingAliasCleanup()->up();

    expect(BotAlias::whereIn('id', $aliasIds)->exists())->toBeFalse()
        ->and($instance->fresh()->primitive_map)->not->toHaveKey('bot_aliases')
        ->and(BotAlias::find($mine->id))->not->toBeNull();
});

it('drops the aliases of an install the move skipped because it names no lane', function () {
    $user = bowlingMoveUser();
    $instance = makeOldLane(bowlingMoveInstall($user));
    $aliasIds = array_values($instance->primitive_map['bot_aliases']);
    $map = $instance->primitive_map;
    unset($map['overlays']['lane']);
    $instance->update(['primitive_map' => $map]);

    bowlingMove()->up();
    bowlingAliasCleanup()->up();

    expect(BotAlias::whereIn('id', $aliasIds)->exists())->toBeFalse()
        ->and($instance->fresh()->primitive_map)->not->toHaveKey('bot_aliases');
});

it('leaves a fresh install untouched and is safe to run twice', function () {
    $user = bowlingMoveUser();
    $fresh = bowlingMoveInstall($user);
    $template = OverlayTemplate::findOrFail($fresh->primitive_map['overlays']['lane']);
    $template->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
    $stamp = $template->fresh()->updated_at;

    bowlingMove()->up();
    bowlingMove()->up();

    expect($template->fresh()->updated_at->equalTo($stamp))->toBeTrue();
});
