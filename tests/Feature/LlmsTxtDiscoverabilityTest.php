<?php

use App\Services\HelpReferenceService;
use App\Support\HelpPage;

/**
 * llms.txt is only useful if something can find it. A `<link rel="llms-txt">`
 * in the document head is a declaration, not a link a crawler follows, and
 * llms.txt is a convention rather than a ratified standard - so nothing indexes
 * the file on its own. The chain that makes it discoverable is:
 *
 *   sitemap.xml -> /help/reference (server-rendered HTML) -> body copy +
 *   /help/llms-txt -> /llms.txt -> back to the explainer page.
 *
 * Every link in that chain is asserted below. Breaking one silently undoes the
 * whole thing, which is exactly what happened before these pages existed.
 *
 * The three explainers (llms.txt, markdown endpoints, the JSON index) are
 * guides under /help. They spent a year filed as reference entries in a
 * `for-machines` category, which made them the only prose in the reference and
 * left the page explaining the `.md` twin as the one help URL without one.
 */
it('ships llms.txt and its explainer page', function () {
    expect(file_exists(public_path('llms.txt')))->toBeTrue();

    expect(HelpPage::exists('llms-txt'))->toBeTrue();

    $page = HelpPage::render('llms-txt');

    expect($page['heading'])->toBe('llms.txt')
        ->and($page['html'])->toContain('https://overlabels.com/llms.txt');
});

it('serves the explainer as crawlable html at its own url', function () {
    $response = $this->get('/help/llms-txt');

    $response->assertOk();

    expect($response->getContent())
        ->toContain('https://overlabels.com/llms.txt')
        ->toContain('llms.txt - Overlabels Help');
});

it('serves each explainer as markdown too', function () {
    // The page that explains "append .md to any help URL" had no .md of its
    // own for as long as it lived in the reference. That is the whole reason
    // these three are guides.
    foreach (['llms-txt', 'markdown-endpoints', 'help-reference-index-json'] as $slug) {
        $this->get("/help/{$slug}.md")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=utf-8');
    }
});

it('redirects the old reference urls permanently', function () {
    // These were indexed for a year. A 404 here throws that away.
    foreach (['llms-txt', 'markdown-endpoints', 'help-reference-index-json'] as $slug) {
        $this->get("/help/reference/for-machines/{$slug}")
            ->assertStatus(301)
            ->assertRedirect("/help/{$slug}");
    }
});

it('no longer carries a for-machines category in the reference', function () {
    expect(HelpReferenceService::CATEGORY_LABELS)->not->toHaveKey('for-machines')
        ->and(HelpReferenceService::CATEGORY_ORDER)->not->toContain('for-machines')
        ->and(app(HelpReferenceService::class)->get('for-machines', 'llms-txt'))->toBeNull();
});

it('puts body copy about llms.txt on the reference index', function () {
    // The index is the highest-priority page in this section. The link has to be
    // real prose in the article column, not a badge or an icon.
    $html = $this->get('/help/reference')->getContent();

    expect($html)
        ->toContain('href="/llms.txt"')
        ->toContain('href="/help/llms-txt"')
        ->toContain('application/ld+json')
        ->toContain('"contentUrl": "https://overlabels.com/llms.txt"');
});

it('anchors llms.txt from the homepage', function () {
    // The homepage is priority 1.0, plain Blade, and the page a crawler reaches
    // first. If the only mention here is <link rel="llms-txt">, there is nothing
    // to follow - that is the whole failure this work exists to fix.
    $html = $this->get('/')->getContent();

    expect($html)
        ->toContain('href="/llms.txt"')
        ->toContain('href="/help/llms-txt"')
        ->toContain('Reading this as a machine?');
});

it('links llms.txt from every reference page, not just the index', function () {
    // channel_id rather than a sampled-at-random entry: it is a core tag that
    // cannot stop existing, so this test fails for the reason it is about
    // rather than because the page it happened to name got retired.
    foreach (['/help/reference', '/help/reference/template-tags/channel_id'] as $url) {
        expect($this->get($url)->getContent())
            ->toContain('href="/llms.txt"')
            ->toContain('rel="llms-txt"');
    }
});

it('links back from llms.txt to the page that explains it', function () {
    // Reciprocal: a crawler that reaches the file should be able to walk back to
    // an HTML page about it.
    expect(file_get_contents(public_path('llms.txt')))
        ->toContain('https://overlabels.com/help/llms-txt')
        ->not->toContain('/help/reference/for-machines/');
});

it('lists llms.txt and the explainer pages in the sitemap', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)
        ->toContain('<loc>https://overlabels.com/llms.txt</loc>')
        ->toContain('<loc>https://overlabels.com/help/llms-txt</loc>')
        ->toContain('<loc>https://overlabels.com/help/markdown-endpoints</loc>')
        ->toContain('<loc>https://overlabels.com/help/help-reference-index-json</loc>')
        ->not->toContain('/help/reference/for-machines/');
});

it('keeps the prebuilt json index in step with the markdown sources', function () {
    // public/help-reference-index.json is generated by `php artisan help:build-index`
    // on post-autoload-dump, and is gitignored. Composer regenerates it in CI, so
    // this guards the local loop: adding a .md file without rerunning the command
    // leaves the fuzzy search - and any machine consuming the JSON - blind to it.
    $path = public_path('help-reference-index.json');

    expect(file_exists($path))->toBeTrue(
        'Run `php artisan help:build-index` - the reference JSON has not been generated.',
    );

    $json = json_decode((string) file_get_contents($path), true);

    expect(count($json))->toBe(count(app(HelpReferenceService::class)->all()));
});
