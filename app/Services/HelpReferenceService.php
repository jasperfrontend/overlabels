<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Symfony\Component\Finder\Finder;

class HelpReferenceService
{
    public const CATEGORY_LABELS = [
        'template-tags' => 'Template Tags',
        'eventsub-tags' => 'EventSub Tags',
        'eventsub-events' => 'EventSub Events',
        'foreach-loops' => 'Foreach Loops',
    ];

    public const CATEGORY_ORDER = [
        'template-tags',
        'eventsub-tags',
        'eventsub-events',
        'foreach-loops',
    ];

    private string $rootPath;

    public function __construct()
    {
        $this->rootPath = resource_path('help/reference');
    }

    /**
     * @return array<int, array{category:string, categoryLabel:string, slug:string, title:string, body:string, path:string}>
     */
    public function all(): array
    {
        return Cache::remember(
            $this->cacheKey(),
            now()->addMinutes(60),
            fn () => $this->scan(),
        );
    }

    public function get(string $category, string $slug): ?array
    {
        foreach ($this->all() as $entry) {
            if ($entry['category'] === $category && $entry['slug'] === $slug) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{category:string, categoryLabel:string, items:array<int, array<string, mixed>>}>
     */
    public function grouped(): array
    {
        $groups = [];
        foreach ($this->all() as $entry) {
            $groups[$entry['category']] ??= [
                'category' => $entry['category'],
                'categoryLabel' => $entry['categoryLabel'],
                'items' => [],
            ];
            $groups[$entry['category']]['items'][] = $entry;
        }

        $ordered = [];
        foreach (self::CATEGORY_ORDER as $cat) {
            if (isset($groups[$cat])) {
                $ordered[] = $groups[$cat];
                unset($groups[$cat]);
            }
        }
        foreach ($groups as $g) {
            $ordered[] = $g;
        }

        return $ordered;
    }

    /**
     * Map slug -> category for the first-seen occurrence. Used by the wikilink
     * preprocessor to resolve `[[slug]]` to `/help/reference/{category}/{slug}`.
     *
     * @return array<string, string>
     */
    public function slugToCategory(): array
    {
        $map = [];
        foreach ($this->all() as $entry) {
            if (! isset($map[$entry['slug']])) {
                $map[$entry['slug']] = $entry['category'];
            }
        }

        return $map;
    }

    public function flush(): void
    {
        Cache::forget($this->cacheKey());
    }

    /**
     * Convert a markdown body into the same HTML the JS pipeline produced:
     *  - Obsidian-style `[[slug]]` and `[[slug|label]]` become real
     *    `/help/reference/{category}/{slug}` links (or inline code if the slug
     *    is unknown).
     *  - Triple-bracket `[[[tag]]]` snippets become click-to-copy widgets.
     * Both transforms are applied outside fenced and inline code spans so that
     * authors can show literal syntax inside backticks without it being
     * rewritten.
     */
    public function render(string $body): string
    {
        $preprocessed = $this->preprocessMarkdown($body);
        $html = (string) $this->converter()->convert($preprocessed);

        return $this->enhanceTagsInHtml($html);
    }

    private function preprocessMarkdown(string $md): string
    {
        $slugToCategory = $this->slugToCategory();

        // Split by fenced code blocks (```...```). Even indexes are prose, odd
        // are code blocks left untouched.
        $fenceParts = preg_split('/(```[\s\S]*?```)/', $md, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($fenceParts === false) {
            return $md;
        }

        foreach ($fenceParts as $i => $part) {
            if ($i % 2 === 1) {
                continue; // fenced code, preserve
            }
            // Within prose, also preserve inline-code spans.
            $inlineParts = preg_split('/(`[^`\n]*`)/', $part, -1, PREG_SPLIT_DELIM_CAPTURE);
            if ($inlineParts === false) {
                continue;
            }
            foreach ($inlineParts as $j => $seg) {
                if ($j % 2 === 1) {
                    continue;
                }
                $inlineParts[$j] = $this->rewriteWikilinks($seg, $slugToCategory);
            }
            $fenceParts[$i] = implode('', $inlineParts);
        }

        return implode('', $fenceParts);
    }

    private function rewriteWikilinks(string $text, array $slugToCategory): string
    {
        // `[[slug]]` or `[[slug|label]]`. Negative lookbehind/lookahead so we
        // don't bite into the inner `[[` of a triple-bracket template tag.
        return preg_replace_callback(
            '/(?<!\[)\[\[([^\]|\[]+?)(?:\|([^\]]+))?]](?!])/',
            function (array $m) use ($slugToCategory): string {
                $slug = trim($m[1]);
                $label = trim($m[2] ?? $slug);
                if (isset($slugToCategory[$slug])) {
                    $cat = $slugToCategory[$slug];

                    return "[{$label}](/help/reference/{$cat}/{$slug})";
                }

                return "`{$label}`";
            },
            $text,
        ) ?? $text;
    }

    private function enhanceTagsInHtml(string $html): string
    {
        // Unwrap any single-tag inline `<code>[[[...]]]</code>` so we don't
        // produce nested <code><code>...</code></code>.
        $out = preg_replace(
            '/<code>\s*(\[\[\[[^\[\]<>]+]]])\s*<\/code>/',
            '$1',
            $html,
        ) ?? $html;

        // Wrap every remaining [[[tag]]] in a clickable <code>.
        return preg_replace_callback(
            '/\[\[\[([^\[\]<>]+?)]]]/',
            function (array $m): string {
                $tag = "[[[{$m[1]}]]]";
                $attr = htmlspecialchars($tag, ENT_QUOTES, 'UTF-8');

                return '<code class="ov-tag" role="button" tabindex="0" data-tag="'.$attr.'" title="Click to copy">'.$tag.'</code>';
            },
            $out,
        ) ?? $out;
    }

    private function converter(): MarkdownConverter
    {
        $env = new Environment([
            'renderer' => [
                'soft_break' => "<br />\n",
            ],
        ]);
        $env->addExtension(new CommonMarkCoreExtension);
        $env->addExtension(new GithubFlavoredMarkdownExtension);

        return new MarkdownConverter($env);
    }

    private function cacheKey(): string
    {
        $mtime = is_dir($this->rootPath) ? filemtime($this->rootPath) : 0;

        return "help_reference_index:{$mtime}";
    }

    /**
     * @return array<int, array{category:string, categoryLabel:string, slug:string, title:string, body:string, path:string}>
     */
    private function scan(): array
    {
        if (! is_dir($this->rootPath)) {
            return [];
        }

        $finder = (new Finder)
            ->files()
            ->in($this->rootPath)
            ->name('*.md')
            ->depth('== 1');

        $entries = [];
        foreach ($finder as $file) {
            $rel = str_replace('\\', '/', $file->getRelativePathname());
            if (! preg_match('#^([^/]+)/([^/]+)\.md$#', $rel, $m)) {
                continue;
            }
            [, $category, $slug] = $m;
            $body = trim($file->getContents());
            $entries[] = [
                'category' => $category,
                'categoryLabel' => self::CATEGORY_LABELS[$category] ?? $this->humanize($category),
                'slug' => $slug,
                'title' => $this->extractTitle($body, $this->humanize($slug)),
                'body' => $body,
                'path' => $file->getPathname(),
            ];
        }

        usort($entries, function (array $a, array $b) {
            $ai = array_search($a['category'], self::CATEGORY_ORDER, true);
            $bi = array_search($b['category'], self::CATEGORY_ORDER, true);
            $ai = $ai === false ? 999 : $ai;
            $bi = $bi === false ? 999 : $bi;
            if ($ai !== $bi) {
                return $ai <=> $bi;
            }

            return strcmp($a['title'], $b['title']);
        });

        return $entries;
    }

    private function humanize(string $slug): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $slug));
    }

    private function extractTitle(string $body, string $fallback): string
    {
        if (preg_match('/^#\s+(.+)$/m', $body, $m)) {
            return trim($m[1]);
        }

        return $fallback;
    }
}
