<?php

use App\Support\HelpCorpus;
use App\Support\HelpPage;

/**
 * Guides are grouped into HelpCorpus::SECTIONS by a `section:` frontmatter
 * line. The landing page and the sidebar tree are built from that, and the
 * hand-written index.md (served as /help.md, the crawl entry point) mirrors
 * it. These tests are what keep the three in step: a guide with no section,
 * a section nobody uses, or an index.md that files a page under a different
 * heading than its frontmatter all fail here by name.
 */
it('files every guide under a declared section', function () {
    foreach (HelpCorpus::ofKind(HelpCorpus::KIND_GUIDE) as $doc) {
        if ($doc['slug'] === 'index') {
            continue;
        }

        expect($doc['section'])->not->toBeNull("{$doc['slug']} declares no section: frontmatter")
            ->and(array_key_exists($doc['section'], HelpCorpus::SECTIONS))->toBeTrue(
                "{$doc['slug']} declares section '{$doc['section']}', which is not in HelpCorpus::SECTIONS"
            );
    }
})->group('help');

it('leaves tutorials and deep dives without a section', function () {
    // Their kind already places them. A section line on one would put it in
    // two places on the landing page.
    foreach ([HelpCorpus::KIND_TUTORIAL, HelpCorpus::KIND_DEEP_DIVE] as $kind) {
        foreach (HelpCorpus::ofKind($kind) as $doc) {
            expect($doc['section'])->toBeNull("{$doc['slug']} is a {$kind} and must not declare a section");
        }
    }
})->group('help');

it('uses every section at least once', function () {
    // The constant is the taxonomy, and an entry nothing points at is a
    // column with a heading and no links.
    foreach (HelpCorpus::sections() as $section) {
        expect($section['items'])->not->toBeEmpty("Section '{$section['label']}' has no pages")
            ->and($section['description'])->not->toBeEmpty();
    }

    expect(array_column(HelpCorpus::sections(), 'label'))->toBe(array_keys(HelpCorpus::SECTIONS));
})->group('help');

it('lists every guide in its section, in index.md order', function () {
    // index.md is where an author already decides what comes first, so that
    // order is read back rather than kept in step with a second key. Getting
    // started leads with the pitch, Bot & chat with the bot overview, and the
    // tutorials open with the chat one - none of which is alphabetical.
    $sections = collect(HelpCorpus::sections())->keyBy('label');

    expect(array_column($sections['Getting started']['items'], 'slug')[0])->toBe('why-overlabels')
        ->and(array_column($sections['Bot & chat']['items'], 'slug')[0])->toBe('bot/index')
        ->and(array_column($sections['For machines']['items'], 'slug'))->toContain('llms-txt')
        ->and(HelpCorpus::ordered(HelpCorpus::KIND_TUTORIAL)[0]['slug'])->toBe('tutorials/show-chat-on-screen');

    // The two Inertia pages that cannot declare a section of their own.
    expect(array_column($sections['Live data']['items'], 'url'))->toContain('/help/integration-presets')
        ->and(array_column($sections['Bot & chat']['items'], 'url'))->toContain('/help/gamejam');

    $listed = [];
    foreach (HelpCorpus::sections() as $section) {
        foreach ($section['items'] as $item) {
            if ($item['slug'] !== null) {
                $listed[] = $item['slug'];
            }
        }
    }

    foreach (HelpCorpus::ofKind(HelpCorpus::KIND_GUIDE) as $doc) {
        if ($doc['slug'] !== 'index') {
            expect($listed)->toContain($doc['slug']);
        }
    }
})->group('help');

it('renders the landing page from the corpus with every document linked', function () {
    $html = $this->get('/help')->assertOk()->getContent();

    foreach (HelpCorpus::docs() as $doc) {
        if ($doc['slug'] === 'index') {
            continue;
        }
        expect($html)->toContain('href="'.$doc['url'].'"');
    }

    foreach (HelpCorpus::sections() as $section) {
        expect($html)->toContain('id="'.$section['anchor'].'"')
            ->and($html)->toContain(e($section['label']));
    }

    expect($html)
        ->toContain('id="tutorials"')
        ->toContain('id="deep-dives"')
        ->toContain('id="guides"')
        ->toContain('href="/help/reference"')
        ->toContain('id="help-search"');
})->group('help');

