<?php

use App\Services\Recipes\RecipeManifestValidator;

/**
 * Helper: returns a deep-cloned copy of the canonical Coin Flip manifest so
 * individual cases can tweak one field without polluting other tests.
 */
function coinFlipManifest(): array
{
    $json = file_get_contents(base_path('resources/recipes/coin_flip/manifest.json'));

    return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
}

function findError(array $errors, string $pointer): ?array
{
    foreach ($errors as $err) {
        if ($err['pointer'] === $pointer) {
            return $err;
        }
    }

    return null;
}

it('accepts the canonical Coin Flip manifest', function () {
    $result = (new RecipeManifestValidator)->validate(coinFlipManifest());

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBe([]);
});

it('accepts the canonical Coin Flip manifest when loaded via validateFile', function () {
    $result = (new RecipeManifestValidator)
        ->validateFile(base_path('resources/recipes/coin_flip/manifest.json'));

    expect($result['valid'])->toBeTrue();
});

it('rejects a slug with a dash', function () {
    $m = coinFlipManifest();
    $m['slug'] = 'coin-flip';

    $result = (new RecipeManifestValidator)->validate($m);

    expect($result['valid'])->toBeFalse();
    expect(findError($result['errors'], '/slug'))->not->toBeNull();
});

it('rejects a slug that starts with a number', function () {
    $m = coinFlipManifest();
    $m['slug'] = '1coin';

    $result = (new RecipeManifestValidator)->validate($m);

    expect($result['valid'])->toBeFalse();
    expect(findError($result['errors'], '/slug'))->not->toBeNull();
});

it('rejects an unknown recipe_format_version', function () {
    $m = coinFlipManifest();
    $m['recipe_format_version'] = 2;

    $result = (new RecipeManifestValidator)->validate($m);

    expect($result['valid'])->toBeFalse();
    expect(findError($result['errors'], '/recipe_format_version'))->not->toBeNull();
});

it('rejects unknown top-level properties', function () {
    $m = coinFlipManifest();
    $m['renderers'] = [['ref' => 'wheel']]; // deliberately removed from v1

    $result = (new RecipeManifestValidator)->validate($m);

    expect($result['valid'])->toBeFalse();
});

it('flags a picker referencing an undeclared option_set', function () {
    $m = coinFlipManifest();
    $m['primitives']['pickers'][0]['option_set_ref'] = 'missing_set';

    $result = (new RecipeManifestValidator)->validate($m);

    $err = findError($result['errors'], '/primitives/pickers/0/option_set_ref');
    expect($result['valid'])->toBeFalse()
        ->and($err)->not->toBeNull()
        ->and($err['message'])->toContain('missing_set');
});

it('flags a control_export referencing an undeclared picker', function () {
    $m = coinFlipManifest();
    $m['control_exports'][0]['from'] = 'pickers.missing_picker.result';

    $result = (new RecipeManifestValidator)->validate($m);

    $err = findError($result['errors'], '/control_exports/0/from');
    expect($result['valid'])->toBeFalse()
        ->and($err)->not->toBeNull()
        ->and($err['message'])->toContain('missing_picker');
});

it('flags a trigger that fires an undeclared picker', function () {
    $m = coinFlipManifest();
    $m['triggers'][0]['fires'] = 'pickers.missing_picker';

    $result = (new RecipeManifestValidator)->validate($m);

    $err = findError($result['errors'], '/triggers/0/fires');
    expect($result['valid'])->toBeFalse()
        ->and($err)->not->toBeNull();
});

it('rejects an unknown permission level on a chat_command trigger', function () {
    $m = coinFlipManifest();
    $m['triggers'][0]['permissions'] = 'godmode';

    $result = (new RecipeManifestValidator)->validate($m);

    expect($result['valid'])->toBeFalse();
});

it('rejects an unknown concurrency mode on a picker', function () {
    $m = coinFlipManifest();
    $m['primitives']['pickers'][0]['concurrency'] = 'super_yolo';

    $result = (new RecipeManifestValidator)->validate($m);

    expect($result['valid'])->toBeFalse();
});

it('rejects a duplicate option_set ref', function () {
    $m = coinFlipManifest();
    $m['primitives']['option_sets'][] = [
        'ref' => 'coin',
        'label' => 'Another coin',
        'items' => ['A', 'B'],
    ];

    $result = (new RecipeManifestValidator)->validate($m);

    expect($result['valid'])->toBeFalse();
    expect(findError($result['errors'], '/primitives/option_sets/1/ref'))->not->toBeNull();
});

it('rejects a duplicate picker ref', function () {
    $m = coinFlipManifest();
    $m['primitives']['pickers'][] = [
        'ref' => 'flipper',
        'label' => 'Another flipper',
        'option_set_ref' => 'coin',
    ];

    $result = (new RecipeManifestValidator)->validate($m);

    expect($result['valid'])->toBeFalse();
    expect(findError($result['errors'], '/primitives/pickers/1/ref'))->not->toBeNull();
});

it('rejects a duplicate control_export name', function () {
    $m = coinFlipManifest();
    $m['control_exports'][] = [
        'name' => 'result',
        'from' => 'pickers.flipper.running',
    ];

    $result = (new RecipeManifestValidator)->validate($m);

    expect($result['valid'])->toBeFalse();
    expect(findError($result['errors'], '/control_exports/3/name'))->not->toBeNull();
});

it('rejects a control_export pointing at a non-supported picker field', function () {
    $m = coinFlipManifest();
    $m['control_exports'][0]['from'] = 'pickers.flipper.secret_internal_state';

    $result = (new RecipeManifestValidator)->validate($m);

    expect($result['valid'])->toBeFalse();
    expect(findError($result['errors'], '/control_exports/0/from'))->not->toBeNull();
});

it('rejects a chat_command without the leading !', function () {
    $m = coinFlipManifest();
    $m['triggers'][0]['command'] = 'flip';

    $result = (new RecipeManifestValidator)->validate($m);

    expect($result['valid'])->toBeFalse();
});

it('rejects a JSON string input that does not parse', function () {
    expect(fn () => (new RecipeManifestValidator)->validate('not json{'))
        ->toThrow(JsonException::class);
});
