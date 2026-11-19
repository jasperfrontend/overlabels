<?php

use App\Models\OverlayTemplate;
use Illuminate\Database\Migrations\Migration;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/** The 2026-11-19 Chat Emote Bubbles shapes backfill. */
function shapesBackfill(): Migration
{
    return require base_path('database/migrations/2026_11_19_170000_add_shapes_to_installed_chat_emote_bubbles.php');
}

/**
 * Turn a fresh Chat Emote Bubbles install back into one from before the
 * shapes: the backfill's own patches run backwards, the four rows deleted and
 * the order closed up, the look select and the three descriptions as they
 * shipped, and the tags taken out of the allowlist.
 *
 * Shared because every older bubbles backfill test installs from today's
 * recipe and must first be put back to the recipe its migration was written
 * against, the one the shapes backfill runs after.
 */
function makePreShapes(OverlayTemplate $template): void
{
    $migration = new ReflectionClass(shapesBackfill());

    $html = $template->html;
    foreach (array_reverse($migration->getConstant('HTML_PATCHES')) as $patch) {
        expect(substr_count($html, $patch['to']))->toBe(1);
        $html = str_replace($patch['to'], $patch['from'], $html);
    }

    $css = $template->css;
    foreach (array_reverse($migration->getConstant('CSS_PATCHES')) as $patch) {
        expect(substr_count($css, $patch['to']))->toBe(1);
        $css = str_replace($patch['to'], $patch['from'], $css);
    }

    $template->controls()->whereIn('key', ['shape', 'border_width', 'fill_strength', 'breathing_room'])->delete();
    foreach ($template->controls()->orderBy('sort_order')->get() as $i => $control) {
        $control->sort_order = $i;
        $control->saveQuietly();
    }

    $look = $template->controls()->where('key', 'look')->first();
    $look->config = ['choices' => $migration->getConstant('SHIPPED_LOOKS')];
    $look->saveQuietly();

    foreach ($migration->getConstant('DESCRIPTIONS') as $key => [$shipped]) {
        $template->controls()->where('key', $key)->update(['description' => $shipped]);
    }

    $template->update([
        'html' => $html,
        'css' => $css,
        'template_tags' => array_values(array_diff($template->template_tags, ['c:shape', 'c:border_width', 'c:fill_strength', 'c:breathing_room'])),
    ]);
}
