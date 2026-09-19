<?php

use App\Services\Recipes\RecipeCatalog;

/**
 * `public/llms.txt` is hand-written, and §12 names every product in a table.
 * A hand-maintained list of derived things is the exact shape that rotted the
 * sitemap by fourteen pages before it was derived from the corpus. llms.txt
 * cannot be derived - it is prose with judgment in it - so it gets a guard
 * instead: the table and the catalogue have to agree in both directions.
 */
function llmsTxt(): string
{
    return (string) file_get_contents(public_path('llms.txt'));
}

/**
 * @return list<string>
 */
function llmsTxtProductSlugs(): array
{
    preg_match_all('#/products/([a-z][a-z0-9-]*)#', llmsTxt(), $m);

    return array_values(array_unique($m[1]));
}

it('names every listed product in llms.txt', function () {
    $listed = array_keys(app(RecipeCatalog::class)->listed());
    $named = llmsTxtProductSlugs();

    $missing = array_values(array_diff($listed, $named));

    expect($missing)->toBe([], 'Products missing from llms.txt: '.implode(', ', $missing));
});

it('names no product in llms.txt that is not a current listed slug', function () {
    $listed = array_keys(app(RecipeCatalog::class)->listed());
    $named = llmsTxtProductSlugs();

    // An alias is a 301, so naming one here would send every reader through a
    // redirect and would mean the file is describing the pre-rename world.
    $stale = array_values(array_diff($named, $listed));

    expect($stale)->toBe([], 'llms.txt names slugs that are not current listed products: '.implode(', ', $stale));
});

it('points at the schema URL the schema claims as its own $id', function () {
    $schema = json_decode(
        (string) file_get_contents(base_path('resources/recipes/recipe-manifest.schema.json')),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    expect(llmsTxt())->toContain($schema['$id']);
});

/**
 * Read from disk, not fetched: `/llms.txt` is a real file under public/ and is
 * served by the web server without reaching Laravel, so there is no route to
 * hit and `$this->get('/llms.txt')` answers 404 in the test environment.
 */
it('carries the products section and the schema link', function () {
    expect(llmsTxt())->toContain('## 12. Products')
        ->and(llmsTxt())->toContain('/products/twitch-chat-overlay')
        ->and(llmsTxt())->toContain('https://overlabels.com/schemas/recipe-manifest/v1.json');
});
