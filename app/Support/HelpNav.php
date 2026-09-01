<?php

namespace App\Support;

use App\Services\HelpReferenceService;

/**
 * Builds the help sidebar tree.
 *
 * One shape for both corpora, rendered by resources/views/help/_tree.blade.php:
 * a list of groups, each with a label, a count, an optional url, whether it
 * starts open, and either items or sub-sections that carry items. The prose
 * side has three kinds plus a link to the reference; the reference side is its
 * category tree. Search spans the whole corpus (see resources/js/help/main.ts);
 * browsing stays scoped to the section you are in.
 *
 * Groups are `<details>` elements, so "open" is decided here, on the server,
 * and needs no script: the branch holding the current page is expanded and
 * everything else is collapsed to one row with a count.
 *
 * @phpstan-type NavItem array{title:string,url:string,active:bool}
 * @phpstan-type NavSection array{label:string,url:string,open:bool,items:array<int,NavItem>}
 * @phpstan-type NavGroup array{label:string,url:?string,count:int,open:bool,mono:bool,items:array<int,NavItem>,sections:array<int,NavSection>}
 */
final class HelpNav
{
    /**
     * Sidebar for the prose side: tutorials, guides by section, deep dives,
     * then the reference as a single row.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function docGroups(?string $activeSlug = null): array
    {
        $activeKind = $activeSlug !== null ? HelpCorpus::kindOf($activeSlug) : null;
        $activeSection = $activeSlug !== null ? HelpCorpus::sectionOf($activeSlug) : null;

        $items = fn (array $docs): array => array_map(fn (array $d): array => [
            'title' => $d['title'],
            'url' => $d['url'],
            'active' => $d['slug'] === $activeSlug,
        ], $docs);

        $groups = [];

        $tutorials = HelpCorpus::ordered(HelpCorpus::KIND_TUTORIAL);
        if ($tutorials !== []) {
            $groups[] = self::group('Tutorials', '/help#tutorials', count($tutorials), $activeKind === HelpCorpus::KIND_TUTORIAL, $items($tutorials));
        }

        $sections = [];
        $guideCount = 0;
        foreach (HelpCorpus::sections() as $section) {
            $guideCount += count($section['items']);
            $sections[] = [
                'label' => $section['label'],
                'url' => '/help#'.$section['anchor'],
                'open' => $activeSection !== null && $activeSection['label'] === $section['label'],
                'items' => $items($section['items']),
            ];
        }
        $groups[] = self::group('Guides', '/help#guides', $guideCount, $activeKind === HelpCorpus::KIND_GUIDE, [], $sections);

        $deepDives = HelpCorpus::ordered(HelpCorpus::KIND_DEEP_DIVE);
        if ($deepDives !== []) {
            $groups[] = self::group('Deep dives', '/help#deep-dives', count($deepDives), $activeKind === HelpCorpus::KIND_DEEP_DIVE, $items($deepDives));
        }

        $groups[] = self::group('Reference', '/help/reference', count(app(HelpReferenceService::class)->all()), false, []);

        return $groups;
    }

    /**
     * Sidebar for the reference: the category tree, monospaced because every
     * entry title is a tag, a field or an event name. Only the category holding
     * the current entry starts open - 147 entries as one flat list was the
     * sidebar this replaces.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function referenceGroups(?string $activeCategory = null, ?string $activeSlug = null): array
    {
        return array_map(fn (array $group): array => self::group(
            $group['categoryLabel'],
            null,
            count($group['items']),
            $group['category'] === $activeCategory,
            array_map(fn (array $item): array => [
                'title' => $item['title'],
                'url' => "/help/reference/{$item['category']}/{$item['slug']}",
                'active' => $item['category'] === $activeCategory && $item['slug'] === $activeSlug,
            ], $group['items']),
            [],
            mono: true,
        ), app(HelpReferenceService::class)->grouped());
    }

    /**
     * @param  array<int,array<string,mixed>>  $items
     * @param  array<int,array<string,mixed>>  $sections
     * @return array<string,mixed>
     */
    private static function group(string $label, ?string $url, int $count, bool $open, array $items, array $sections = [], bool $mono = false): array
    {
        return [
            'label' => $label,
            'url' => $url,
            'count' => $count,
            'open' => $open,
            'mono' => $mono,
            'items' => $items,
            'sections' => $sections,
        ];
    }
}
