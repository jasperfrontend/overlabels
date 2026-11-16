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

    expect(bowlingMoveConstant('HTML'))->toBe($template->html)
        ->and(bowlingMoveConstant('CSS'))->toBe($template->css)
        ->and(bowlingMoveConstant('HEAD'))->toBe($template->head)
        ->and(bowlingMoveConstant('DESCRIPTION'))->toBe($template->description);

    // A superset of what the installer allows at this account's cap: the
    // followers loop is expanded to the largest cap any account can set.
    expect(array_diff($template->template_tags, bowlingMoveConstant('TAGS')))->toBe([]);
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
