<?php

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
 * The 2026-11-19 backfill: installs of Chat Emote Bubbles and Twitch Chat
 * from before `enabled_if` get exactly the declarations a fresh install has.
 */
function enabledIfBackfill(): Migration
{
    return require base_path('database/migrations/2026_11_19_130000_declare_enabled_if_on_installed_product_controls.php');
}

function enabledIfUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'enabledif'.fake()->unique()->randomNumber(5)],
    ]);
}

function enabledIfInstall(User $user, string $slug): OverlayTemplate
{
    $catalog = app(RecipeCatalog::class);
    $recipe = $catalog->sync($catalog->find($slug));
    $instance = app(RecipeInstaller::class)->install($recipe, $user, RecipeInstance::instanceSlugFrom($slug));

    return OverlayTemplate::findOrFail(collect($instance->primitive_map['overlays'])->first());
}

/**
 * Every row's config, keyed by control key, each config's keys sorted: the
 * backfill appends `enabled_if`, so a row that carries a later key too (the
 * angle knob's `angle_colors`) holds the same config in a different order.
 */
function enabledIfConfigs(OverlayTemplate $template): array
{
    return $template->controls()->orderBy('sort_order')->get()->mapWithKeys(function (OverlayControl $c) {
        $config = $c->config;
        if (is_array($config)) {
            ksort($config);
        }

        return [$c->key => $config];
    })->all();
}

/** Every row's value, keyed by control key. */
function enabledIfValues(OverlayTemplate $template): array
{
    return $template->controls()->orderBy('sort_order')->pluck('value', 'key')->all();
}

/** An install from before: every enabled_if taken off its row. */
function stripEnabledIf(OverlayTemplate $template): void
{
    foreach ($template->controls()->get() as $control) {
        $config = $control->config ?? [];
        if (! array_key_exists('enabled_if', $config)) {
            continue;
        }
        unset($config['enabled_if']);
        $control->update(['config' => $config === [] ? null : $config]);
    }
}

it('gives installs from before exactly the declarations a fresh install has', function () {
    $user = enabledIfUser();

    foreach (['chat-emote-bubbles', 'twitch-chat-overlay'] as $slug) {
        $template = enabledIfInstall($user, $slug);
        $expected = enabledIfConfigs($template);
        expect(collect($expected)->filter(fn ($config) => isset($config['enabled_if'])))->not->toBeEmpty();

        stripEnabledIf($template);
        // A value the streamer chose on a row the backfill writes, so it
        // differs from the recipe default the install put there.
        $template->controls()->whereIn('key', ['bubble_color', 'background_color'])->update(['value' => '#ff7eb6']);
        $values = enabledIfValues($template);

        enabledIfBackfill()->up();

        expect(enabledIfConfigs($template))->toBe($expected)
            ->and(enabledIfValues($template))->toBe($values);
    }

    expect(OverlayControl::where('user_id', $user->id)->whereIn('key', ['bubble_color', 'background_color'])->pluck('value')->unique()->values()->all())
        ->toBe(['#ff7eb6']);
});

it('leaves a row that already declares one, and a hand-made control with the same key, alone', function () {
    $user = enabledIfUser();
    $template = enabledIfInstall($user, 'chat-emote-bubbles');
    stripEnabledIf($template);
    $template->controls()->where('key', 'spawn_x')->firstOrFail()->update(['config' => ['min' => 0, 'max' => 100, 'enabled_if' => ['c:spawn != random']]]);

    $own = OverlayTemplate::factory()->create(['owner_id' => $user->id, 'fork_of_id' => null, 'type' => 'static']);
    $hand = OverlayControl::createForTemplate($own, $user, ['key' => 'bubble_color', 'type' => 'color', 'value' => '#000000']);
    $handBefore = $hand->fresh()->config;

    enabledIfBackfill()->up();

    expect($template->controls()->where('key', 'spawn_x')->firstOrFail()->config['enabled_if'])->toBe(['c:spawn != random'])
        ->and($template->controls()->where('key', 'spawn_y')->firstOrFail()->config['enabled_if'])->toBe(['c:spawn = cannon'])
        ->and($hand->fresh()->config)->toBe($handBefore);
});
