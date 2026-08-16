<?php

namespace App\Services;

use App\Support\HelpCorpus;
use App\Support\HelpMarkdown;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Finder\Finder;

class HelpReferenceService
{
    public const CATEGORY_LABELS = [
        'template-tags' => 'Template Tags',
        'eventsub-tags' => 'EventSub Tags',
        'eventsub-events' => 'EventSub Events',
        'foreach-loops' => 'Foreach Loops',
        'integration-controls' => 'Integration Controls',
        'for-machines' => 'For Machines',
    ];

    // `for-machines` sits last on purpose: humans open this page for tags, and
    // the crawl signal it exists for does not care about sidebar position. The
    // index page's article column carries the prominent link instead.
    public const CATEGORY_ORDER = [
        'template-tags',
        'eventsub-tags',
        'eventsub-events',
        'foreach-loops',
        'integration-controls',
        'for-machines',
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
     * Map slug -> category for the first-seen occurrence.
     *
     * Wikilink resolution moved to HelpCorpus::linkMap(), which spans every
     * kind of document; this remains the reference-only view of the same idea
     * and is what tests assert a documented slug against.
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

        // HelpCorpus memoises this corpus for the request; dropping the cache
        // underneath it while leaving the memo would serve stale entries to
        // the nav and the wikilink map.
        HelpCorpus::flush();
    }

    /**
     * Render a reference body.
     *
     * The pipeline itself lives in HelpMarkdown, shared with every other kind
     * of help document. Two things are specific to this corpus:
     *
     *  - Soft breaks become `<br />`. Reference entries are written one
     *    statement per line and read as nonsense when those lines are joined.
     *  - Wikilinks resolve against the WHOLE corpus now, not just the reference,
     *    so an entry can link out to the guide that explains it.
     */
    public function render(string $body): string
    {
        [$html] = HelpMarkdown::render($body, HelpCorpus::linkMap(), softBreaks: true);

        return $html;
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
