<?php

use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Services\Recipes\RecipeManifestValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * The nine listed products moved from snake_case slugs to the hyphenated form
 * their public URLs use. Three things have to stay true, and each one was
 * verified to fail before it was made true.
 */
it('301s every slug a product used to live at to the slug it lives at now', function () {
    $catalog = app(RecipeCatalog::class);

    foreach ($catalog->listed() as $slug => $manifest) {
        foreach ($manifest['url_aliases'] ?? [] as $alias) {
            $this->get("/products/{$alias}")
                ->assertStatus(301)
                ->assertRedirect(route('products.show', $slug));
        }
    }
});

it('gives every listed product an alias, so no old URL is left dead', function () {
    $catalog = app(RecipeCatalog::class);

    // Not a rule for all time - it is true because all nine were renamed at
    // once. A product first published under its current slug has nothing to
    // alias and would be added to this exemption list.
    $bornHyphenated = [];

    foreach ($catalog->listed() as $slug => $manifest) {
        if (in_array($slug, $bornHyphenated, true)) {
            continue;
        }

        expect($manifest['url_aliases'] ?? [])
            ->not->toBeEmpty("Product {$slug} has no url_aliases, so its old URL 404s.");
    }
});

it('keeps the sitemap naming every listed product at its current slug', function () {
    $body = $this->get('/sitemap.xml')->assertOk()->getContent();

    foreach (array_keys(app(RecipeCatalog::class)->listed()) as $slug) {
        expect($body)->toContain("https://overlabels.com/products/{$slug}");
    }

    // And never at one it has moved away from.
    foreach (app(RecipeCatalog::class)->listed() as $manifest) {
        foreach ($manifest['url_aliases'] ?? [] as $alias) {
            expect($body)->not->toContain("https://overlabels.com/products/{$alias}");
        }
    }
});

/**
 * The one that actually bit. A product slug is a URL and carries hyphens; the
 * instance slug it is installed under is a control identifier and must not.
 * Passing the product slug straight through made every install throw.
 */
it('installs a hyphenated product under a legal instance slug', function () {
    $catalog = app(RecipeCatalog::class);
    $user = User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'slugrename'.fake()->unique()->randomNumber(5)],
    ]);

    $manifest = $catalog->find('twitch-chat-overlay');
    $instance = app(RecipeInstaller::class)->install(
        $catalog->sync($manifest),
        $user,
        RecipeInstance::instanceSlugFrom('twitch-chat-overlay'),
    );

    expect($instance->instance_slug)->toBe('twitch_chat_overlay')
        ->and($instance->instance_slug)->toMatch(RecipeInstance::SLUG_PATTERN)
        ->and($instance->recipe->slug)->toBe('twitch-chat-overlay');
});

it('never lets a hyphen reach a control identifier through the instance slug', function () {
    foreach (array_keys(app(RecipeCatalog::class)->listed()) as $slug) {
        expect(RecipeInstance::instanceSlugFrom($slug))->toMatch(RecipeInstance::SLUG_PATTERN);
    }
});

/**
 * A recipe whose slug becomes part of [[[c:<slug>:<instance>:<key>]]] cannot
 * take the hyphens the URL rename introduced. The schema cannot express
 * "hyphens unless this other key is present", so the validator says it.
 */
it('refuses a hyphenated slug on a recipe that exports control tags', function () {
    $result = app(RecipeManifestValidator::class)->validate([
        'recipe_format_version' => 1,
        'slug' => 'my-picker',
        'name' => 'My Picker',
        'version' => 1,
        'description' => 'x',
        'author' => ['name' => 'Overlabels'],
        'control_exports' => [['ref' => 'p', 'from' => 'pickers.p.result', 'key' => 'result']],
    ]);

    expect($result['valid'])->toBeFalse()
        ->and(collect($result['errors'])->pluck('message')->implode(' '))
        ->toContain('Control identifiers use underscores');
});
