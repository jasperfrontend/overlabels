<?php

use App\Services\HelpReferenceService;
use App\Support\HelpCorpus;
use App\Support\HelpPage;

/**
 * Help used to be built two different ways: prose pages were an Inertia app
 * with one markdown pipeline, reference entries were Blade with another, and
 * neither could see the other. These tests pin the parts of the merge that a
 * later change could quietly undo.
 */
it('lists every help document in the sitemap', function () {
    // The sitemap was a hand-written array and had rotted by fourteen pages
    // before it was replaced. Deriving it from the corpus is only worth
    // anything if this test fails the moment the two diverge.
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    foreach (HelpCorpus::all() as $doc) {
        expect($xml)->toContain("<loc>https://overlabels.com{$doc['url']}</loc>");
    }
})->group('help');

it('serves reference entries as crawlable html carrying their whole body', function () {
    // Same assertion the prose pages get: the view echoes the rendered body
    // raw, so all of it must survive into the response.
    $service = app(HelpReferenceService::class);
    $entry = $service->get('eventsub-events', 'new-follower');

    $response = $this->get('/help/reference/eventsub-events/new-follower');

    $response->assertOk();
    expect($response->getContent())->toContain($service->render($entry['body']));
})->group('help');

it('gives both corpora the same layout and the same stylesheet', function () {
    // One template for anything help-related was the whole point. Both halves
    // must come through layouts/help.blade.php - same script entry, same
    // search box, same prose class - or they will drift apart again.
    $guide = $this->get('/help/conditionals')->getContent();
    $reference = $this->get('/help/reference/eventsub-events/new-follower')->getContent();

    foreach ([$guide, $reference] as $html) {
        expect($html)
            ->toContain('id="help-search"')
            ->toContain('id="help-sidebar"')
            ->toContain('help-prose');
    }
})->group('help');

it('offers click-to-copy tags in guides, not just the reference', function () {
    // `[[[tag]]]` widgets were a reference-only feature. Writing an overlay is
    // mostly copying tags, and the guides are full of them.
    expect(HelpPage::render('conditionals')['html'])->toContain('class="ov-tag"');
})->group('help');

it('offers callouts in the reference, not just guides', function () {
    // The mirror of the test above: `> [!NOTE]` was a guides-only feature.
    $html = app(HelpReferenceService::class)->render(
        "> [!NOTE]\n> Both halves render the same markdown now."
    );

    expect($html)->toContain('help-callout--note')
        ->and($html)->not->toContain('[!NOTE]');
})->group('help');

it('keeps soft breaks per kind', function () {
    // Reference entries are written one statement per line and need <br />.
    // Guides are prose wrapped at ~100 columns and would break mid-sentence.
    // A single global setting is wrong for one corpus or the other.
    $reference = app(HelpReferenceService::class)->get('eventsub-events', 'new-follower');

    expect(app(HelpReferenceService::class)->render($reference['body']))->toContain('<br />')
        ->and(HelpPage::render('conditionals')['html'])->not->toContain('<br />');
})->group('help');

it('resolves wikilinks across both corpora without shadowing a page', function () {
    $map = HelpCorpus::linkMap();

    expect($map)->toHaveKey('new-follower')
        ->and($map['new-follower'])->toBe('/help/reference/eventsub-events/new-follower')
        ->and($map)->toHaveKey('lists')
        ->and($map['lists'])->toBe('/help/lists');

    // Reference slugs are registered first and win collisions, because all 147
    // reference files were written against that namespace. `chat` is the one
    // deliberate casualty and is declared as such; anything else colliding is
    // an accident that silently repoints existing wikilinks at a different
    // document, so it has to fail here.
    $referenceSlugs = array_column(
        array_filter(HelpCorpus::all(), fn ($d) => $d['kind'] === HelpCorpus::KIND_REFERENCE),
        'slug'
    );

    // `toContain` is variadic, so a second argument would be read as another
    // needle rather than a failure message. These have to be boolean asserts.
    foreach (HelpCorpus::docs() as $doc) {
        if (in_array($doc['slug'], HelpCorpus::SHADOWED_PAGE_SLUGS, true)) {
            continue;
        }

        expect(in_array($doc['slug'], $referenceSlugs, true))->toBeFalse(
            "Page slug '{$doc['slug']}' collides with a reference entry and is unreachable by wikilink. "
            .'Rename one, or add it to HelpCorpus::SHADOWED_PAGE_SLUGS if the shadowing is intended.'
        );
    }

    // The declared shadowing must be real, so the list cannot rot into a set of
    // exemptions for collisions that no longer exist.
    foreach (HelpCorpus::SHADOWED_PAGE_SLUGS as $slug) {
        expect(in_array($slug, $referenceSlugs, true))->toBeTrue(
            "'{$slug}' is declared as shadowed but no reference entry claims it."
        );
    }
})->group('help');

it('indexes all three kinds for the one search box', function () {
    // The search index is what makes a single search box honest. If it only
    // carried the reference again, the box would be promising more than it
    // answers.
    $kinds = array_unique(array_column(HelpCorpus::all(), 'kind'));

    expect($kinds)->toContain(HelpCorpus::KIND_GUIDE)
        ->and($kinds)->toContain(HelpCorpus::KIND_REFERENCE)
        ->and(HelpCorpus::all())->toHaveCount(
            count(HelpPage::all()) + count(app(HelpReferenceService::class)->all())
        );
})->group('help');

it('derives a tutorial from its directory rather than new machinery', function () {
    // Tutorials are ordinary help pages under pages/tutorials/, so routing, the
    // .md twin, HelpContext and the sitemap pick them up with no new code. If
    // this ever needs its own table, controller or route, something went wrong.
    expect(HelpCorpus::kindOf('tutorials/show-chat-on-screen'))->toBe(HelpCorpus::KIND_TUTORIAL)
        ->and(HelpCorpus::kindOf('conditionals'))->toBe(HelpCorpus::KIND_GUIDE)
        ->and(HelpPage::url('tutorials/show-chat-on-screen'))->toBe('/help/tutorials/show-chat-on-screen');
})->group('help');
