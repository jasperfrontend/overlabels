<?php

use App\Services\Recipes\RecipeInstaller;
use App\Services\Recipes\RecipeManifestValidator;
use App\Support\ProductDesigner;

/**
 * The manifest's `designer` block, held against the overlay it designs.
 *
 * The knobs are the overlay's own controls, so the block is only right next
 * to the document: every key it names must be declared, every control the
 * designer can render must have exactly one home, a preset must be a complete
 * look with values the rows accept, and the skin strip must be one button per
 * preset. Each rule here names the violation the validator reports, so a
 * manifest that would give a product a broken designer fails the catalogue
 * read rather than a streamer's page.
 */
function designerBlockManifest(string $slug = 'twitch-chat-overlay'): array
{
    return json_decode(file_get_contents(RecipeInstaller::directoryFor($slug).'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
}

function designerBlockErrors(array $manifest, string $slug = 'twitch-chat-overlay'): array
{
    return (new RecipeManifestValidator)->validate($manifest, RecipeInstaller::directoryFor($slug))['errors'];
}

function designerBlockMessages(array $manifest, string $slug = 'twitch-chat-overlay'): array
{
    return array_column(designerBlockErrors($manifest, $slug), 'message');
}

it('accepts both shipped designer blocks next to their documents', function () {
    foreach (['twitch-chat-overlay', 'chat-emote-bubbles'] as $slug) {
        expect(designerBlockErrors(designerBlockManifest($slug), $slug))->toBe([]);
    }
});

it('rejects a designer whose overlay is not one the recipe installs', function () {
    $m = designerBlockManifest();
    $m['designer']['overlay'] = 'sidebar';

    expect(designerBlockMessages($m))->toContain('Designer references unknown overlay "sidebar".');
});

it('rejects a grouped key the document does not declare', function () {
    $m = designerBlockManifest();
    $m['designer']['groups'][0]['keys'][] = 'glow';

    expect(designerBlockMessages($m))->toContain('Control "glow" is not declared by chat.md.');
});

it('rejects a control grouped twice', function () {
    $m = designerBlockManifest();
    $m['designer']['groups'][1]['keys'][] = 'layout';

    expect(designerBlockMessages($m))->toContain('Control "layout" is in more than one group.');
});

it('rejects a knob-type control with no home on the page', function () {
    // Every text, number, boolean and color control the document declares
    // has to be in a group (or be the skin strip), or it is a control the
    // overlay has that the designer silently never shows.
    $m = designerBlockManifest();
    $m['designer']['groups'][0]['keys'] = ['layout'];

    expect(designerBlockMessages($m))->toContain('Control "lifetime" (number) has no home on the designer: put it in a group.');
});

it('rejects the skin key sitting in a group', function () {
    $m = designerBlockManifest();
    $m['designer']['groups'][0]['keys'][] = 'skin';

    expect(designerBlockMessages($m))->toContain('Control "skin" is the skin_key and has the strip above the groups, not a row in one.');
});

it('rejects a skin key the document does not declare, and one that is not a text control', function () {
    $m = designerBlockManifest();
    $m['designer']['skin_key'] = 'theme';
    expect(designerBlockMessages($m))->toContain('Control "theme" is not declared by chat.md.');

    $m = designerBlockManifest();
    $m['designer']['skin_key'] = 'font_size';
    $m['designer']['groups'][1]['keys'] = ['font', 'emote_size'];
    $m['designer']['groups'][0]['keys'][] = 'skin';
    expect(designerBlockMessages($m))->toContain('Control "font_size" is a number control; the skin strip writes a text control.');
});

it('rejects a preset that is not a complete look', function () {
    $m = designerBlockManifest();
    unset($m['designer']['presets'][2]['values']['accent']);
    $m['designer']['presets'][2]['values']['sparkle'] = '1';

    $messages = designerBlockMessages($m);
    expect($messages)->toContain('Preset "bubbles" does not set "accent". A preset is a complete look.')
        ->and($messages)->toContain('Preset "bubbles" sets "sparkle", which is not a designer key.');
});

it('rejects a preset value outside the control\'s declared choices', function () {
    $m = designerBlockManifest();
    $m['designer']['presets'][0]['values']['layout'] = 'sideways';

    expect(designerBlockMessages($m))->toContain('Preset "clean" sets layout to "sideways", which is not one of its choices (bottom, top, ticker).');
});

it('rejects a preset whose skin is not its own key', function () {
    $m = designerBlockManifest();
    $m['designer']['presets'][1]['values']['skin'] = 'clean';

    expect(designerBlockMessages($m))->toContain('Preset "terminal" sets skin to "clean"; the strip is one button per preset, so it must be "terminal".');
});

it('rejects a duplicate preset key and a duplicate group title', function () {
    $m = designerBlockManifest();
    $m['designer']['presets'][3]['key'] = 'clean';
    $m['designer']['presets'][3]['values']['skin'] = 'clean';
    $m['designer']['groups'][2]['title'] = 'Layout';

    $messages = designerBlockMessages($m);
    expect($messages)->toContain('Duplicate preset key "clean".')
        ->and($messages)->toContain('Duplicate group title "Layout".');
});

it('rejects a control of a type the designer has no knob for', function () {
    // A counter on the overlay is a Values-tab thing, not a look. Grouping it
    // would render a slider that writes a counter.
    $m = designerBlockManifest();
    $document = file_get_contents(RecipeInstaller::directoryFor('twitch-chat-overlay').'/chat.md');
    expect($document)->not->toContain('c:hits');

    // Stage a copy of the recipe with one counter control added, so the
    // document side of the check is exercised without touching the shipped file.
    $dir = sys_get_temp_dir().'/ol-designer-'.uniqid();
    mkdir($dir);
    $row = "| `[[[c:emote_size]]]` | number | Emote size | `28` | yes |\n";
    expect($document)->toContain($row);
    file_put_contents($dir.'/chat.md', str_replace($row, $row."| `[[[c:hits]]]` | counter | Hits | `0` | no |\n", $document));
    $m['designer']['groups'][0]['keys'][] = 'hits';

    try {
        $messages = array_column((new RecipeManifestValidator)->validate($m, $dir)['errors'], 'message');
    } finally {
        unlink($dir.'/chat.md');
        rmdir($dir);
    }

    expect($messages)->toContain('Control "hits" is a counter control, which the designer has no knob for.');
});

it('rejects a stage condition on an undeclared control or an unknown choice', function () {
    $m = designerBlockManifest();
    $m['designer']['stage'][] = ['when' => ['mood' => 'dark'], 'w' => 10, 'h' => 10];
    $m['designer']['stage'][] = ['when' => ['layout' => 'sideways'], 'w' => 10, 'h' => 10];

    $messages = designerBlockMessages($m);
    expect($messages)->toContain('Stage condition names "mood", which chat.md does not declare.')
        ->and($messages)->toContain('Stage condition sets layout to "sideways", which is not one of its choices (bottom, top, ticker).');
});

it('rejects an extra the page does not know, and keeps the schema enum equal to the class constant', function () {
    $m = designerBlockManifest();
    $m['designer']['extras'][] = 'weather';

    $errors = designerBlockErrors($m);
    expect(array_column($errors, 'pointer'))->toContain('/designer/extras/3');

    // The page's switch and the schema's enum are the same closed list, in
    // two files. A name added to one and not the other is a manifest that
    // validates and a knob that never renders, or the reverse.
    $schema = json_decode(file_get_contents(base_path('resources/recipes/recipe-manifest.schema.json')), true, 512, JSON_THROW_ON_ERROR);
    expect($schema['properties']['designer']['properties']['extras']['items']['enum'])->toBe(ProductDesigner::EXTRAS);
});

it('checks only the block itself without the recipe directory', function () {
    // Without the document the key checks cannot run, but the block's own
    // consistency and the overlay ref still can.
    $m = designerBlockManifest();
    $m['designer']['overlay'] = 'sidebar';
    $m['designer']['groups'][1]['keys'][] = 'layout';
    $m['designer']['groups'][0]['keys'][] = 'glow';

    $messages = array_column((new RecipeManifestValidator)->validate($m)['errors'], 'message');
    expect($messages)->toContain('Designer references unknown overlay "sidebar".')
        ->and($messages)->toContain('Control "layout" is in more than one group.')
        ->and($messages)->not->toContain('Control "glow" is not declared by chat.md.');
});
