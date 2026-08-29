<?php

use App\Services\OgImageService;

/**
 * bodyExcerpt() is private and its output never reaches a string a caller can
 * assert on - it is folded into a context array that is hashed into a PNG
 * filename. Reflection is the only way to see it, following the pattern in
 * TwitchPollWinnersTest.
 */
function callBodyExcerpt(string $body): string
{
    $service = app(OgImageService::class);
    $method = new ReflectionMethod($service, 'bodyExcerpt');
    $method->setAccessible(true);

    return $method->invoke($service, $body);
}

test('a triple-bracket tag loses all three brackets, not two of them', function () {
    // The wikilink pattern excludes `[` from its inner class, so run before
    // the triple-bracket pass it skips the first bracket, matches the inner
    // [[channel_name]] and leaves a stray [channel_name] behind. 52 of the
    // 143 reference entries rendered a bracketed tag on their card that way.
    expect(callBodyExcerpt('Use [[[channel_name]]] in your overlay.'))
        ->toBe('Use channel_name in your overlay.');
});

test('a namespaced or prefixed tag is stripped the same way', function () {
    expect(callBodyExcerpt('Try [[[foreach:chat as msg]]] and [[[c:kofi:donations_received]]].'))
        ->toBe('Try foreach:chat as msg and c:kofi:donations_received.');
});

test('a genuine wikilink still resolves to its target', function () {
    expect(callBodyExcerpt('See [[chat]] for details.'))
        ->toBe('See chat for details.');
});

test('a piped wikilink still resolves to its label', function () {
    expect(callBodyExcerpt('See [[chat|the chat page]] for details.'))
        ->toBe('See the chat page for details.');
});

test('wikilinks and tags survive each other in one line', function () {
    expect(callBodyExcerpt('See [[chat]] and [[slug|a label]] plus [[[channel_name]]].'))
        ->toBe('See chat and a label plus channel_name.');
});

test('headings, fences and bullets are still dropped', function () {
    $body = "# Title\n\n## A heading\n\n```\ncode fence\n```\n\n- a bullet\n- another";

    expect(callBodyExcerpt($body))->toBe('a bullet another');
});
