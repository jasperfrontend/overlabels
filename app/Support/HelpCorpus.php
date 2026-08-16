<?php

namespace App\Support;

use App\Services\HelpReferenceService;
use Illuminate\Support\Str;

/**
 * Every help document, of every kind, as one list.
 *
 * Help used to be three unrelated piles: prose pages under resources/help/pages,
 * reference entries under resources/help/reference, and whatever the Alt+R
 * palette had globbed into the JS bundle. Nothing could see across them, so the
 * sitemap was hand-maintained (and had rotted by fourteen pages), search covered
 * the reference only, and a guide could not link to a reference entry by slug.
 *
 * This is the one index they all read.
 *
 * Tutorials are deliberately NOT a new mechanism. They are ordinary help pages
 * living in the `tutorials/` subdirectory, exactly like the `bot/` pages that
 * already worked that way, so routing, the `.md` twin, HelpContext and the
 * sitemap pick them up with no new code. The kind is derived from the slug.
 */
final class HelpCorpus
{
    public const KIND_TUTORIAL = 'tutorial';

    public const KIND_GUIDE = 'guide';

    public const KIND_REFERENCE = 'reference';

    /** The subdirectory under resources/help/pages that makes a page a tutorial. */
    public const TUTORIAL_PREFIX = 'tutorials/';

    /**
     * Page slugs that a reference entry deliberately shadows in the wikilink map.
     *
     * `[[chat]]` is written inside the reference vault, where it sits alongside
     * `[[subscribers]]` and `[[goals]]` and plainly means the foreach loop. The
     * guide at /help/chat wants the same name and cannot have it, which is fine:
     * wikilinks are an authoring convenience for the reference, and prose pages
     * are linked with ordinary markdown links that name the URL outright.
     *
     * Anything not on this list must not collide - see HelpUnificationTest. A
     * silent collision would repoint existing links to a different document.
     */
    public const SHADOWED_PAGE_SLUGS = ['chat'];

    public const KIND_LABELS = [
        self::KIND_TUTORIAL => 'Tutorial',
        self::KIND_GUIDE => 'Guide',
        self::KIND_REFERENCE => 'Reference',
    ];

    /** @var array<int,array<string,mixed>>|null */
    private static ?array $memo = null;

    /** @var array<string,string>|null */
    private static ?array $links = null;

    /**
     * Which kind a page slug is. Reference entries never come through here -
     * they are identified by their corpus, not their slug.
     */
    public static function kindOf(string $slug): string
    {
        return str_starts_with($slug, self::TUTORIAL_PREFIX)
            ? self::KIND_TUTORIAL
            : self::KIND_GUIDE;
    }

    /**
     * Every document: tutorials, then guides, then reference entries.
     *
     * `body` is included because the search index and the sitemap both read
     * this, and the only caller that does not want it (the nav) is already
     * paying for the file reads anyway.
     *
     * @return array<int,array{kind:string,kindLabel:string,slug:string,title:string,lead:string,url:string,path:string,body:string,category:?string,categoryLabel:?string}>
     */
    public static function all(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $docs = [];

        foreach (HelpPage::all() as $slug) {
            $meta = HelpPage::meta($slug);
            $path = HelpPage::path($slug);

            $docs[] = [
                'kind' => self::kindOf($slug),
                'kindLabel' => self::KIND_LABELS[self::kindOf($slug)],
                'slug' => $slug,
                // Same reasoning as HelpContext: `heading` is the page's short
                // name, `title` is written for a browser tab and runs long.
                'title' => $meta['heading'] ?? $meta['title'] ?? Str::headline($slug),
                'lead' => $meta['lead'] ?? $meta['description'] ?? '',
                'url' => HelpPage::url($slug),
                'path' => (string) $path,
                'body' => $path !== null ? (string) file_get_contents($path) : '',
                'category' => null,
                'categoryLabel' => null,
            ];
        }

        // Tutorials lead, because the index page leads with them and the search
        // results should agree with it: someone typing "chat" wants the tutorial
        // before the twelve reference fields it mentions.
        usort($docs, function (array $a, array $b): int {
            $rank = fn (array $d): int => $d['kind'] === self::KIND_TUTORIAL ? 0 : 1;

            return [$rank($a), $a['title']] <=> [$rank($b), $b['title']];
        });

        foreach (app(HelpReferenceService::class)->all() as $entry) {
            $docs[] = [
                'kind' => self::KIND_REFERENCE,
                'kindLabel' => self::KIND_LABELS[self::KIND_REFERENCE],
                'slug' => $entry['slug'],
                'title' => $entry['title'],
                'lead' => '',
                'url' => "/help/reference/{$entry['category']}/{$entry['slug']}",
                'path' => $entry['path'],
                'body' => $entry['body'],
                'category' => $entry['category'],
                'categoryLabel' => $entry['categoryLabel'],
            ];
        }

        return self::$memo = $docs;
    }

    /**
     * Tutorials and guides only, in index order.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function docs(): array
    {
        return array_values(array_filter(
            self::all(),
            fn (array $d): bool => $d['kind'] !== self::KIND_REFERENCE,
        ));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function ofKind(string $kind): array
    {
        return array_values(array_filter(
            self::all(),
            fn (array $d): bool => $d['kind'] === $kind,
        ));
    }

    /**
     * slug => url, for resolving `[[wikilinks]]` across the whole corpus.
     *
     * Reference entries are registered FIRST and win any collision. Every
     * existing wikilink in the 147 reference files was written against that
     * namespace, so a page slug that happens to match one must not silently
     * repoint them. Page slugs only ever fill gaps.
     *
     * @return array<string,string>
     */
    public static function linkMap(): array
    {
        if (self::$links !== null) {
            return self::$links;
        }

        $map = [];

        foreach (self::all() as $doc) {
            if ($doc['kind'] !== self::KIND_REFERENCE) {
                continue;
            }
            $map[$doc['slug']] ??= $doc['url'];
        }

        foreach (self::docs() as $doc) {
            $map[$doc['slug']] ??= $doc['url'];
        }

        return self::$links = $map;
    }

    /**
     * Drop the memo. Tests mutate the corpus on disk between assertions, and
     * `help:build-index` needs a cold read after flushing the reference cache.
     */
    public static function flush(): void
    {
        self::$memo = null;
        self::$links = null;
    }
}
