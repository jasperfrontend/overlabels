<?php

use App\Models\OverlayTemplate;
use App\Models\User;
use App\Support\HelpContext;
use App\Support\HelpPage;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Contextual help is declared by the help pages themselves, in a `context:`
 * frontmatter line naming the route names they cover. These tests pin the
 * matching rules, and - more usefully - guard the two ways the association rots
 * over time: a route gets renamed and a page quietly points at nothing, or
 * generic routes slowly accumulate every page that mentions them until the help
 * affordance is a link farm.
 */
it('matches a page that named the route outright', function () {
    $slugs = array_column(HelpContext::for('lists.index'), 'slug');

    expect($slugs)->toContain('lists')
        ->and(HelpContext::for('lists.index')[0])->toHaveKeys(['slug', 'title', 'lead', 'url']);
});

it('carries card copy short enough for a 375px panel', function () {
    // The beacon shows the page's own `heading` and `lead`, not its `title` and
    // `description` - those are written for a browser tab and a search result,
    // and the title in particular runs long ("Blocks - reusable building pieces
    // for the Builder"). Every page that claims a context has to be presentable.
    foreach (HelpPage::all() as $slug) {
        if (HelpContext::declared($slug) === []) {
            continue;
        }

        $card = HelpContext::for(HelpContext::declared($slug)[0]['pattern'], HelpContext::declared($slug)[0]['constraints']);
        $card = collect($card)->firstWhere('slug', $slug);

        expect($card['title'])->not->toBeEmpty()
            ->and(strlen($card['title']))->toBeLessThanOrEqual(40, "{$slug} has a long panel title")
            ->and($card['lead'])->not->toBeEmpty("{$slug} has no lead to preview")
            ->and(strlen($card['lead']))->toBeLessThanOrEqual(320, "{$slug} has a long panel lead");
    }
});

it('returns nothing for a route no page claimed', function () {
    expect(HelpContext::for('privacy'))->toBeEmpty()
        ->and(HelpContext::for('does.not.exist'))->toBeEmpty();
});

it('ranks a page that pinned down a query constraint above one that did not', function () {
    // /templates?type=block: blocks.md declared the type, overlays-vs-alerts
    // only declared the bare route, so blocks leads.
    $slugs = array_column(HelpContext::for('templates.index', ['type' => 'block']), 'slug');

    expect($slugs[0])->toBe('blocks')
        ->and($slugs)->toContain('overlays-vs-alerts');
});

it('ignores query parameters no page constrained on', function () {
    // Declared constraints must match; everything else in the URL is noise, so
    // search, sort and pagination can never break a match.
    $slugs = array_column(HelpContext::for('templates.index', [
        'type' => 'block',
        'filter' => 'mine',
        'search' => 'goal',
        'sort' => 'name',
        'page' => '2',
    ]), 'slug');

    expect($slugs[0])->toBe('blocks');
});

it('withholds a constrained page when the constraint is absent or different', function () {
    $bare = array_column(HelpContext::for('templates.index'), 'slug');
    $alert = array_column(HelpContext::for('templates.index', ['type' => 'alert']), 'slug');

    expect($bare)->not->toContain('blocks')
        ->and($alert)->not->toContain('blocks');
});

it('offers a page once even when several of its contexts match', function () {
    // A page may declare a bare route and a narrowed one. It earns its best
    // score, not one entry per declaration.
    $slugs = array_column(HelpContext::for('templates.blocks.library'), 'slug');

    expect($slugs)->toBe(array_unique($slugs));
});

it('prefers the more literal wildcard', function () {
    // settings.bot.expressions.* is a more deliberate claim on the expressions
    // pages than the settings.bot.* catch-all.
    $slugs = array_column(HelpContext::for('settings.bot.expressions.index'), 'slug');

    expect($slugs[0])->toBe('bot/expressions')
        ->and($slugs)->toContain('bot/index');
});

it('reads context the URL does not carry', function () {
    // /templates/{template} serves blocks, alerts and static overlays alike;
    // the discriminator is the model. A controller injects it, and it matches
    // through the same path as a real query parameter.
    $slugs = array_column(HelpContext::for('templates.show', ['type' => 'alert']), 'slug');

    expect($slugs)->toContain('overlays-vs-alerts')
        ->and(array_column(HelpContext::for('templates.show', ['type' => 'block']), 'slug'))
        ->toContain('blocks')
        ->and(HelpContext::for('templates.show', ['type' => 'static']))->toBeEmpty();
});

it('points every declared context at a route that exists', function () {
    // The failure this catches: a route is renamed, and contextual help goes
    // silently dead. A central route-to-page map would catch it too; this is
    // the price of keeping the association in the markdown.
    $names = collect(Route::getRoutes()->getRoutesByName())->keys();

    foreach (HelpPage::all() as $slug) {
        foreach (HelpContext::declared($slug) as $entry) {
            $pattern = $entry['pattern'];

            $matched = str_contains($pattern, '*')
                ? $names->contains(fn (string $name): bool => Str::is($pattern, $name))
                : $names->contains($pattern);

            expect($matched)->toBeTrue("{$slug} declares context '{$pattern}', which matches no route");
        }
    }
});

it('keeps any single context down to three pages', function () {
    // Left alone, a generic route like templates.index accumulates every page
    // that mentions templates until the help affordance is a link farm. Making
    // that a test failure means a loose `context:` line is caught when it is
    // written, not two years later.
    foreach (HelpPage::all() as $slug) {
        foreach (HelpContext::declared($slug) as $entry) {
            if (str_contains($entry['pattern'], '*')) {
                continue;
            }

            $resolved = HelpContext::for($entry['pattern'], $entry['constraints']);

            expect(count($resolved))->toBeLessThanOrEqual(
                3,
                "context '{$entry['pattern']}' resolves to ".count($resolved).' pages'
            );
        }
    }
});

it('shares contextual help with the frontend', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard/lists');

    $response->assertOk();
    $help = $response->viewData('page')['props']['help'] ?? null;

    expect($help)->toBeArray()
        ->and(array_column($help, 'slug'))->toContain('lists')
        ->and($help[0]['url'])->toStartWith('/help/');
});

it('resolves controller-injected context over a real request', function () {
    // The end-to-end path for the case the URL cannot express: the template
    // page pushes the model's type in, and an alert gets the alert page.
    $user = User::factory()->create();

    $alert = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'type' => 'alert',
        'fork_of_id' => null,
    ]);

    $response = $this->actingAs($user)->get("/templates/{$alert->id}");

    $response->assertOk();

    expect(array_column($response->viewData('page')['props']['help'], 'slug'))
        ->toContain('overlays-vs-alerts');
});

it('resolves query-string context over a real request', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/templates?filter=mine&type=block&sort=name');

    $response->assertOk();

    expect($response->viewData('page')['props']['help'][0]['slug'])->toBe('blocks');
});

it('shares an empty list on a route with no help', function () {
    $response = $this->get('/privacy');

    $response->assertOk();

    expect($response->viewData('page')['props']['help'])->toBe([]);
});
