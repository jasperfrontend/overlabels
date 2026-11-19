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
 * The 2026-11-19 angle backfill: Chat Emote Bubbles installs from before
 * get `angle_colors` on their Gradient angle, exactly as a fresh install has.
 */
function angleBackfill(): Migration
{
    return require base_path('database/migrations/2026_11_19_150000_declare_angle_colors_on_installed_chat_emote_bubbles.php');
}

function angleInstall(): OverlayTemplate
{
    $user = User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'angle'.fake()->unique()->randomNumber(5)],
    ]);
    $catalog = app(RecipeCatalog::class);
    $recipe = $catalog->sync($catalog->find('chat-emote-bubbles'));
    $instance = app(RecipeInstaller::class)->install($recipe, $user, RecipeInstance::instanceSlugFrom('chat-emote-bubbles'));

    return OverlayTemplate::findOrFail($instance->primitive_map['overlays']['bubbles']);
}

function angleRow(OverlayTemplate $template): OverlayControl
{
    return $template->controls()->where('key', 'gradient_angle')->firstOrFail();
}

function stripAngleColors(OverlayTemplate $template): void
{
    $config = angleRow($template)->config;
    unset($config['angle_colors']);
    angleRow($template)->update(['config' => $config]);
}

it('gives an install from before exactly the config a fresh install has, and keeps the value', function () {
    $template = angleInstall();
    $expected = angleRow($template)->config;
    expect($expected['angle_colors'])->toBe(['bubble_color', 'bubble_color_2']);

    stripAngleColors($template);
    angleRow($template)->update(['value' => '135']);

    angleBackfill()->up();

    expect(angleRow($template)->config)->toBe($expected)
        ->and(angleRow($template)->value)->toBe('135');
});

it('runs twice to the same result, and leaves a row that already declares one alone', function () {
    $template = angleInstall();
    stripAngleColors($template);

    angleBackfill()->up();
    $afterFirst = angleRow($template)->config;
    angleBackfill()->up();

    expect(angleRow($template)->config)->toBe($afterFirst);

    $other = angleInstall();
    $config = angleRow($other)->config;
    $config['angle_colors'] = ['bubble_color_2', 'bubble_color'];
    angleRow($other)->update(['config' => $config]);

    angleBackfill()->up();

    expect(angleRow($other)->config['angle_colors'])->toBe(['bubble_color_2', 'bubble_color']);
});
