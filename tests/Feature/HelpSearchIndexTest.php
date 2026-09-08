<?php

use App\Services\HelpReferenceService;
use App\Support\HelpCorpus;
use App\Support\HelpMarkdown;
use App\Support\HelpPage;

/**
 * Search indexes sections, not pages, and a result links to the section's
 * anchor. These pin the two halves of that: the splitter produces one entry
 * per heading with the id the renderer gives it, and the corpus carries the
 * result to the published index.
 */
it('gives every search section the id its heading renders with', function () {
    // A search result opening `/help/bot/commands#controls` is only an answer
    // if the page has an element with that id. The splitter derives ids from
    // markdown and the renderer from HTML, so this walks the whole corpus and
    // fails on the first heading the two disagree about.
    $service = app(HelpReferenceService::class);

    foreach (HelpCorpus::all() as $doc) {
        if ($doc['slug'] === 'index') {
            continue;
        }

        $html = $doc['kind'] === HelpCorpus::KIND_REFERENCE
            ? $service->render($doc['body'])
            : HelpPage::render($doc['slug'])['html'];

        $ids = array_values(array_filter(array_column($doc['sections'], 'id')));

        preg_match_all('/<h[23] id="([^"]+)"/', $html, $m);

        expect($ids)->toBe($m[1], "sections of '{$doc['slug']}' do not match its rendered headings");
    }
})->group('help');

it('splits a body into an intro plus one section per h2 and h3, and leaves fenced code alone', function () {
    $sections = HelpMarkdown::sections(<<<'MD'
    # Title line is not a section

    Intro text.

    ## Controls

    Requires `!enablecontrols` on your channel.

    ### 1. Nested heading

    ```
    ## not a heading, this is code
    ```

    ## Controls

    Same heading twice.
    MD);

    expect(array_column($sections, 'id'))->toBe([null, 'controls', 'nested-heading', 'controls-2'])
        ->and($sections[0]['text'])->toBe('Intro text.')
        ->and($sections[0]['heading'])->toBe('')
        ->and($sections[1]['text'])->toContain('!enablecontrols')
        ->and($sections[2]['heading'])->toBe('1. Nested heading')
        ->and($sections[2]['text'])->toContain('## not a heading');
})->group('help');

it('omits an empty intro but keeps an empty section', function () {
    $sections = HelpMarkdown::sections("## Only\n\n## Headings");

    expect(array_column($sections, 'id'))->toBe(['only', 'headings']);
})->group('help');

it('keeps frontmatter out of the intro section', function () {
    // The intro section of every page ranked for every page's own frontmatter
    // keys when the raw file was split as-is. `keywords:` and `section:` are
    // not words a reader searches for, and `title:` matched everything.
    $editor = collect(HelpCorpus::all())->firstWhere('slug', 'editor');

    expect($editor['sections'][0]['id'])->toBeNull()
        ->and($editor['sections'][0]['text'])->not->toContain('keywords:')
        ->and($editor['sections'][0]['text'])->not->toStartWith('---');
})->group('help');

it('indexes the section that answers the on-stream question', function () {
    // The night this was built: "controls", "chat", "controls chat",
    // "enablecontrols", "twitch controls" and "bot controls" all failed to find
    // this section from the help search, live on stream. The client-side
    // ranking has its own test; this pins that the data it needs is there.
    $commands = collect(HelpCorpus::all())->firstWhere('slug', 'bot/commands');
    $controls = collect($commands['sections'])->firstWhere('id', 'controls');

    expect($controls)->not->toBeNull()
        ->and($controls['heading'])->toBe('Controls')
        ->and($controls['text'])->toContain('!enablecontrols');
})->group('help');

it('gives the root index no sections', function () {
    // The landing is a table of contents. Its sections are lists of links to
    // the pages that answer things, and a result pointing at "Bot & chat" on
    // the landing page answers nothing.
    $index = collect(HelpCorpus::all())->firstWhere('slug', 'index');

    expect($index['sections'])->toBe([]);
})->group('help');

it('publishes sections to the search index in place of the body', function () {
    // The body is the same bytes as the sections joined back together, so
    // shipping both doubled the file. The frozen reference contract is
    // untouched and still carries its body - HelpUnificationTest pins that.
    $this->artisan('help:build-index')->assertSuccessful();

    $unified = json_decode((string) file_get_contents(public_path('help-index.json')), true);
    $commands = collect($unified)->firstWhere('slug', 'bot/commands');

    expect($commands)->toHaveKey('sections')
        ->and($commands)->not->toHaveKey('body')
        ->and(array_keys($commands['sections'][1]))->toBe(['id', 'heading', 'text']);
})->group('help');
