<?php

use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeManifestValidator;

/**
 * The recipe manifest schema is a public document. A JSON Schema's `$id` is
 * where it claims to live, so the claim and the route have to agree: a `$ref`
 * written against the `$id` resolves by fetching it, and for months that fetch
 * returned a 404 page.
 */
it('serves the schema at the exact URL its $id claims', function () {
    $path = base_path('resources/recipes/recipe-manifest.schema.json');
    $schema = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

    expect($schema['$id'])->toBe('https://overlabels.com/schemas/recipe-manifest/v1.json');

    // The path half of that URL is the route it must be reachable on.
    expect(parse_url($schema['$id'], PHP_URL_PATH))->toBe('/schemas/recipe-manifest/v1.json')
        ->and(route('schemas.recipe-manifest', [], false))->toBe('/schemas/recipe-manifest/v1.json');
});

it('serves the file the validator reads, byte for byte', function () {
    $onDisk = (string) file_get_contents(base_path('resources/recipes/recipe-manifest.schema.json'));

    $response = $this->get('/schemas/recipe-manifest/v1.json')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/schema+json')
        ->and($response->getContent())->toBe($onDisk);
});

it('publishes a schema that is valid JSON and describes the manifest keys in use', function () {
    $schema = json_decode(
        (string) $this->get('/schemas/recipe-manifest/v1.json')->getContent(),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    expect($schema['$schema'])->toBe('https://json-schema.org/draft/2020-12/schema')
        ->and($schema['additionalProperties'])->toBeFalse();

    // additionalProperties is false, so every key any shipped manifest uses has
    // to be declared or that manifest would not validate. This is the check that
    // catches a key added to a product and never added to the published schema.
    $declared = array_keys($schema['properties']);
    $undeclared = [];

    foreach (app(RecipeCatalog::class)->all() as $slug => $manifest) {
        foreach (array_keys($manifest) as $key) {
            // The catalogue injects the manifest's own directory on read.
            if ($key === 'directory') {
                continue;
            }
            if (! in_array($key, $declared, true)) {
                $undeclared[] = "{$slug}.{$key}";
            }
        }
    }

    expect($undeclared)->toBe([], 'Manifest keys missing from the published schema: '.implode(', ', $undeclared));
});

it('validates every shipped manifest against the published schema', function () {
    $validator = app(RecipeManifestValidator::class);

    foreach (app(RecipeCatalog::class)->all() as $slug => $manifest) {
        $result = $validator->validateFile(
            base_path("resources/recipes/{$slug}/manifest.json")
        );

        expect($result['valid'])->toBeTrue(
            "Manifest {$slug} does not validate: ".collect($result['errors'])->pluck('message')->implode('; ')
        );
    }
});
