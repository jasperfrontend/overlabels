<?php

use App\Models\BotExpression;
use App\Models\Kit;
use App\Models\OverlayTemplate;
use App\Models\Recipe;
use App\Models\RecipeChatTrigger;
use App\Models\RecipeInstance;
use App\Models\User;
use Database\Seeders\FirstPartyKitsSeeder;
use Database\Seeders\FirstPartyRecipesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function ghostUserForKitTests(): User
{
    return User::firstOrCreate(
        ['twitch_id' => 'GHOST_USER'],
        [
            'name' => 'Ghost User',
            'role' => 'user',
            'is_system_user' => true,
            'onboarded_at' => now(),
            'webhook_secret' => 'test',
        ]
    );
}

function makeKitWithRecipe(): array
{
    $ghost = ghostUserForKitTests();

    // Seed wheel_spin recipe and the kit. Reuses the production seeders so
    // the tests cover the same shape that ships to dev/prod.
    (new FirstPartyRecipesSeeder)->run();
    (new FirstPartyKitsSeeder)->run();

    $kit = Kit::where('owner_id', $ghost->id)->where('title', 'Wheel of Fortune')->firstOrFail();
    $recipe = Recipe::where('slug', 'wheel_spin')->firstOrFail();

    return [$kit, $recipe];
}

function freshKitTestUser(): User
{
    // Make sure the user can host a chat trigger; bot_enabled flag is required
    // for the bot commandMap query, not for installer collision checks, so
    // it's irrelevant here. But twitch_data needs a login for any test that
    // would later check the commandMap.
    return User::factory()->create([
        'twitch_data' => ['login' => 'test_'.fake()->unique()->randomNumber(6)],
    ]);
}

// ──────────────────────────────────────────────────────────────────────────────
// Fork: full happy path
// ──────────────────────────────────────────────────────────────────────────────

it('forks a kit and installs its bundled recipe with the default instance slug', function () {
    [$kit, $recipe] = makeKitWithRecipe();
    $user = freshKitTestUser();

    $fork = $kit->fork($user);

    expect($fork->owner_id)->toBe($user->id)
        ->and($fork->forked_from_id)->toBe($kit->id);

    $instance = RecipeInstance::where('user_id', $user->id)
        ->where('recipe_id', $recipe->id)
        ->first();
    expect($instance)->not->toBeNull()
        ->and($instance->instance_slug)->toBe('main');

    $forkedTemplate = $fork->templates->first();
    expect($forkedTemplate)->not->toBeNull()
        ->and($forkedTemplate->html)->toContain('wheel_spin:main')
        ->and($forkedTemplate->html)->not->toContain('__INSTANCE__');
});

it('also materialises the recipe chat trigger when forking the kit', function () {
    [$kit] = makeKitWithRecipe();
    $user = freshKitTestUser();

    $kit->fork($user);

    $trigger = RecipeChatTrigger::where('user_id', $user->id)
        ->where('command', 'spin')
        ->first();
    expect($trigger)->not->toBeNull()
        ->and($trigger->permission_level)->toBe('everyone')
        ->and($trigger->cooldown_seconds)->toBe(15);
});

// ──────────────────────────────────────────────────────────────────────────────
// Fork: collision behaviour
// ──────────────────────────────────────────────────────────────────────────────

it('suffixes the instance slug when the desired one is already used', function () {
    [$kit, $recipe] = makeKitWithRecipe();
    $user = freshKitTestUser();

    // Pre-install wheel_spin:main manually so the kit's default collides.
    // Manually creating skips the installer's chat-command materialisation,
    // so the !spin command remains free for the fork to claim.
    $manualInstance = RecipeInstance::create([
        'recipe_id' => $recipe->id,
        'user_id' => $user->id,
        'instance_slug' => 'main',
        'label' => 'Manual',
    ]);

    $fork = $kit->fork($user);

    $installed = RecipeInstance::where('user_id', $user->id)
        ->where('recipe_id', $recipe->id)
        ->where('id', '!=', $manualInstance->id)
        ->first();
    expect($installed)->not->toBeNull()
        ->and($installed->instance_slug)->toBe('main_2');

    // And the forked template's __INSTANCE__ placeholder uses the suffixed slug.
    $forkedTemplate = $fork->templates->first();
    expect($forkedTemplate->html)->toContain('wheel_spin:main_2');
});

it('rolls back the entire fork when a recipe install collides on chat command', function () {
    [$kit] = makeKitWithRecipe();
    $user = freshKitTestUser();

    // Pre-claim the !spin command via a BotExpression so the recipe install
    // refuses with a clear error.
    BotExpression::create([
        'user_id' => $user->id,
        'command' => 'spin',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'expression' => 'hi',
        'enabled' => true,
    ]);

    $kitForksBefore = Kit::where('forked_from_id', $kit->id)->count();
    $instancesBefore = RecipeInstance::where('user_id', $user->id)->count();

    try {
        $kit->fork($user);
        $threw = false;
    } catch (RuntimeException $e) {
        $threw = true;
        expect($e->getMessage())->toContain('collides with an existing Bot Expression');
    }

    expect($threw)->toBeTrue()
        ->and(Kit::where('forked_from_id', $kit->id)->count())->toBe($kitForksBefore)
        ->and(RecipeInstance::where('user_id', $user->id)->count())->toBe($instancesBefore);
});

// ──────────────────────────────────────────────────────────────────────────────
// Backward compat
// ──────────────────────────────────────────────────────────────────────────────

it('forks a recipe-less kit without trying to substitute __INSTANCE__', function () {
    $ghost = ghostUserForKitTests();
    $kit = Kit::create([
        'owner_id' => $ghost->id,
        'title' => 'Plain Kit',
        'description' => 'No recipes.',
        'is_public' => true,
    ]);
    $template = OverlayTemplate::factory()->create([
        'owner_id' => $ghost->id,
        'name' => 'Plain Template',
        'html' => '<div>__INSTANCE__</div>',
        'fork_of_id' => null,
    ]);
    $kit->templates()->attach($template->id);

    $user = freshKitTestUser();
    $fork = $kit->fork($user);

    // No recipes means no substitution should happen - the literal
    // __INSTANCE__ stays intact in the forked template.
    expect($fork->templates->first()->html)->toBe('<div>__INSTANCE__</div>');
});

// ──────────────────────────────────────────────────────────────────────────────
// Seed correctness
// ──────────────────────────────────────────────────────────────────────────────

it('seeds the Wheel of Fortune kit with one bundled recipe and one template', function () {
    [$kit] = makeKitWithRecipe();

    expect($kit->is_starter_kit)->toBeTrue()
        ->and($kit->is_public)->toBeTrue()
        ->and($kit->templates)->toHaveCount(1)
        ->and($kit->recipes)->toHaveCount(1);

    $recipePivot = $kit->recipes->first()->pivot;
    expect($recipePivot->default_instance_slug)->toBe('main');

    $template = $kit->templates->first();
    expect($template->html)->toContain('__INSTANCE__')
        ->and($template->html)->toContain('wheel_spin')
        ->and($template->css)->toContain('wheel-stage')
        ->and($template->js)->toContain('SEGMENTS');
});
