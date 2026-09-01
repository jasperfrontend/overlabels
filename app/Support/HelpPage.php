<?php

namespace App\Support;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Reads a help page from resources/help/pages/<slug>.md and turns it into
 * everything the Blade view needs: metadata, rendered HTML, and a table of
 * contents derived from the headings.
 *
 * Markdown is the single source of truth for help prose. The same file is also
 * served verbatim at /help/<slug>.md, so a machine reads byte-identical content
 * to what the site renders. One source, two outputs, no drift - the same
 * principle as resources/dsl/dsl.json.
 *
 * A slug's directory decides its kind: `tutorials/foo` is a tutorial, anything
 * else is a guide. Reference entries are a separate corpus (HelpReferenceService)
 * that joins these in HelpCorpus. Rendering for all of them is HelpMarkdown.
 *
 * Frontmatter is deliberately FLAT `key: value` pairs. No YAML parser, no
 * nesting, nothing to get clever with. Breadcrumbs are derived, not authored.
 */
final class HelpPage
{
    /** Slugs are path segments; `bot/commands` is legal, `../secrets` is not. */
    private const string SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)?$/';

    public static function directory(): string
    {
        return resource_path('help/pages');
    }

    /**
     * Absolute path for a slug, or null if the slug is malformed or missing.
     */
    public static function path(string $slug): ?string
    {
        if (! preg_match(self::SLUG_PATTERN, $slug)) {
            return null;
        }

        $path = self::directory().'/'.$slug.'.md';

        return is_file($path) ? $path : null;
    }

    public static function exists(string $slug): bool
    {
        return self::path($slug) !== null;
    }

    /**
     * Every available slug, sorted. Used by the build command and tests.
     *
     * @return array<int,string>
     */
    public static function all(): array
    {
        $dir = self::directory();

        if (! is_dir($dir)) {
            return [];
        }

        $slugs = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
            $slugs[] = substr($relative, 0, -3);
        }

        sort($slugs);

        return $slugs;
    }

    /**
     * The public URL for a slug.
     *
     * An `index` slug collapses to its parent path, so `index` is /help and
     * `bot/index` is /help/bot. Route registration in routes/web.php derives
     * URLs the same way and calls through here so the two cannot drift.
     */
    public static function url(string $slug): string
    {
        $path = trim((string) preg_replace('#(^|/)index$#', '', $slug), '/');

        return '/help'.($path === '' ? '' : '/'.$path);
    }

    /**
     * Frontmatter only, without rendering the body.
     *
     * Reads line by line and stops at the closing delimiter, because the only
     * caller that needs this - the help-context index - reads every page on
     * every request. Slurping whole files to get at their first ten lines would
     * mean ~200KB of I/O to answer a question the first 1KB already answers.
     *
     * @return array<string,string>
     */
    public static function meta(string $slug): array
    {
        $path = self::path($slug);

        if ($path === null) {
            return [];
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $lines = [];
        $first = true;

        while (($line = fgets($handle)) !== false) {
            $line = rtrim(ltrim($line, "\xEF\xBB\xBF"), "\r\n");

            if ($first) {
                $first = false;
                if ($line !== '---') {
                    break;
                }

                continue;
            }

            if ($line === '---') {
                break;
            }

            $lines[] = $line;
        }

        fclose($handle);

        return self::parseFrontmatter(implode("\n", $lines));
    }

    /**
     * Split a `keywords:` frontmatter line into its terms.
     *
     * Comma-separated, because frontmatter here is flat `key: value` with no
     * YAML parser and therefore no lists. A term may contain spaces, so
     * `bang snippets` is one keyword rather than two.
     *
     * These exist because of how the search scores a long field. Fuse applies a
     * field norm, so the same exact match scores 0.0 in a short field and 0.89
     * in a 20KB body - well above the cutoff that throws coincidence away. The
     * word `autocomplete` appears five times in the editor guide and searching
     * for it returned nothing at all. A keyword is the author saying what a
     * page is about, in a field short enough for that to survive scoring.
     *
     * @return array<int,string>
     */
    public static function splitKeywords(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $terms = array_filter(
            array_map(trim(...), explode(',', $raw)),
            fn (string $term): bool => $term !== '',
        );

        return array_values(array_unique($terms));
    }

    /**
     * Render a page.
     *
     * @return array{
     *     slug:string, kind:string, title:string, description:string,
     *     heading:string, lead:string, canonical:string, section:?string,
     *     html:string, toc:array<int,array{id:string,text:string}>,
     *     readingMinutes:int
     * }
     */
    public static function render(string $slug): array
    {
        $path = self::path($slug);

        if ($path === null) {
            throw new RuntimeException("Help page not found: {$slug}");
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new RuntimeException("Help page not readable: {$slug}");
        }

        [$meta, $body] = self::splitFrontmatter($raw);

        // Guides are prose wrapped at ~100 columns, so soft breaks stay soft.
        // See HelpMarkdown::converter() for why that is a per-kind choice.
        [$html, $toc] = HelpMarkdown::render($body, HelpCorpus::linkMap(), softBreaks: false);

        return [
            'slug' => $slug,
            'kind' => HelpCorpus::kindOf($slug),
            'title' => $meta['title'] ?? Str::headline($slug),
            'description' => $meta['description'] ?? '',
            'heading' => $meta['heading'] ?? $meta['title'] ?? Str::headline($slug),
            'lead' => $meta['lead'] ?? $meta['description'] ?? '',
            'canonical' => $meta['canonical'] ?? 'https://overlabels.com'.self::url($slug),
            'section' => isset($meta['section']) ? trim($meta['section']) : null,
            'html' => $html,
            'toc' => $toc,
            'readingMinutes' => self::readingMinutes($body),
        ];
    }

    /**
     * Whole minutes at a slow reading pace, never less than one.
     *
     * Counted on the markdown source, so code samples and tag pills weigh in
     * as the words they are - a tutorial that is mostly a block to paste still
     * takes time to read. 200 words a minute is the conservative end of adult
     * prose speed, which suits documentation that gets read while something is
     * broken.
     */
    public static function readingMinutes(string $body): int
    {
        $words = str_word_count(strip_tags($body));

        return max(1, (int) ceil($words / 200));
    }

    /**
     * Split flat `key: value` frontmatter from the body.
     *
     * The implementation lives in App\Support\Frontmatter, shared with update
     * posts. Help pages pass no required keys, so the behaviour here is exactly
     * what it was when this method owned the logic.
     *
     * @return array{0:array<string,string>,1:string}
     */
    private static function splitFrontmatter(string $raw): array
    {
        return Frontmatter::split($raw);
    }

    /**
     * Turn a frontmatter block into flat `key => value` pairs.
     *
     * @return array<string,string>
     */
    private static function parseFrontmatter(string $block): array
    {
        return Frontmatter::parse($block);
    }
}
