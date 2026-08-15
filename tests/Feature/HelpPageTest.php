<?php

use App\Support\HelpPage;

/**
 * Help prose lives in resources/help/pages/*.md and is rendered server-side.
 * The point of the markdown conversion was that the old hand-written .vue pages
 * shipped an empty Inertia shell - a machine fetching /help/blocks got ~27KB of
 * <head> and zero content. These tests pin both halves: the HTML page carries
 * the prose, and the .md twin serves the source verbatim.
 */
it('finds every shipped help page', function () {
    expect(HelpPage::all())->not->toBeEmpty()
        ->and(HelpPage::all())->toContain('blocks', 'conditionals', 'bot/aliases', 'bot/commands');
});

it('rejects a traversal attempt in the slug', function () {
    expect(HelpPage::path('../../.env'))->toBeNull()
        ->and(HelpPage::path('..'))->toBeNull()
        ->and(HelpPage::exists('nope'))->toBeFalse();
});

it('renders frontmatter, html and a generated table of contents', function () {
    $page = HelpPage::render('blocks');

    expect($page['title'])->toContain('Blocks')
        ->and($page['description'])->not->toBeEmpty()
        ->and($page['html'])->toContain('<h2')
        ->and($page['toc'])->not->toBeEmpty()
        ->and($page['toc'][0])->toHaveKeys(['id', 'text']);
});

it('generates heading ids with the section number stripped', function () {
    // "1. The third template type" should anchor to #the-third-template-type,
    // so the anchor survives a section being renumbered.
    $page = HelpPage::render('blocks');
    $ids = array_column($page['toc'], 'id');

    expect($ids)->toContain('the-third-template-type')
        ->and($page['html'])->toContain('id="the-third-template-type"');
});

it('turns GitHub-style alerts into callouts', function () {
    $html = HelpPage::render('blocks')['html'];

    expect($html)->toContain('help-callout--warning')
        ->and($html)->toContain('help-callout--note')
        ->and($html)->not->toContain('[!WARNING]');
});

it('protects TeX from the markdown pass', function () {
    // Backslashes and underscores would be eaten by CommonMark, so math is
    // lifted out before rendering and reinserted afterwards.
    $html = HelpPage::render('math')['html'];

    expect($html)->toContain('help-math')
        ->and($html)->toContain('data-tex')
        ->and($html)->not->toContain('@@OLMATH');
});

it('serves each help page as an inertia page carrying the prose', function () {
    $response = $this->get('/help/conditionals');

    $response->assertOk();
    expect($response->getContent())->toContain('Nested Conditionals');
});

it('serves the markdown twin as text/markdown', function () {
    $response = $this->get('/help/blocks.md');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/markdown; charset=utf-8');
    expect($response->getContent())->toStartWith('---');
});

it('serves markdown for nested bot pages too', function () {
    $this->get('/help/bot/aliases.md')->assertOk();
    $this->get('/help/bot/commands.md')->assertOk();
});

it('serves the markdown byte-identically to the source file', function () {
    $slug = 'builder';

    expect($this->get("/help/{$slug}.md")->getContent())
        ->toBe(file_get_contents(HelpPage::path($slug)));
});

it('keeps every original help route name working', function () {
    // The .vue pages these replaced were referenced by name across the app;
    // renaming any of them would break links silently.
    foreach ([
        'help.conditionals', 'help.controls', 'help.formatting', 'help.lists',
        'help.expressions', 'help.math', 'help.blocks', 'help.builder',
        'help.manifesto', 'help.resources', 'help.for-creators', 'help.for-designers',
        'help.overlays-vs-alerts', 'help.lists-realtime', 'help.why-kofi',
        'help.why-overlabels', 'help.bot.aliases', 'help.bot.commands',
    ] as $name) {
        expect(route($name, absolute: false))->toBeString();
    }
});

it('serves the help index at /help and /help.md', function () {
    // An `index` slug maps to its parent path, so index.md is /help (not
    // /help/index) and bot/index.md is /help/bot. This is the entry point a
    // crawler lands on, so it has to carry links to everything else.
    $this->get('/help')->assertOk();

    $md = $this->get('/help.md');
    $md->assertOk();
    $md->assertHeader('Content-Type', 'text/markdown; charset=utf-8');

    expect($md->getContent())
        ->toContain('/help/conditionals')
        ->toContain('/help/controls')
        ->toContain('/help/lists');

    $this->get('/help/bot')->assertOk();
    $this->get('/help/bot.md')->assertOk();

    // The index slug must not also leak out as /help/index.
    expect(app('router')->getRoutes()->getByName('help.index'))->toBeNull();
});

it('makes every markdown page reachable from the index', function () {
    // llms.txt tells a machine that /help.md is the entry point for crawling
    // the docs, so an unlinked page is effectively undiscoverable. Reachability
    // is transitive: the bot pages hang off /help/bot.md rather than the root
    // index, which is a legitimate second hop.
    $indexes = array_filter(HelpPage::all(), fn ($s) => str_ends_with($s, 'index'));

    $linkText = '';
    foreach ($indexes as $slug) {
        $linkText .= file_get_contents(HelpPage::path($slug));
    }

    foreach (HelpPage::all() as $slug) {
        if (str_ends_with($slug, 'index')) {
            continue;
        }

        expect($linkText)->toContain("/help/{$slug}");
    }
});

it('still redirects the bot expressions url this page briefly lived at', function () {
    // The page was /help/bot/commands, moved to /help/bot/expressions in Jul
    // 2026, and moved back. The .md variant matters as much as the HTML one:
    // llms.txt named it, so crawlers hold that exact URL.
    $this->get('/help/bot/expressions')->assertRedirect('/help/bot/commands');
    $this->get('/help/bot/expressions.md')->assertRedirect('/help/bot/commands.md');
});

it('leaves the interactive preset page as a vue component', function () {
    // It renders live from controlPresets.ts with a search box, so it is
    // deliberately not markdown - freezing it would drift from its source.
    // Asserting on the absence of the route rather than on a 404, because this
    // app answers every unknown URL with a rendered not-found page at status 200.
    $this->get('/help/integration-presets')->assertOk();

    expect(HelpPage::exists('integration-presets'))->toBeFalse()
        ->and(app('router')->getRoutes()->getByName('help.integration-presets.md'))->toBeNull();
});
