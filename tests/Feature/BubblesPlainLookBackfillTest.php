<?php

use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Support\OverlayMarkdown;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * The 2026-11-19 backfill: Chat Emote Bubbles installs from before the Plain
 * look get it as a fourth choice on their `look` row, and nothing else moves.
 */
function plainBackfill(): Migration
{
    return require base_path('database/migrations/2026_11_19_120000_add_plain_look_to_installed_chat_emote_bubbles.php');
}

function plainUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'plain'.fake()->unique()->randomNumber(5)],
    ]);
}

function plainInstall(User $user): OverlayTemplate
{
    $catalog = app(RecipeCatalog::class);
    $recipe = $catalog->sync($catalog->find('chat-emote-bubbles'));
    $instance = app(RecipeInstaller::class)->install($recipe, $user, RecipeInstance::instanceSlugFrom('chat-emote-bubbles'));

    return OverlayTemplate::findOrFail($instance->primitive_map['overlays']['bubbles']);
}

function lookRow(OverlayTemplate $template): OverlayControl
{
    return $template->controls()->where('key', 'look')->firstOrFail();
}

/** What an install from before Plain looks like: three choices and no plain rule. */
function makePrePlain(OverlayTemplate $template): void
{
    $rule = (new ReflectionClass(plainBackfill()))->getConstant('CSS_RULE');
    expect(substr_count($template->css, $rule))->toBe(1);
    $template->update(['css' => str_replace($rule, '', $template->css)]);

    $row = lookRow($template);
    $config = $row->config;
    $config['choices'] = array_values(array_filter($config['choices'], fn ($c) => $c['value'] !== 'plain'));
    $row->update(['config' => $config]);
}

it('gives an install from before Plain exactly the choices a fresh install has now', function () {
    $template = plainInstall(plainUser());
    $expected = lookRow($template)->config;
    $expectedCss = $template->css;
    $declared = collect(OverlayMarkdown::parse(file_get_contents(RecipeInstaller::directoryFor('chat-emote-bubbles').DIRECTORY_SEPARATOR.'bubbles.md'))['controls'])
        ->keyBy('key')['look']['config']['choices'];

    makePrePlain($template);
    lookRow($template)->update(['value' => 'heart']);
    expect(array_column(lookRow($template)->config['choices'], 'value'))->toBe(['bubble', 'snow', 'heart']);

    plainBackfill()->up();

    expect($template->fresh()->css)->toBe($expectedCss)
        ->and(lookRow($template)->config)->toBe($expected)
        ->and(lookRow($template)->config['choices'])->toBe($declared)
        ->and(lookRow($template)->value)->toBe('heart');
});

it('runs twice without adding Plain twice', function () {
    $template = plainInstall(plainUser());
    makePrePlain($template);

    plainBackfill()->up();
    $configAfterFirst = lookRow($template)->config;
    $cssAfterFirst = $template->fresh()->css;

    plainBackfill()->up();

    expect(lookRow($template)->config)->toBe($configAfterFirst)
        ->and($template->fresh()->css)->toBe($cssAfterFirst)
        ->and(array_column($configAfterFirst['choices'], 'value'))->toBe(['bubble', 'snow', 'heart', 'plain'])
        ->and(substr_count($cssAfterFirst, '.look-plain'))->toBe(1);
});

it('still offers Plain when the streamer rewrote the heart emote rule, and leaves their CSS alone', function () {
    $template = plainInstall(plainUser());
    makePrePlain($template);
    $anchor = (new ReflectionClass(plainBackfill()))->getConstant('CSS_ANCHOR');
    $template->update(['css' => str_replace($anchor, '.look-heart .skin img { height: 50px; }', $template->fresh()->css)]);
    $css = $template->fresh()->css;

    plainBackfill()->up();

    expect($template->fresh()->css)->toBe($css)
        ->and(array_column(lookRow($template)->config['choices'], 'value'))->toBe(['bubble', 'snow', 'heart', 'plain']);
});

it('leaves a row with any other list, and a hand-made look control, alone', function () {
    $user = plainUser();
    $template = plainInstall($user);
    $custom = [['value' => 'bubble', 'label' => 'Only bubbles', 'hint' => '']];
    lookRow($template)->update(['config' => ['choices' => $custom]]);

    $own = OverlayTemplate::factory()->create(['owner_id' => $user->id, 'fork_of_id' => null, 'type' => 'static']);
    $hand = OverlayControl::createForTemplate($own, $user, ['key' => 'look', 'type' => 'text', 'value' => 'anything']);
    $hand->update(['config' => ['choices' => [
        ['value' => 'bubble', 'label' => 'Bubble', 'hint' => ''],
        ['value' => 'snow', 'label' => 'Snow', 'hint' => ''],
        ['value' => 'heart', 'label' => 'Heart', 'hint' => ''],
    ]]]);
    $handBefore = $hand->fresh()->config;

    plainBackfill()->up();

    expect(lookRow($template)->config['choices'])->toBe($custom)
        ->and($hand->fresh()->config)->toBe($handBefore);
});
