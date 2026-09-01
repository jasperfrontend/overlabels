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
 * Deep dives (`deep-dives/`) follow the identical pattern.
 */
final class HelpCorpus
{
    public const KIND_TUTORIAL = 'tutorial';

    public const KIND_GUIDE = 'guide';

    public const KIND_DEEP_DIVE = 'deep-dive';

    public const KIND_REFERENCE = 'reference';

    /** The subdirectory under resources/help/pages that makes a page a tutorial. */
    public const TUTORIAL_PREFIX = 'tutorials/';

    /** The subdirectory that makes a page a deep dive - same mechanism, longer read. */
    public const DEEP_DIVE_PREFIX = 'deep-dives/';

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
     *
     * `checkin` is the same situation as `chat`: the generated
     * integration-controls reference entry owns the wikilink name, the guide
     * at /help/checkin is reached by ordinary markdown links.
     */
    public const SHADOWED_PAGE_SLUGS = ['chat', 'checkin'];

    public const KIND_LABELS = [
        self::KIND_TUTORIAL => 'Tutorial',
        self::KIND_GUIDE => 'Guide',
        self::KIND_DEEP_DIVE => 'Deep dive',
        self::KIND_REFERENCE => 'Reference',
    ];

    /**
     * The guide taxonomy, in display order.
     *
     * A guide declares which of these it belongs to in a `section:` frontmatter
     * line, and the landing page and the sidebar tree are built from that. The
     * list is closed on purpose: a `section:` value not on it fails
     * HelpTaxonomyTest rather than quietly opening an eighth column, and a
     * section with no pages fails too, so the constant cannot rot into labels
     * nothing uses. Tutorials and deep dives are kinds, not sections, and
     * declare nothing.
     *
     * Before September 2026 the thirty-odd guides were one alphabetical list.
     *
     * Label => the one-line description the landing page prints under it.
     */
    public const SECTIONS = [
        'Getting started' => 'What Overlabels is, and how an overlay gets from a text field to your stream.',
        'Tags & syntax' => 'The template language: conditionals, formatting pipes and the math engine.',
        'Building overlays' => 'The editor, the Builder, blocks and styling.',
        'Live data' => 'Controls, expressions and Lists - the values your overlay reads.',
        'Bot & chat' => 'The @overlabels bot, chat commands and chat on screen.',
        'Integrations & testing' => 'Donation services, test mode, firing fake events and access tokens.',
        'For machines' => 'Plain-text and JSON versions of everything here, for assistants and scripts.',
    ];

    /**
     * Links that belong in a section but are not markdown pages.
     *
     * These are the two help pages that deliberately stay Inertia (live data
     * from controlPresets.ts, and a Vue app), so they are not in the corpus
     * and cannot declare a `section:` of their own. They are listed here so the
     * landing and the sidebar show them where the index has always shown them.
     *
     * @var array<string,array<int,array{title:string,url:string}>>
     */
    public const SECTION_EXTRAS = [
        'Live data' => [
            ['title' => 'Integration Presets', 'url' => '/help/integration-presets'],
        ],
        'Bot & chat' => [
            ['title' => 'Chat Castle', 'url' => '/help/gamejam'],
        ],
    ];

    /** @var array<int,array<string,mixed>>|null */
    private static ?array $memo = null;

    /** @var array<string,string>|null */
    private static ?array $links = null;

    /** @var array<string,int>|null */
    private static ?array $indexOrder = null;

    /**
     * Which kind a page slug is. Reference entries never come through here -
     * they are identified by their corpus, not their slug.
     */
    public static function kindOf(string $slug): string
    {
        if (str_starts_with($slug, self::TUTORIAL_PREFIX)) {
            return self::KIND_TUTORIAL;
        }

        return str_starts_with($slug, self::DEEP_DIVE_PREFIX)
            ? self::KIND_DEEP_DIVE
            : self::KIND_GUIDE;
    }

