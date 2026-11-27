<?php

use App\Models\OverlayTemplate;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * The 2026-11-27 upgrade: Chat Checkin globes installed before the hexagon
 * tiles get the "Mixed land tones" and "Bold text" switches, off, and the CSS
 * that reads them - unless the streamer edited the CSS. The size knob's description
 * follows its new meaning either way.
 */
function landTonesUpgrade(): Migration
{
    return require base_path('database/migrations/2026_11_27_120000_add_land_tones_and_bold_text_to_installed_chat_checkin_globes.php');
}

function landTonesConstant(string $name): mixed
{
    return (new ReflectionClass(landTonesUpgrade()))->getConstant($name);
}

function landTonesInstall(): OverlayTemplate
{
    $user = User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'tones'.fake()->unique()->randomNumber(5)],
    ]);
    $catalog = app(RecipeCatalog::class);
    $recipe = $catalog->sync($catalog->find('chat-checkin'));
    $instance = app(RecipeInstaller::class)->install($recipe, $user, RecipeInstance::instanceSlugFrom('chat-checkin'));

    return OverlayTemplate::findOrFail($instance->primitive_map['overlays']['globe']);
}

/** Turn a fresh install back into one from between 2026-11-16 and 2026-11-27. */
function makePreTones(OverlayTemplate $template): void
{
    $template->controls()->whereIn('key', ['land_tones', 'font_bold'])->delete();
    $template->controls()->where('key', 'land_dot_size')->update(['description' => landTonesConstant('SIZE_DESCRIPTION_FROM')]);
    $template->update([
        'css' => landTonesConstant('OLD_CSS'),
        'template_tags' => array_values(array_diff($template->template_tags, landTonesConstant('TAGS'))),
    ]);
}

it('ships exactly the CSS a fresh install gets', function () {
    expect(landTonesConstant('NEW_CSS'))->toBe(trim(landTonesInstall()->css));
});

it('gives an old install both switches, off, the CSS and the tags, matching a fresh install', function () {
    $template = landTonesInstall();
    $fresh = $template->controls()->get()->keyBy('key');
    makePreTones($template);

    landTonesUpgrade()->up();

    $template->refresh();
    $controls = $template->controls()->get()->keyBy('key');

    expect($template->css)->toBe(landTonesConstant('NEW_CSS'))
        ->and($template->template_tags)->toContain('c:land_tones', 'c:font_bold')
        ->and($controls['land_tones']->value)->toBe('0')
        ->and($controls['font_bold']->value)->toBe('0')
        ->and($controls['font_bold']->sort_order)->toBe($controls->max('sort_order'));

    foreach (['land_tones', 'font_bold', 'land_dot_size'] as $key) {
        expect($controls[$key]->only(['label', 'type', 'config', 'description']))
            ->toBe($fresh[$key]->only(['label', 'type', 'config', 'description']), "control {$key}");
    }
});

it('leaves an install whose CSS was edited as it is, but still describes the size', function () {
    $template = landTonesInstall();
    makePreTones($template);
    $edited = str_replace('--land-size', '--land-size-edited', landTonesConstant('OLD_CSS'));
    $template->update(['css' => $edited]);

    landTonesUpgrade()->up();

    $template->refresh();
    expect($template->css)->toBe($edited)
        ->and($template->controls()->where('key', 'land_tones')->exists())->toBeFalse()
        ->and($template->controls()->where('key', 'land_dot_size')->value('description'))
        ->toBe(landTonesConstant('SIZE_DESCRIPTION_TO'));
});

it('keeps a size description the streamer wrote', function () {
    $template = landTonesInstall();
    makePreTones($template);
    $template->controls()->where('key', 'land_dot_size')->update(['description' => 'Mine.']);

    landTonesUpgrade()->up();

    expect($template->controls()->where('key', 'land_dot_size')->value('description'))->toBe('Mine.');
});

it('does nothing to an install that already has the switch', function () {
    $template = landTonesInstall();
    $template->controls()->where('key', 'land_tones')->update(['value' => '1']);
    $before = $template->controls()->count();
    $css = $template->css;

    landTonesUpgrade()->up();

    $template->refresh();
    expect($template->controls()->count())->toBe($before)
        ->and($template->css)->toBe($css)
        ->and($template->controls()->where('key', 'land_tones')->value('value'))->toBe('1');
});
