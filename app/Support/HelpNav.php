<?php

namespace App\Support;

use App\Services\HelpReferenceService;

/**
 * Builds the help sidebar.
 *
 * Deliberately section-aware rather than one list of everything: the reference
 * is 147 entries and belongs in a tag tree, while the prose side is two dozen
 * pages and belongs in a plain list. Search spans the whole corpus (see
 * resources/js/help/main.ts); browsing stays scoped to the section you are in.
 *
 * @phpstan-type NavItem array{title:string,url:string,active:bool}
 * @phpstan-type NavGroup array{label:string,items:array<int,NavItem>,count:int,mono:bool}
 */
final class HelpNav
{
    /**
     * Sidebar for the prose side: tutorials first, then guides, then deep dives.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function docGroups(?string $activeSlug = null): array
    {
        $groups = [];

        foreach ([
            HelpCorpus::KIND_TUTORIAL => 'Tutorials',
            HelpCorpus::KIND_GUIDE => 'Guides',
            HelpCorpus::KIND_DEEP_DIVE => 'Deep dives',
        ] as $kind => $label) {
            $docs = HelpCorpus::ofKind($kind);

            if ($docs === []) {
                continue;
            }

            $groups[] = [
                'label' => $label,
                'count' => count($docs),
                'mono' => false,
                'items' => array_map(fn (array $d): array => [
                    'title' => $d['title'],
                    'url' => $d['url'],
                    'active' => $d['slug'] === $activeSlug,
                ], $docs),
            ];
        }

        return $groups;
    }

    /**
     * Sidebar for the reference: the category tree, monospaced because every
     * entry title is a tag, a field or an event name.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function referenceGroups(?string $activeCategory = null, ?string $activeSlug = null): array
    {
        return array_map(fn (array $group): array => [
            'label' => $group['categoryLabel'],
            'count' => count($group['items']),
            'mono' => true,
            'items' => array_map(fn (array $item): array => [
                'title' => $item['title'],
                'url' => "/help/reference/{$item['category']}/{$item['slug']}",
                'active' => $item['category'] === $activeCategory && $item['slug'] === $activeSlug,
            ], $group['items']),
        ], app(HelpReferenceService::class)->grouped());
    }
}