    /**
     * Every document: tutorials, then guides, then reference entries.
     *
     * `body` is included because the search index and the sitemap both read
     * this, and the only caller that does not want it (the nav) is already
     * paying for the file reads anyway.
     *
     * @return array<int,array{kind:string,kindLabel:string,slug:string,title:string,lead:string,url:string,path:string,body:string,keywords:array<int,string>,category:?string,categoryLabel:?string,section:?string}>
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
                // Search terms the author declared for this page. Separate from
                // `body` because Fuse's field norm makes a match in a 20KB body
                // score like coincidence - see HelpPage::splitKeywords().
                'keywords' => HelpPage::splitKeywords($meta['keywords'] ?? null),
                'category' => null,
                'categoryLabel' => null,
                // Which SECTIONS column a guide sits in. Null for tutorials and
                // deep dives, whose kind already places them.
                'section' => isset($meta['section']) ? trim($meta['section']) : null,
            ];
        }

        // Tutorials lead, because the index page leads with them and the search
        // results should agree with it: someone typing "chat" wants the tutorial
        // before the twelve reference fields it mentions.
        // Deep dives sit last among the prose: they are long reads about one
        // overlay, not the page someone searching for an answer wants first.
        usort($docs, function (array $a, array $b): int {
            $rank = fn (array $d): int => match ($d['kind']) {
                self::KIND_TUTORIAL => 0,
                self::KIND_DEEP_DIVE => 2,
                default => 1,
            };

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
                // Reference entries have no frontmatter at all - their title is
                // read from the first heading - so there is nowhere to declare
                // one. They are named after the thing they document, which is
                // the query anyway.
                'keywords' => [],
                'category' => $entry['category'],
                'categoryLabel' => $entry['categoryLabel'],
                'section' => null,
            ];
        }

        return self::$memo = $docs;
    }

    /**
     * The guides, grouped into SECTIONS in display order.
     *
     * Each section carries its label, its description, an anchor for the
     * landing page, and its pages in index.md order (see sortByIndex()).
     * SECTION_EXTRAS take part in that ordering like any other link. The root
     * help index is the landing page itself and is never listed.
     *
     * A guide with no section, or with one that is not in SECTIONS, is left
     * out here and reported by HelpTaxonomyTest - the landing must never
     * silently swallow a page.
     *
     * @return array<int,array{label:string,description:string,anchor:string,items:array<int,array<string,mixed>>}>
     */
    public static function sections(): array
    {
        $byLabel = array_fill_keys(array_keys(self::SECTIONS), []);

        foreach (self::ofKind(self::KIND_GUIDE) as $doc) {
            if ($doc['slug'] === 'index' || ! isset($byLabel[$doc['section'] ?? ''])) {
                continue;
            }
            $byLabel[$doc['section']][] = $doc;
        }

        $sections = [];

        foreach ($byLabel as $label => $docs) {
            foreach (self::SECTION_EXTRAS[$label] ?? [] as $extra) {
                $docs[] = [
                    'kind' => self::KIND_GUIDE,
                    'kindLabel' => self::KIND_LABELS[self::KIND_GUIDE],
                    'slug' => null,
                    'title' => $extra['title'],
                    'lead' => '',
                    'url' => $extra['url'],
                    'section' => $label,
                ];
            }

            $sections[] = [
                'label' => $label,
                'description' => self::SECTIONS[$label],
                'anchor' => self::sectionAnchor($label),
                'items' => self::sortByIndex($docs),
            ];
        }

        return $sections;
    }

    /**
     * One kind's pages in index.md order - the tutorials as the index lists
     * them, not alphabetically.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function ordered(string $kind): array
    {
        return self::sortByIndex(self::ofKind($kind));
    }

    /**
     * Sort documents by where index.md links them.
     *
     * index.md is already the hand-written listing every page must appear in
     * (HelpPageTest enforces the link, HelpTaxonomyTest the heading it sits
     * under), so its order is the one place an author already decides what
     * comes first. Reading that back means "Why Overlabels" can lead Getting
     * started without a new frontmatter key to keep in step. Anything the
     * index does not link sorts last, by title.
     *
     * @param  array<int,array<string,mixed>>  $docs
     * @return array<int,array<string,mixed>>
     */
    public static function sortByIndex(array $docs): array
    {
        $order = self::indexOrder();

        usort($docs, fn (array $a, array $b): int => [
            $order[$a['url']] ?? PHP_INT_MAX, $a['title'],
        ] <=> [
            $order[$b['url']] ?? PHP_INT_MAX, $b['title'],
        ]);

        return $docs;
    }

    /**
     * url => position of its first link in index.md.
     *
     * @return array<string,int>
     */
    private static function indexOrder(): array
    {
        if (self::$indexOrder !== null) {
            return self::$indexOrder;
        }

        $path = HelpPage::path('index');
        $source = $path !== null ? (string) file_get_contents($path) : '';

        preg_match_all('#\]\((/help[^)\s]*)\)#', $source, $matches);

        $order = [];
        foreach ($matches[1] as $position => $url) {
            $order[$url] ??= $position;
        }

        return self::$indexOrder = $order;
    }

    /**
     * The section a guide belongs to, with its neighbours, or null.
     *
     * @return array{label:string,anchor:string,items:array<int,array<string,mixed>>}|null
     */
    public static function sectionOf(string $slug): ?array
    {
        foreach (self::sections() as $section) {
            foreach ($section['items'] as $item) {
                if ($item['slug'] === $slug) {
                    return $section;
                }
            }
        }

        return null;
    }

    /** The landing-page anchor for a section label: `Bot & chat` is `#bot-chat`. */
    public static function sectionAnchor(string $label): string
    {
        return Str::slug($label);
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
        self::$indexOrder = null;
    }
}
