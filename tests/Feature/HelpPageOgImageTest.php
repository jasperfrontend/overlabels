<?php

use App\Services\OgImageService;
use App\Support\HelpPage;
use Symfony\Component\Process\Process;

/**
 * Every guide, tutorial and deep dive gets its own OG card, the same way
 * reference entries and update posts already did. Before this, HelpController
 * never asked OgImageService for one, so every /help/<slug> page shared the
 * generic site image in link previews.
 *
 * resvg is baked into the app image but not installed on CI runners, so the
 * "is there a real PNG" test skips without it rather than failing.
 */
function helpResvgAvailable(): bool
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

it('never serves a help page with the generic jpg card', function () {
    $html = $this->get('/help/tokens')->assertOk()->getContent();

    preg_match('#<meta property="og:image" content="([^"]+)"#', $html, $matches);

    // A rendered card lives under /og/<hash>.png; a failed render falls back
    // to /ogimage.png. Either way the page asked for a card, which is the
    // thing that was missing.
    expect($matches[1] ?? '')->toMatch('#/og/[0-9a-f]{64}\.png$|/ogimage\.png$#')
        ->not->toEndWith('/ogimage.jpg');
});

it('gives two help pages two different cards', function () {
    if (! helpResvgAvailable()) {
        $this->markTestSkipped('resvg is not installed on this machine.');
    }

    $og = app(OgImageService::class);

    $tokens = HelpPage::render('tokens');
    $conditionals = HelpPage::render('conditionals');

    $first = $og->urlForPage($tokens, $tokens['canonical']);
    $second = $og->urlForPage($conditionals, $conditionals['canonical']);

    expect($first)->not->toBe('/ogimage.png')
        ->and($second)->not->toBe('/ogimage.png')
        ->and($first)->not->toBe($second)
        ->and(file_exists(public_path(ltrim($first, '/'))))->toBeTrue()
        ->and(file_exists(public_path(ltrim($second, '/'))))->toBeTrue();
});

it('labels the card with the page kind', function () {
    $tutorials = array_values(array_filter(HelpPage::all(), fn (string $slug) => str_starts_with($slug, 'tutorials/')));

    expect($tutorials)->not->toBeEmpty();

    $page = HelpPage::render($tutorials[0]);

    expect($page['kind'])->toBe('tutorial');
});
