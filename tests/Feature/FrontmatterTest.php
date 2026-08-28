<?php

use App\Support\Frontmatter;
use App\Support\HelpPage;

/**
 * Pins App\Support\Frontmatter, lifted out of HelpPage when update posts
 * needed the same parser.
 *
 * Nothing here was covered before. Every help test went through real files on
 * disk, and every one of those files is well-formed, so the whole suite stayed
 * green regardless of what the split did with a BOM, a CRLF body, an empty
 * block, or a value containing a colon. These are the behaviours that were
 * load-bearing and invisible.
 */

// ──────────────────────────────────────────────────────────────────────────────
// parse() - flat key: value, and the rules that make it useful
// ──────────────────────────────────────────────────────────────────────────────

it('keeps everything after the first colon, which is what makes urls work', function () {
    // The `2` in explode(':', $line, 2). Without it a canonical: or url: line
    // parses to the string "https" and every link in the app breaks.
    expect(Frontmatter::parse('url: https://example.com/x?a=1&b=2'))
        ->toBe(['url' => 'https://example.com/x?a=1&b=2']);
});

it('skips lines with no colon rather than erroring on them', function () {
    expect(Frontmatter::parse("label\nroute: wiring.index"))
        ->toBe(['route' => 'wiring.index']);
});

it('trims surrounding quotes and whitespace off a value', function () {
    expect(Frontmatter::parse('label: "Check your wiring"'))
        ->toBe(['label' => 'Check your wiring']);
});

it('lets the last duplicate key win and keeps a valueless key as empty string', function () {
    expect(Frontmatter::parse("k: one\nk: two"))->toBe(['k' => 'two'])
        ->and(Frontmatter::parse('e:'))->toBe(['e' => '']);
});

// ──────────────────────────────────────────────────────────────────────────────
// split() - the delimiter arithmetic
// ──────────────────────────────────────────────────────────────────────────────

it('splits a well formed block off the front of the body', function () {
    [$meta, $body] = Frontmatter::split("---\ntitle: A\n---\n\nBody copy.\n");

    expect($meta)->toBe(['title' => 'A'])
        ->and($body)->toBe("Body copy.\n");
});

it('treats an empty block as frontmatter, not as body', function () {
    // Depends on strpos() starting at offset 3 so the very first newline can
    // match the closing delimiter. Searching from 4 would swallow the document.
    expect(Frontmatter::split("---\n---\nBody\n"))->toBe([[], "Body\n"]);
});

it('requires the opening delimiter to be the very first line', function () {
    $raw = "\n---\ntitle: x\n---\nBody\n";

    expect(Frontmatter::split($raw))->toBe([[], $raw]);
});

it('hands back the whole document when there is no closing delimiter', function () {
    $raw = "---\nJust a rule and prose, no second one.\n";

    expect(Frontmatter::split($raw))->toBe([[], $raw]);
});

it('normalises CRLF on every path, including one with no frontmatter', function () {
    expect(Frontmatter::split("Plain body\r\nline two\r\n"))
        ->toBe([[], "Plain body\nline two\n"]);
});

it('strips a leading BOM', function () {
    [$meta] = Frontmatter::split("\xEF\xBB\xBF---\ntitle: A\n---\nBody\n");

    expect($meta)->toBe(['title' => 'A']);
});

it('collapses every blank line between the block and the body', function () {
    [, $body] = Frontmatter::split("---\na: b\n---\n\n\n\n# Heading\n");

    expect($body)->toBe("# Heading\n");
});

// ──────────────────────────────────────────────────────────────────────────────
// split() with required keys - the guard that protects free-form markdown
// ──────────────────────────────────────────────────────────────────────────────

it('refuses to treat a leading horizontal rule as frontmatter', function () {
    // The reason the guard exists. An update body is markdown typed into a
    // textarea, where `---` is an ordinary rule. Unguarded, this silently ate
    // the intro paragraph and turned "Note:" into a metadata key.
    $raw = "---\n\nNote: this paragraph matters.\n\n---\n\nRest of the post.\n";

    expect(Frontmatter::split($raw, ['route', 'url', 'label']))->toBe([[], $raw]);

    // Unguarded is exactly the damage the guard prevents.
    expect(Frontmatter::split($raw))
        ->toBe([['Note' => 'this paragraph matters.'], "Rest of the post.\n"]);
});

it('accepts a block that carries at least one required key', function () {
    [$meta, $body] = Frontmatter::split(
        "---\nroute: dashboard.index\nlabel: Go\n---\nReal body.\n",
        ['route', 'url', 'label'],
    );

    expect($meta)->toBe(['route' => 'dashboard.index', 'label' => 'Go'])
        ->and($body)->toBe("Real body.\n");
});

// ──────────────────────────────────────────────────────────────────────────────
// The extraction did not move HelpPage
// ──────────────────────────────────────────────────────────────────────────────

it('still renders help pages through the shared parser', function () {
    $page = HelpPage::render('blocks');

    expect($page['title'])->toContain('Blocks')
        ->and($page['description'])->not->toBeEmpty()
        ->and($page['canonical'])->toStartWith('https://')
        ->and($page['html'])->toContain('<h2');
});