it('keeps index.md filed the same way as the frontmatter', function () {
    // /help.md is the hand-written twin of the derived landing page. Each
    // guide's link there must sit under a heading that reads exactly like its
    // section, so a machine and a person are told the same thing.
    $lines = file(HelpPage::path('index'), FILE_IGNORE_NEW_LINES) ?: [];

    $headingFor = [];
    $current = null;
    foreach ($lines as $line) {
        if (preg_match('/^#{2,3}\s+(.+?)\s*$/', $line, $m)) {
            $current = $m[1];

            continue;
        }
        // Links above the first heading are the intro (the machine tip
        // mentions /help/llms-txt); only a link under a heading is a filing.
        if ($current !== null && preg_match_all('#\]\((/help[^)\s]*)\)#', $line, $m)) {
            foreach ($m[1] as $url) {
                $headingFor[$url] ??= $current;
            }
        }
    }

    foreach (HelpCorpus::ofKind(HelpCorpus::KIND_GUIDE) as $doc) {
        if ($doc['slug'] === 'index') {
            continue;
        }

        expect($headingFor[$doc['url']] ?? null)->toBe(
            $doc['section'],
            "index.md files {$doc['url']} under '".($headingFor[$doc['url']] ?? 'nothing')."' but its frontmatter says '{$doc['section']}'"
        );
    }

    foreach (HelpCorpus::SECTION_EXTRAS as $label => $extras) {
        foreach ($extras as $extra) {
            expect($headingFor[$extra['url']] ?? null)->toBe($label);
        }
    }
})->group('help');

it('walks previous, next and related within the section', function () {
    $html = $this->get('/help/integration-test-mode')->assertOk()->getContent();

    $section = HelpCorpus::sectionOf('integration-test-mode');
    $slugs = array_column($section['items'], 'slug');
    $at = array_search('integration-test-mode', $slugs, true);

    expect($section['label'])->toBe('Integrations & testing')
        ->and($html)->toContain('href="'.$section['items'][$at - 1]['url'].'"')
        ->toContain('href="'.$section['items'][$at + 1]['url'].'"')
        ->toContain('aria-label="Nearby pages"')
        ->toContain('Related docs')
        ->toContain('min read')
        ->toContain('data-help-copy-page="/help/integration-test-mode.md"');

    // A tutorial's neighbours are the other tutorials.
    $first = HelpCorpus::ordered(HelpCorpus::KIND_TUTORIAL)[0];
    $second = HelpCorpus::ordered(HelpCorpus::KIND_TUTORIAL)[1];

    expect($this->get($first['url'])->getContent())
        ->toContain('href="'.$second['url'].'"')
        ->not->toContain('← Previous');
})->group('help');

it('opens the sidebar branch holding the current page and collapses the rest', function () {
    $html = $this->get('/help/bot/aliases')->assertOk()->getContent();

    expect($html)
        ->toContain('aria-current="page"')
        ->toMatch('/<details class="help-tree-group"\s+open\s*>\s*<summary[^>]*>\s*<span[^>]*><\/span>\s*<span[^>]*>Guides<\/span>/')
        ->toMatch('/<details class="help-tree-section"\s+open\s*>\s*<summary[^>]*>\s*<span[^>]*><\/span>\s*<span[^>]*>Bot &amp; chat<\/span>/')
        ->toMatch('/<details class="help-tree-group"\s*>\s*<summary[^>]*>\s*<span[^>]*><\/span>\s*<span[^>]*>Tutorials<\/span>/');
})->group('help');

it('counts reading time on the markdown source', function () {
    expect(HelpPage::readingMinutes(''))->toBe(1)
        ->and(HelpPage::readingMinutes(str_repeat('word ', 199)))->toBe(1)
        ->and(HelpPage::readingMinutes(str_repeat('word ', 201)))->toBe(2)
        ->and(HelpPage::render('tokens')['readingMinutes'])->toBeGreaterThanOrEqual(1);
})->group('help');
