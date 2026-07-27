<?php

namespace App\Support;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Reads a help page from resources/help/pages/<slug>.md and turns it into
 * everything the Vue renderer needs: metadata, rendered HTML, and a table of
 * contents derived from the headings.
 *
 * Markdown is the single source of truth for help prose. The same file is
 * copied verbatim to public/help/<slug>.md by `php artisan help:build`, so a
 * machine can fetch the real content instead of the empty Inertia shell that
 * the old hand-written .vue pages served. One source, two outputs, no drift -
 * the same principle as resources/dsl/dsl.json.
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
     * Render a page.
     *
     * @return array{
     *     slug:string, title:string, description:string, heading:string,
     *     lead:string, canonical:string, section:?string,
     *     html:string, toc:array<int,array{id:string,text:string}>
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

        $html = Str::markdown($body, [
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);

        $html = self::transformCallouts($html);
        [$html, $toc] = self::addHeadingAnchors($html);

        return [
            'slug' => $slug,
            'title' => $meta['title'] ?? Str::headline($slug),
            'description' => $meta['description'] ?? '',
            'heading' => $meta['heading'] ?? $meta['title'] ?? Str::headline($slug),
            'lead' => $meta['lead'] ?? $meta['description'] ?? '',
            'canonical' => $meta['canonical'] ?? 'https://overlabels.com/help/'.$slug,
            'section' => $meta['section'] ?? null,
            'html' => $html,
            'toc' => $toc,
        ];
    }

    /**
     * Split flat `key: value` frontmatter from the body.
     *
     * @return array{0:array<string,string>,1:string}
     */
    private static function splitFrontmatter(string $raw): array
    {
        $raw = ltrim($raw, "\xEF\xBB\xBF");
        $normalized = str_replace("\r\n", "\n", $raw);

        if (! str_starts_with($normalized, "---\n")) {
            return [[], $normalized];
        }

        $end = strpos($normalized, "\n---", 3);

        if ($end === false) {
            return [[], $normalized];
        }

        $block = substr($normalized, 4, $end - 3);
        $body = ltrim(substr($normalized, $end + 4), "\n");

        $meta = [];
        foreach (explode("\n", $block) as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $meta[trim($key)] = trim(trim(trim($value), '"\''));
        }

        return [$meta, $body];
    }

    /**
     * Turn GitHub-style alert blockquotes into styled callouts.
     *
     *   > [!NOTE]
     *   > Snapshots are a promise in both directions.
     *
     * The syntax is deliberately GitHub's: it is widely recognised, LLMs know
     * it, and it still reads correctly as plain text when the raw .md is
     * fetched from public/help. Anything else stays an ordinary blockquote.
     */
    private static function transformCallouts(string $html): string
    {
        $kinds = [
            'NOTE' => 'note',
            'TIP' => 'tip',
            'IMPORTANT' => 'important',
            'WARNING' => 'warning',
            'CAUTION' => 'warning',
        ];

        return preg_replace_callback(
            '/<blockquote>\s*(.*?)\s*<\/blockquote>/s',
            function (array $m) use ($kinds): string {
                $inner = $m[1];

                if (! preg_match('/\[!([A-Z]+)]/', $inner, $tag)) {
                    return $m[0];
                }

                $kind = $kinds[$tag[1]] ?? null;

                if ($kind === null) {
                    return $m[0];
                }

                // Drop the marker, plus the now-empty paragraph if it was alone.
                $inner = str_replace($tag[0], '', $inner);
                $inner = preg_replace('/<p>\s*<\/p>/', '', $inner);
                $inner = preg_replace('/<p>\s*<br\s*\/?>\s*/', '<p>', $inner);

                return sprintf('<div class="help-callout help-callout--%s">%s</div>', $kind, trim($inner));
            },
            $html
        );
    }

    /**
     * Give every h2/h3 a stable id and collect the h2s as a table of contents.
     *
     * Generating the TOC removes a whole class of rot: the old .vue pages each
     * carried a hand-written list of anchors that had to be kept in step with
     * the sections by hand.
     *
     * @return array{0:string,1:array<int,array{id:string,text:string}>}
     */
    private static function addHeadingAnchors(string $html): array
    {
        $toc = [];
        $used = [];

        $html = preg_replace_callback(
            '/<h([23])>(.*?)<\/h\1>/s',
            function (array $m) use (&$toc, &$used): string {
                $level = (int) $m[1];
                $inner = $m[2];
                $text = trim(html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                // "1. Writing block CSS" -> "writing-block-css"
                $base = Str::slug(preg_replace('/^\s*\d+[.)]\s*/', '', $text)) ?: 'section';

                $id = $base;
                $n = 2;
                while (isset($used[$id])) {
                    $id = $base.'-'.$n++;
                }
                $used[$id] = true;

                if ($level === 2) {
                    $toc[] = ['id' => $id, 'text' => $text];
                }

                return sprintf('<h%d id="%s">%s</h%d>', $level, $id, $inner, $level);
            },
            $html
        );

        return [$html, $toc];
    }
}
