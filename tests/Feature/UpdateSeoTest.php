<?php

use App\Models\Update;
use App\Services\OgImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

/**
 * Named apart from PublicUpdatesTest's makeUpdate(): Pest loads every test file
 * into the same process, so two file-scope helpers sharing a name is a fatal
 * redeclare.
 */
function makeSeoUpdate(array $attributes = []): Update
{
    return Update::create(array_merge([
        'title' => 'Your bot can stop counting wrong',
        'body' => 'Here is what shipped.',
        'excerpt' => 'Chat command replies and alert messages understand if / else now.',
        'published_at' => now()->subDay(),
    ], $attributes));
}

/**
 * The OG renderer shells out to resvg, which is baked into the app image but
 * is not installed on CI runners. Everything about the metadata is testable
 * without it; only the "is there a real PNG" test needs it, and that one skips
 * rather than pretending the card is broken.
 */
function resvgAvailable(): bool
{
    try {
        $process = new Process([(string) (env('RESVG_BIN') ?: 'resvg'), '--version']);
        $process->setTimeout(10);
        $process->run();

        return $process->isSuccessful();
    } catch (Throwable) {
        return false;
    }
}

it('gives a post its own title, description and url rather than the site default', function () {
    $update = makeSeoUpdate();

    $html = $this->get("/updates/{$update->slug}")->assertOk()->getContent();

    expect($html)
        ->toContain('<meta property="og:title" content="Your bot can stop counting wrong" />')
        ->toContain('<meta property="og:description" content="Chat command replies and alert messages understand if / else now." />')
        ->toContain('<meta property="og:url" content="'.url("/updates/{$update->slug}").'" />')
        ->toContain('<meta name="twitter:title" content="Your bot can stop counting wrong" />')
        // The whole point: the site-wide card must be replaced, not joined.
        ->not->toContain('Overlabels - A live overlay DSL for Twitch streamers')
        ->not->toContain('<meta property="og:url" content="https://overlabels.com/" />');
});

it('server-renders a title element so a crawler that runs no javascript still gets one', function () {
    $update = makeSeoUpdate();

    $this->get("/updates/{$update->slug}")
        ->assertOk()
        ->assertSee('<title>Your bot can stop counting wrong - Overlabels</title>', false);
});

it('points a post at itself as canonical', function () {
    $update = makeSeoUpdate();

    $this->get("/updates/{$update->slug}")
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.url("/updates/{$update->slug}").'" />', false);
});

it('marks a post as an article carrying its publish date and topic tags', function () {
    $update = makeSeoUpdate([
        'tags' => ['whatsnew', 'bot', 'alerts'],
        'published_at' => now()->subDays(3),
    ]);

    $html = $this->get("/updates/{$update->slug}")->assertOk()->getContent();

    expect($html)
        ->toContain('<meta property="og:type" content="article" />')
        ->toContain('<meta property="article:published_time" content="'.$update->published_at->toIso8601String().'" />')
        ->toContain('<meta property="article:tag" content="bot" />')
        ->toContain('<meta property="article:tag" content="alerts" />')
        // whatsnew says where a post is shown, not what it is about.
        ->not->toContain('<meta property="article:tag" content="whatsnew" />');
});

it('declares the card dimensions and serves an absolute image url', function () {
    $update = makeSeoUpdate();

    $html = $this->get("/updates/{$update->slug}")->assertOk()->getContent();

    preg_match('#<meta property="og:image" content="([^"]+)"#', $html, $matches);

    expect($matches[1] ?? '')->toStartWith(url('/'))
        ->and($html)
        // Both the rendered card and the fallback are 1200x630 PNGs, so these
        // hold whether or not resvg was available to this request.
        ->toContain('<meta property="og:image:width" content="1200" />')
        ->toContain('<meta property="og:image:height" content="630" />')
        ->toContain('<meta property="og:image:type" content="image/png" />');
});

it('renders a distinct card per post', function () {
    if (! resvgAvailable()) {
        $this->markTestSkipped('resvg is not installed on this machine.');
    }

    $og = app(OgImageService::class);

    $first = makeSeoUpdate(['title' => 'First post about bots']);
    $second = makeSeoUpdate(['title' => 'Second post about alerts']);

    $firstPath = $og->urlForUpdate($first, url("/updates/{$first->slug}"));
    $secondPath = $og->urlForUpdate($second, url("/updates/{$second->slug}"));

    expect($firstPath)->not->toBe('/ogimage.png')
        ->and($secondPath)->not->toBe($firstPath)
        ->and(public_path(ltrim($firstPath, '/')))->toBeFile();
});

