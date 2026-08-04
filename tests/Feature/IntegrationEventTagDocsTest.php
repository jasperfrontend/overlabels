<?php

use App\Services\External\ExternalServiceRegistry;
use App\Services\HelpReferenceService;

/**
 * Every template tag a donation driver emits must be documented.
 *
 * Buy Me a Coffee shipped with seventeen event tags and no reference page at
 * all, which nothing noticed because documentation coverage was not checked
 * anywhere. Ko-fi's page was separately wrong in a way only a reader would hit:
 * it described `event.source` as lowercase "kofi" when the driver emits
 * "Ko-fi", so `[[[if:event.source = kofi]]]` silently never matched.
 *
 * The tags are read out of the driver source rather than by calling
 * normalizeEvent(), which would need a realistic signed payload per service.
 * A regex over `'event.x' =>` literals is enough to catch the case that
 * actually happens: a driver gains a tag, or a whole integration ships, and the
 * reference never hears about it.
 */

/**
 * Nothing is excluded any more. GPS was, until it got its own page - it is
 * telemetry rather than a donation service, so it documents position, speed and
 * device state instead of the shared donation six. Keep this list empty unless
 * there is a real reason, and write the reason here.
 */
const UNDOCUMENTED_BY_DESIGN = [];

function driverEventTags(string $service): array
{
    $class = new ReflectionClass(ExternalServiceRegistry::driver($service));
    $source = file_get_contents($class->getFileName());

    preg_match_all("/'(event\.[a-z_]+)'\s*=>/", $source, $matches);

    return array_values(array_unique($matches[1]));
}

function documentedTags(): string
{
    $dir = resource_path('help/reference/eventsub-tags');

    return implode("\n", array_map(
        fn (string $f) => file_get_contents($f),
        glob($dir.'/*.md'),
    ));
}

test('every tag a donation driver emits appears in the eventsub-tags reference', function (string $service) {
    $docs = documentedTags();
    $tags = driverEventTags($service);

    expect($tags)->not->toBeEmpty("no event tags found in the {$service} driver - did the source shape change?");

    $undocumented = array_values(array_filter(
        $tags,
        fn (string $tag) => ! str_contains($docs, "[[[{$tag}]]]"),
    ));

    expect($undocumented)->toBe([], sprintf(
        "%s emits tags with no reference entry: %s\nAdd them to resources/help/reference/eventsub-tags/.",
        $service,
        implode(', ', $undocumented),
    ));
})->with(array_values(array_diff(ExternalServiceRegistry::services(), UNDOCUMENTED_BY_DESIGN)));

test('reference wikilinks point at slugs that exist', function () {
    $service = app(HelpReferenceService::class);
    $known = $service->slugToCategory();

    $broken = [];

    foreach (glob(resource_path('help/reference/*/*.md')) as $path) {
        $body = file_get_contents($path);

        // `[[slug]]` or `[[slug|label]]`, skipping the inner `[[` of a triple
        // bracket template tag - the same pattern the renderer itself uses.
        preg_match_all('/(?<!\[)\[\[([^\]|\[]+?)(?:\|([^\]]+))?]](?!])/', $body, $matches);

        foreach ($matches[1] as $slug) {
            $slug = trim($slug);

            // Obsidian attachment embeds (`[[Pasted image ....png]]`) are not
            // page links and never resolve to a slug. The vault is authored in
            // Obsidian, so they turn up in prose.
            if (preg_match('/\.(png|jpe?g|gif|svg|webp|pdf)$/i', $slug)) {
                continue;
            }

            if (! isset($known[$slug])) {
                $broken[] = basename($path).' -> [['.$slug.']]';
            }
        }
    }

    // A wikilink whose slug does not resolve degrades to inline code rather
    // than erroring, so these rot silently. Three Ko-fi pages linked each other
    // by title ("All Ko-fi Events") instead of slug ("all-ko-fi-events") and
    // had been rendering as plain code the whole time.
    expect($broken)->toBe([]);
});
