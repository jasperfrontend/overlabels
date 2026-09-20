<?php

namespace App\Support;

/**
 * The webfont catalogue: every family Bunny Fonts serves, vendored.
 *
 * Bunny Fonts is a GDPR-friendly mirror of the Google Fonts library, free and
 * unauthenticated - there is no account, no key and no fonts API under
 * api.bunny.net. The whole catalogue is one public endpoint,
 * https://fonts.bunny.net/list, and `fonts:sync-bunny` writes a trimmed copy of
 * it to public/fonts-bunny.json.
 *
 * It is vendored rather than fetched for the same reason the help index is: the
 * picker must not put a third party on the page-load path, and the server needs
 * the same list to validate a written value. One file answers both.
 *
 * Two rules held across every family Bunny listed in September 2026, and the
 * whole design rests on them, so `fonts:sync-bunny` refuses a catalogue that
 * breaks either:
 *
 *   1. The slug is the family name lowercased with spaces hyphenated, exactly.
 *      That is what lets the overlay build a font URL from the control's value
 *      alone, with no catalogue shipped to it and nothing added to the
 *      broadcast. See syncWebfontLink() in OverlayRenderer.vue, which must
 *      keep deriving it the same way.
 *   2. Every family has weight 400 or weight 700 (only `buda` and
 *      `coda-caption` have neither, and the sync drops them). Bunny serves the
 *      weights of `:400,700` that exist and silently ignores the rest, but a
 *      request for only weights a family lacks comes back as an error comment
 *      with no font face in it. So one fixed weight pair is safe for every
 *      family that survives the sync, and WEIGHTS stays a constant on both
 *      sides.
 *
 * Family names are stored as the control's value, not slugs: the value already
 * flows into `--font` as the CSS family name, so storing the slug would mean
 * rewriting the var on the way through for no gain. Names are unique across the
 * catalogue and contain nothing but letters, digits and spaces, which is also
 * what makes them safe to interpolate into a URL after has() has passed them.
 */
class BunnyFonts
{
    /** Where the catalogue is fetched from, and where the sync writes it. */
    public const LIST_URL = 'https://fonts.bunny.net/list';

    public const CATALOGUE_PATH = 'fonts-bunny.json';

    /** The host the overlays load fonts from. */
    public const HOST = 'https://fonts.bunny.net';

    /**
     * The weights every font link asks for. Regular for body text, bold for
     * names. See rule 2 above for why a constant is safe here.
     */
    public const WEIGHTS = '400,700';

    /** What a font control falls back to, and what the chat overlay ships with. */
    public const DEFAULT_FAMILY = 'Albert Sans';

    /** @var array<string, array{name: string, category: string}>|null */
    private static ?array $cache = null;

    /**
     * The catalogue, keyed by slug.
     *
     * @return array<string, array{name: string, category: string}>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = public_path(self::CATALOGUE_PATH);

        if (! is_file($path)) {
            return self::$cache = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return self::$cache = is_array($decoded) ? $decoded : [];
    }

    /** Drop the memoised catalogue. For the sync command and for tests. */
    public static function flush(): void
    {
        self::$cache = null;
    }

    /**
     * The slug for a family name, by the rule the overlay also applies.
     * Pure string work - it says nothing about whether the family exists.
     */
    public static function slug(string $family): string
    {
        return strtolower(str_replace(' ', '-', trim($family)));
    }

    /** Is this a family Bunny serves? The allowlist behind every written value. */
    public static function has(string $family): bool
    {
        return isset(self::all()[self::slug($family)]);
    }

    /**
     * The catalogue's own spelling of a family name, or null if unknown.
     * A value arrives from a picker that was built from this same file, so
     * this is mostly identity - it exists so a value typed with the wrong
     * case is stored the way the CSS needs it rather than refused.
     */
    public static function canonical(string $family): ?string
    {
        return self::all()[self::slug($family)]['name'] ?? null;
    }

    /**
     * The stylesheet URL for a family, or null if it is not in the catalogue.
     *
     * Null rather than a best-effort URL on purpose: an unknown family returns
     * HTTP 200 with a CSS comment and no font face in it, so a link built for
     * one would look like it worked and quietly render the fallback.
     */
    public static function cssUrl(string $family): ?string
    {
        $slug = self::slug($family);

        if (! isset(self::all()[$slug])) {
            return null;
        }

        return self::HOST.'/css?family='.$slug.':'.self::WEIGHTS.'&display=swap';
    }

    /**
     * The catalogue as the picker renders it: one row per family, sorted by
     * name, carrying the category it can be filtered by.
     *
     * @return list<array{value: string, slug: string, category: string}>
     */
    public static function choices(): array
    {
        $rows = [];

        foreach (self::all() as $slug => $entry) {
            $rows[] = ['value' => $entry['name'], 'slug' => $slug, 'category' => $entry['category']];
        }

        usort($rows, fn (array $a, array $b) => strcasecmp($a['value'], $b['value']));

        return $rows;
    }
}