it('publishes BlogPosting structured data that survives escaping', function () {
    // Quotes and an ampersand in the title are what would break a naively
    // encoded <script> block, so the round-trip is the assertion.
    $update = makeSeoUpdate([
        'title' => 'Your bot can stop saying "1 times" & other wins',
        'tags' => ['whatsnew', 'bot'],
    ]);

    $html = $this->get("/updates/{$update->slug}")->assertOk()->getContent();

    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    expect($matches)->not->toBeEmpty();

    $data = json_decode($matches[1], true);

    expect($data)->not->toBeNull()
        ->and($data['@type'])->toBe('BlogPosting')
        ->and($data['headline'])->toBe('Your bot can stop saying "1 times" & other wins')
        ->and($data['url'])->toBe(url("/updates/{$update->slug}"))
        ->and($data['keywords'])->toBe('bot')
        ->and($data['datePublished'])->toBe($update->published_at->toIso8601String());
});

it('gives the updates index its own metadata', function () {
    makeSeoUpdate();

    $html = $this->get('/updates')->assertOk()->getContent();

    expect($html)
        ->toContain('<meta property="og:url" content="'.url('/updates').'" />')
        ->toContain('<link rel="canonical" href="'.url('/updates').'" />')
        ->toContain('<title>Updates - Overlabels</title>')
        // An unfiltered listing is the page we want indexed.
        ->not->toContain('name="robots"');
});

it('keeps filtered and paginated listings out of the index while still following them', function () {
    makeSeoUpdate();

    $html = $this->get('/updates?search=bot')->assertOk()->getContent();

    expect($html)
        ->toContain('<meta name="robots" content="noindex, follow" />')
        // Still canonicalised to the clean listing rather than competing with it.
        ->toContain('<link rel="canonical" href="'.url('/updates').'" />');
});

it('lists the updates index and every published post in the sitemap', function () {
    $live = makeSeoUpdate(['title' => 'Shipped today']);
    $scheduled = makeSeoUpdate(['title' => 'Not out yet', 'published_at' => now()->addWeek()]);

    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)
        ->toContain('<loc>https://overlabels.com/updates</loc>')
        ->toContain('<loc>https://overlabels.com/updates/'.$live->slug.'</loc>')
        // Submitting a URL that answers 404 to the crawler is worse than
        // not listing it at all.
        ->not->toContain($scheduled->slug);
});

it('gives each post a lastmod in the order the sitemap schema requires', function () {
    $update = makeSeoUpdate();

    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)->toMatch(
        '#<loc>https://overlabels\.com/updates/'.preg_quote($update->slug, '#').'</loc>\s*'
        .'<lastmod>[^<]+</lastmod>\s*<changefreq>#'
    );
});

it('keeps the whatsnew plumbing tag out of a post topic tags', function () {
    $update = new Update;
    $update->forceFill(['tags' => ['whatsnew', 'bot', 'alerts']]);

    expect($update->topicTags())->toBe(['bot', 'alerts']);
});

it('prefers the hand-written excerpt as the description', function () {
    $update = new Update;
    $update->forceFill(['excerpt' => 'The short version.', 'body' => 'The long version.']);

    expect($update->plainExcerpt())->toBe('The short version.');
});

it('falls back to the body and reduces it to prose', function () {
    $update = new Update;
    $update->forceFill([
        'excerpt' => null,
        'body' => "## A heading\n\n> a quoted line\n\nSome **bold** text with [a link](https://x.test) "
            ."and `[[[counter:wins]]]`.\n\n```\ncode fence\n```\n\n- a bullet",
    ]);

    $text = $update->plainExcerpt();

    expect($text)
        ->toContain('a quoted line')
        ->toContain('Some bold text')
        ->toContain('a link')
        // Stripped to the bare tag, with no orphan bracket left behind.
        ->toContain('counter:wins')
        ->not->toContain('A heading')
        ->not->toContain('code fence')
        ->not->toContain('[')
        ->not->toContain('*')
        ->not->toContain('`')
        ->not->toContain('https://x.test');
});

it('does not eat prose that merely looks like an html tag', function () {
    // strip_tags() would take everything from the `<` in "i <3 you" to the
    // next `>`, turning this into "i is bold". Same trap as latest_chat_message.
    $update = new Update;
    $update->forceFill([
        'excerpt' => null,
        'body' => 'i <3 you and <strong>this</strong> is bold',
    ]);

    expect($update->plainExcerpt())->toBe('i <3 you and this is bold');
});

it('clips a long description on a word boundary', function () {
    $update = new Update;
    $update->forceFill(['excerpt' => str_repeat('word ', 100)]);

    $text = $update->plainExcerpt(60);

    expect(mb_strlen($text))->toBeLessThanOrEqual(61)
        ->and($text)->toEndWith('…')
        ->and($text)->not->toContain('wor…');
});
