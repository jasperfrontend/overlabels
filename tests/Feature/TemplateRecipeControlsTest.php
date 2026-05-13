<?php

use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\Recipe;
use App\Models\RecipeInstance;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * Set up a user + a recipe_instance with three recipe-managed controls
 * (result, result_at, rotation_deg). Returns the user and the instance.
 */
function makeUserWithWheelInstance(): array
{
    $user = User::factory()->create();

    $recipe = Recipe::create([
        'slug' => 'test_wheel',
        'version' => 1,
        'name' => 'Test Wheel',
        'description' => 'For testing recipe-control surfacing in the template editor.',
        'author_name' => 'Test',
        'manifest' => [
            'recipe_format_version' => 1,
            'slug' => 'test_wheel',
            'version' => 1,
            'name' => 'Test Wheel',
            'description' => 'Test',
            'author' => ['name' => 'Test'],
            'primitives' => ['option_sets' => [], 'pickers' => []],
            'control_exports' => [],
            'triggers' => [],
        ],
        'is_first_party' => true,
    ]);

    $instance = RecipeInstance::create([
        'recipe_id' => $recipe->id,
        'user_id' => $user->id,
        'instance_slug' => 'main',
        'label' => 'Main',
        'primitive_map' => [],
    ]);

    foreach (['result' => 'text', 'result_at' => 'number', 'rotation_deg' => 'expression'] as $key => $type) {
        OverlayControl::create([
            'overlay_template_id' => null,
            'user_id' => $user->id,
            'recipe_instance_id' => $instance->id,
            'key' => $key,
            'label' => $key,
            'type' => $type,
            'value' => '0',
            'config' => $type === 'expression' ? ['expression' => 'now_ms()'] : null,
            'sort_order' => 0,
            'source' => null,
            'source_managed' => true,
        ]);
    }

    return [$user, $instance];
}

it('exposes only recipe controls actually referenced by the template HTML', function () {
    [$user, $instance] = makeUserWithWheelInstance();

    // Template references rotation_deg in style + result in body, but NOT result_at.
    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'name' => 'Wheel test',
        'html' => '<div style="transform: rotate([[[c:test_wheel:main:rotation_deg]]]deg)">[[[c:test_wheel:main:result]]]</div>',
        'css' => null,
        'js' => null,
        'fork_of_id' => null,
    ]);

    $response = $this->actingAs($user)->get("/templates/{$template->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('templates/show')
        ->has('recipeControls', 2)
        ->where('recipeControls.0.broadcast_key', fn ($v) => in_array($v, ['test_wheel:main:result', 'test_wheel:main:rotation_deg'], true))
        ->where('recipeControls.0.recipe_instance.recipe.slug', 'test_wheel')
        ->where('recipeControls.0.recipe_instance.instance_slug', 'main')
    );
});

it('returns empty recipeControls when the template references none of the recipes tags', function () {
    [$user] = makeUserWithWheelInstance();

    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'name' => 'No-recipe template',
        'html' => '<div>just static [[[channel_name]]]</div>',
        'fork_of_id' => null,
    ]);

    $response = $this->actingAs($user)->get("/templates/{$template->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('recipeControls', []));
});

it('does not expose recipe controls owned by another user', function () {
    [$wheelOwner] = makeUserWithWheelInstance();
    $someoneElse = User::factory()->create();

    $template = OverlayTemplate::factory()->create([
        'owner_id' => $someoneElse->id,
        'name' => 'Other user template',
        'html' => '<div>[[[c:test_wheel:main:rotation_deg]]]</div>',
        'fork_of_id' => null,
    ]);

    // The someone-else owner has no recipe install, so recipeControls is empty
    // even though the template references those tags.
    $response = $this->actingAs($someoneElse)->get("/templates/{$template->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('recipeControls', []));
});

it('matches tag references in css and js too, not just html', function () {
    [$user] = makeUserWithWheelInstance();

    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'name' => 'Mixed-source template',
        'html' => '<div class="wheel"></div>',
        // rotation_deg referenced in CSS (e.g. animating via a CSS custom property),
        // result referenced in JS (the column - even though scripts get stripped on
        // save, the JS field can still hold expression engine config or notes).
        'css' => '.wheel { --rotation: [[[c:test_wheel:main:rotation_deg]]]deg; }',
        'js' => '/* uses [[[c:test_wheel:main:result]]] in expression dependencies */',
        'fork_of_id' => null,
    ]);

    $response = $this->actingAs($user)->get("/templates/{$template->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->has('recipeControls', 2));
});
