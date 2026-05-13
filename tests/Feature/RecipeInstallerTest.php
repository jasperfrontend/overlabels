<?php

use App\Events\ControlValueUpdated;
use App\Events\PickerLanded;
use App\Models\OptionSet;
use App\Models\OverlayControl;
use App\Models\Picker;
use App\Models\Recipe;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeInstaller;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;

uses(DatabaseTransactions::class);

function seedCoinFlipRecipe(): Recipe
{
    $manifest = json_decode(
        file_get_contents(base_path('resources/recipes/coin_flip/manifest.json')),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    return Recipe::create([
        'slug' => $manifest['slug'],
        'version' => $manifest['version'],
        'name' => $manifest['name'],
        'description' => $manifest['description'],
        'author_name' => $manifest['author']['name'],
        'author_twitch_login' => $manifest['author']['twitch_login'] ?? null,
        'changelog' => $manifest['changelog'] ?? null,
        'min_overlabels_version' => $manifest['min_overlabels_version'] ?? 1,
        'requires_integrations' => $manifest['requires_integrations'] ?? [],
        'max_instances_per_user' => $manifest['max_instances_per_user'] ?? null,
        'manifest' => $manifest,
        'is_first_party' => true,
    ]);
}

function installerFreshUser(): User
{
    return User::factory()->create();
}

it('installs Coin Flip end-to-end with correct primitives + controls', function () {
    $recipe = seedCoinFlipRecipe();
    $user = installerFreshUser();

    $instance = app(RecipeInstaller::class)->install($recipe, $user, 'main');

    expect($instance)->toBeInstanceOf(RecipeInstance::class)
        ->and($instance->instance_slug)->toBe('main')
        ->and($instance->primitive_map)->toHaveKeys(['option_sets', 'pickers'])
        ->and($instance->primitive_map['option_sets'])->toHaveKey('coin')
        ->and($instance->primitive_map['pickers'])->toHaveKey('flipper');

    $optionSet = OptionSet::where('recipe_instance_id', $instance->id)->first();
    expect($optionSet)->not->toBeNull()
        ->and($optionSet->slug)->toBe('main_coin')
        ->and($optionSet->items)->toBe(['Heads', 'Tails']);

    $picker = Picker::where('recipe_instance_id', $instance->id)->first();
    expect($picker)->not->toBeNull()
        ->and($picker->slug)->toBe('main_flipper')
        ->and($picker->option_set_id)->toBe($optionSet->id);

    $controls = OverlayControl::where('recipe_instance_id', $instance->id)->get();
    expect($controls)->toHaveCount(3);
    $keysByType = $controls->keyBy('key');
    expect($keysByType->keys()->all())->toContain('result', 'result_at', 'running')
        ->and($keysByType['result']->type)->toBe('text')
        ->and($keysByType['result_at']->type)->toBe('number')
        ->and($keysByType['running']->type)->toBe('boolean');
});

it('composes broadcastKey as recipe_slug:instance_slug:key for recipe controls', function () {
    $recipe = seedCoinFlipRecipe();
    $user = installerFreshUser();
    $instance = app(RecipeInstaller::class)->install($recipe, $user, 'lolwheel');

    $resultControl = OverlayControl::where('recipe_instance_id', $instance->id)
        ->where('key', 'result')
        ->first();

    expect($resultControl->broadcastKey())->toBe('coin_flip:lolwheel:result');
});

it('refuses to install a duplicate instance_slug for the same (user, recipe)', function () {
    $recipe = seedCoinFlipRecipe();
    $user = installerFreshUser();
    $installer = app(RecipeInstaller::class);

    $installer->install($recipe, $user, 'main');

    expect(fn () => $installer->install($recipe, $user, 'main'))
        ->toThrow(RuntimeException::class, "Instance slug 'main' is already in use");
});

it('allows the same user to install the same recipe twice under different slugs', function () {
    $recipe = seedCoinFlipRecipe();
    $user = installerFreshUser();
    $installer = app(RecipeInstaller::class);

    $first = $installer->install($recipe, $user, 'main');
    $second = $installer->install($recipe, $user, 'lolwheel');

    expect($first->id)->not->toBe($second->id)
        ->and(OptionSet::where('user_id', $user->id)->count())->toBe(2)
        ->and(Picker::where('user_id', $user->id)->count())->toBe(2)
        ->and(OverlayControl::where('user_id', $user->id)->count())->toBe(6);
});

it('rejects an instance slug that contains a dash', function () {
    $recipe = seedCoinFlipRecipe();
    $user = installerFreshUser();

    expect(fn () => app(RecipeInstaller::class)->install($recipe, $user, 'bad-slug'))
        ->toThrow(InvalidArgumentException::class);
});

it('enforces max_instances_per_user', function () {
    $recipe = seedCoinFlipRecipe();
    $recipe->update(['max_instances_per_user' => 2]);
    $user = installerFreshUser();
    $installer = app(RecipeInstaller::class);

    $installer->install($recipe, $user, 'a');
    $installer->install($recipe, $user, 'b');

    expect(fn () => $installer->install($recipe, $user, 'c'))
        ->toThrow(RuntimeException::class, 'Per-user install cap reached');
});

it('cascades cleanup: deleting the instance removes primitives and controls', function () {
    $recipe = seedCoinFlipRecipe();
    $user = installerFreshUser();
    $instance = app(RecipeInstaller::class)->install($recipe, $user, 'main');
    $instanceId = $instance->id;

    expect(OptionSet::where('recipe_instance_id', $instanceId)->count())->toBe(1);

    $instance->delete();

    expect(OptionSet::where('recipe_instance_id', $instanceId)->count())->toBe(0)
        ->and(Picker::where('recipe_instance_id', $instanceId)->count())->toBe(0)
        ->and(OverlayControl::where('recipe_instance_id', $instanceId)->count())->toBe(0);
});

it('bridges PickerLanded to overlay_controls and broadcasts ControlValueUpdated', function () {
    Event::fake([ControlValueUpdated::class]);

    $recipe = seedCoinFlipRecipe();
    $user = installerFreshUser();
    $user->update(['twitch_id' => '99988877']);
    $instance = app(RecipeInstaller::class)->install($recipe, $user, 'main');
    $picker = $instance->pickers->first();

    $result = $picker->fire();
    expect($result)->toBeIn(['Heads', 'Tails']);

    $resultControl = OverlayControl::where('recipe_instance_id', $instance->id)
        ->where('key', 'result')
        ->first();
    $resultAtControl = OverlayControl::where('recipe_instance_id', $instance->id)
        ->where('key', 'result_at')
        ->first();

    expect($resultControl->fresh()->value)->toBe($result)
        ->and($resultAtControl->fresh()->value)->toMatch('/^\d+$/');

    Event::assertDispatchedTimes(ControlValueUpdated::class, 3);

    Event::assertDispatched(ControlValueUpdated::class, function ($event) use ($result) {
        return $event->key === 'coin_flip:main:result'
            && $event->value === $result
            && $event->broadcasterId === '99988877';
    });
});

it('ignores PickerLanded for pickers not owned by any recipe_instance', function () {
    Event::fake([ControlValueUpdated::class]);

    $user = installerFreshUser();
    $user->update(['twitch_id' => '55544433']);

    $os = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'standalone_os',
        'items' => ['A', 'B'],
    ]);
    $picker = Picker::create([
        'user_id' => $user->id,
        'option_set_id' => $os->id,
        'slug' => 'standalone_picker',
    ]);

    $picker->fire();

    Event::assertNotDispatched(ControlValueUpdated::class);
});
