<?php

use App\Models\ExternalIntegration;
use App\Models\OverlayTemplate;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * The 2026-11-16 backfill: Chat Checkin globes installed before the designer
 * get its thirteen controls at the Classic look, the CSS that reads them, and
 * their old home setting as the hometown - unless the streamer edited the CSS.
 */
function checkinBackfill(): Migration
{
    return require base_path('database/migrations/2026_11_16_120000_add_designer_controls_to_installed_chat_checkin_globes.php');
}

function checkinBackfillConstant(string $name): mixed
{
    return (new ReflectionClass(checkinBackfill()))->getConstant($name);
}

function checkinBackfillUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'globe'.fake()->unique()->randomNumber(5)],
    ]);
}

function checkinBackfillInstall(User $user): OverlayTemplate
{
    $catalog = app(RecipeCatalog::class);
    $recipe = $catalog->sync($catalog->find('chat-checkin'));
    $instance = app(RecipeInstaller::class)->install($recipe, $user, RecipeInstance::instanceSlugFrom('chat-checkin'));

    return OverlayTemplate::findOrFail($instance->primitive_map['overlays']['globe']);
}

/** The next upgrade in line, 2026-11-27: the land tones switch. */
function checkinLandTonesUpgrade(): Migration
{
    return require base_path('database/migrations/2026_11_27_120000_add_land_tones_and_bold_text_to_installed_chat_checkin_globes.php');
}

/** Turn a fresh install back into one from before the designer. */
function makePreDesigner(OverlayTemplate $template): void
{
    $keys = [...array_column(checkinBackfillConstant('CONTROLS'), 0), 'land_tones', 'font_bold'];
    $template->controls()->whereIn('key', $keys)->delete();

    $template->update([
        'css' => checkinBackfillConstant('OLD_CSS'),
        'html' => str_replace(checkinBackfillConstant('HTML_TO'), checkinBackfillConstant('HTML_FROM'), $template->html),
        'template_tags' => array_values(array_diff($template->template_tags, [...checkinBackfillConstant('TAGS'), 'c:land_tones', 'c:font_bold'])),
    ]);
}

function setCheckinHome(User $user, ?string $label): void
{
    $integration = ExternalIntegration::where('user_id', $user->id)->where('service', 'checkin')->firstOrFail();
    $integration->settings = array_merge($integration->settings ?? [], $label === null ? [] : [
        'home_place_label' => $label, 'home_lat' => 51.9225, 'home_lng' => 4.47917,
    ]);
    $integration->save();
}

// The recipe has moved on since this backfill, so it no longer matches a fresh
// install on its own. It must hand the next upgrade exactly the CSS that one
// starts from, and the two run in order must land on a fresh install.
it('ships exactly the CSS the next upgrade starts from, and the html a fresh install gets', function () {
    $fresh = checkinBackfillInstall(checkinBackfillUser());
    $nextOldCss = (new ReflectionClass(checkinLandTonesUpgrade()))->getConstant('OLD_CSS');

    expect(checkinBackfillConstant('NEW_CSS'))->toBe($nextOldCss)
        ->and(substr_count($fresh->html, checkinBackfillConstant('HTML_TO')))->toBe(1);
});

it('gives an old install the controls at the Classic look, the CSS and its old home', function () {
    $user = checkinBackfillUser();
    $template = checkinBackfillInstall($user);
    $freshCss = trim($template->css);
    $fresh = $template->controls()->get()->keyBy('key');
    makePreDesigner($template);
    setCheckinHome($user, 'Rotterdam, NL');

    checkinBackfill()->up();

    $template->refresh();
    expect($template->css)->toBe(checkinBackfillConstant('NEW_CSS'));

    checkinLandTonesUpgrade()->up();

    $template->refresh();
    $controls = $template->controls()->get()->keyBy('key');
    $classic = collect(json_decode(file_get_contents(base_path('resources/recipes/chat-checkin/manifest.json')), true)['designer']['presets'])
        ->firstWhere('key', 'classic')['values'];

    expect($template->css)->toBe($freshCss)
        ->and(substr_count($template->html, checkinBackfillConstant('HTML_TO')))->toBe(1)
        ->and($template->template_tags)->toContain('c:land_color', 'c:hometown', 'if:c:hometown', 'c:land_tones', 'c:font_bold')
        ->and($controls['hometown']->value)->toBe('Rotterdam, NL');

    // Every look control is at the Classic preset, so the designer shows
    // Classic as the active look and nothing on stream changed.
    foreach ($classic as $key => $value) {
        expect((string) $controls[$key]->value)->toBe($value, "control {$key}");
    }

    // Everything but the value matches what a fresh install creates.
    foreach ([...array_column(checkinBackfillConstant('CONTROLS'), 0), 'land_tones', 'font_bold'] as $key) {
        expect($controls[$key]->only(['label', 'type', 'config', 'description']))
            ->toBe($fresh[$key]->only(['label', 'type', 'config', 'description']), "control {$key}");
    }
});

it('leaves the hometown empty without an old home', function () {
    $user = checkinBackfillUser();
    $template = checkinBackfillInstall($user);
    makePreDesigner($template);

    checkinBackfill()->up();

    expect($template->controls()->where('key', 'hometown')->value('value'))->toBe('');
});

it('leaves an install whose CSS was edited exactly as it is', function () {
    $user = checkinBackfillUser();
    $template = checkinBackfillInstall($user);
    makePreDesigner($template);
    $edited = str_replace('#2dd4bf', '#ff00ff', checkinBackfillConstant('OLD_CSS'));
    $template->update(['css' => $edited]);
    $before = $template->controls()->count();

    checkinBackfill()->up();

    $template->refresh();
    expect($template->css)->toBe($edited)
        ->and($template->controls()->count())->toBe($before);
});

it('does nothing to an install that already has the designer controls', function () {
    $template = checkinBackfillInstall(checkinBackfillUser());
    $before = $template->controls()->count();
    $css = $template->css;

    checkinBackfill()->up();

    $template->refresh();
    expect($template->controls()->count())->toBe($before)
        ->and($template->css)->toBe($css);
});
