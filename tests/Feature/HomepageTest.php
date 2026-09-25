<?php

use App\Services\Recipes\RecipeCatalog;

/**
 * The homepage's product shelf and alerts row are read from the recipe
 * catalogue at request time, so they can never drift from /products. These
 * tests pin that, and that the builder half of the page (the original
 * homepage, which is what the page ranks on) is still there under its fold.
 */
it('lists every product on the Products shelf with a link to its page', function () {
    $response = $this->get('/')->assertOk();

    $games = collect(app(RecipeCatalog::class)->listed())->where('category', 'product');

    expect($games)->not->toBeEmpty();

    foreach ($games as $game) {
        $response->assertSee($game['name']);
        $response->assertSee('href="'.route('products.show', $game['slug']).'"', escape: false);
    }
});

it('lists every alert product with a link to its page', function () {
    $response = $this->get('/')->assertOk();

    $alerts = collect(app(RecipeCatalog::class)->listed())->where('category', 'alert');

    expect($alerts)->not->toBeEmpty();

    foreach ($alerts as $alert) {
        $response->assertSee($alert['name']);
        $response->assertSee('href="'.route('products.show', $alert['slug']).'"', escape: false);
    }
});

it('keeps every builder section and its anchor under the fold', function () {
    $response = $this->get('/')->assertOk();

    foreach (['products', 'how-it-works', 'alerts', 'build', 'tags', 'controls', 'conditionals', 'events', 'integrations', 'kits', 'get-started'] as $anchor) {
        $response->assertSee('id="'.$anchor.'"', escape: false);
    }

    // The original headings, verbatim: the words the page already ranks on.
    $response->assertSee('Simple tags.');
    $response->assertSee('Typed, mutable overlay state.');
    $response->assertSee('A comparison engine in your template.');
    $response->assertSee('Every Twitch event. One syntax.');
    $response->assertSee('Show donations from different sources.');
    $response->assertSee('Good design compounds.');
});

it('sells to streamers in the title, not to coders', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('<title>Overlabels', escape: false)
        ->assertDontSee('for people who code');
});
