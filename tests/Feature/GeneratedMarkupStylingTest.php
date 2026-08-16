<?php

/*
 * Markup the renderer generates itself - emote and badge <img> elements - must
 * carry CLASSES and no inline `style` attribute.
 *
 * Inline styles outrank every selector short of `!important`, so an emote with
 * `style="height:1.5em"` baked in cannot be resized by an ordinary template
 * rule. Overlabels serves lego bricks; deciding how tall someone's emotes are
 * is not its job.
 *
 * The defaults still exist, as `.overlay-emote` in BASE_OVERLAY_CSS, injected
 * first in <head> so any template rule at equal specificity beats it. Nothing
 * changed visually - only who gets the last word.
 *
 * This guards the easy regression: someone fixing a sizing complaint reaches
 * for the nearest string template and adds `style=""` back, and no visual test
 * would object.
 */

function generatedMarkupSources(): array
{
    return [
        'useEmoteParser.ts' => resource_path('js/composables/useEmoteParser.ts'),
        'badgeRenderer.ts' => resource_path('js/utils/badgeRenderer.ts'),
    ];
}

it('generates emote and badge markup without inline styles', function () {
    foreach (generatedMarkupSources() as $name => $path) {
        $source = file_get_contents($path);

        // Anchored to an actual `<img` tag rather than a bare `style=`, so a
        // comment explaining why inline styles are avoided does not trip it.
        expect(preg_match('/<img[^>]*style=/', $source))
            ->toBe(0, "$name emits an inline style attribute; put the rule in BASE_OVERLAY_CSS instead");
    }
});

it('still tags generated markup with the classes templates style against', function () {
    // The classes are the contract that replaces the inline styles. Losing one
    // would leave authors with nothing to select.
    $emotes = file_get_contents(generatedMarkupSources()['useEmoteParser.ts']);
    $badges = file_get_contents(generatedMarkupSources()['badgeRenderer.ts']);

    expect($emotes)->toContain('class="overlay-emote"')
        ->and($emotes)->toContain('overlay-emote twitch-emote')
        ->and($badges)->toContain('class="ol-badge"');
});

it('keeps the emote defaults in the overlay base stylesheet', function () {
    // Removing the inline styles without providing the rule would change every
    // existing overlay's appearance, which this change explicitly does not do.
    $renderer = file_get_contents(resource_path('js/components/OverlayRenderer.vue'));

    expect($renderer)->toContain('BASE_OVERLAY_CSS')
        ->and($renderer)->toContain('.overlay-emote')
        ->and($renderer)->toContain('height: 1.5em');
});

it('inserts the base stylesheet first so template CSS can override it', function () {
    // insertBefore(head.firstChild), not appendChild. Order is the entire
    // mechanism: at equal specificity the later rule wins, and the template's
    // rule must be the later one.
    $renderer = file_get_contents(resource_path('js/components/OverlayRenderer.vue'));

    expect($renderer)->toContain('document.head.insertBefore(style, document.head.firstChild)');
});
