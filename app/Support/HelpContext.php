<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Answers "which help pages are relevant to where the user is standing?"
 *
 * The association is declared by the help page itself, in a flat `context:`
 * frontmatter line listing the route names it covers:
 *
 *   context: templates.index?type=block, templates.blocks.library, builder.create
 *
 * It lives in the markdown rather than in a central map for the same reason the
 * prose does: writing the page wires it up, so there is no second file to keep
 * in step. Reading blocks.md tells you where it surfaces. The index here is the
 * inverse - route name to pages - built by scanning frontmatter.
 *
 * Route NAMES are the key, not URLs. They are already unique, they survive a
 * URL being rewritten, and `$request->route()->getName()` hands one over for
 * free. Query constraints narrow a name down to a state, because /templates is
 * one route serving four filter states.
 *
 * Matching is deliberately dull. Nothing here guesses: a page is offered
 * because it said it belonged, or it is not offered at all. A wrong contextual
 * link is worse than no link, because it teaches people the help button lies.
 */
final class HelpContext
{
    /** Where controller-supplied context rides, per request. */
    private const string ATTRIBUTE = 'help_context';

    /** @var array<string,array<int,array{slug:string,pattern:string,constraints:array<string,string>}>>|null */
    private static ?array $index = null;

    /** @var array<string,array{title:string,lead:string}> Slug to card copy, filled while indexing. */
    private static array $cards = [];

    /**
     * Add context the URL does not carry.
     *
     * /templates/{template} is one route serving blocks, alerts and static
     * overlays - the discriminator is the model, not the query string. A
     * controller that knows better says so:
     *
     *   HelpContext::add(['type' => $template->type]);
     *
     * and `context: templates.show?type=alert` then matches through the exact
     * same code path as a real query parameter. One matcher, and the controller
     * decides what the meaningful discriminator is.
     *
     * @param  array<string,mixed>  $params
     */
    public static function add(array $params): void
    {
        $request = request();

        $request->attributes->set(self::ATTRIBUTE, [
            ...$request->attributes->get(self::ATTRIBUTE, []),
            ...$params,
        ]);
    }

    /**
     * Resolve help pages for the current request.
     *
     * @return array<int,array{slug:string,title:string,url:string}>
     */
    public static function forRequest(Request $request): array
    {
        $name = $request->route()?->getName();

        if ($name === null) {
            return [];
        }

        return self::for($name, [
            ...$request->query(),
            ...$request->attributes->get(self::ATTRIBUTE, []),
        ]);
    }

    /**
     * Resolve help pages for a route name and a bag of context parameters.
     *
     * Every match is returned, best first, because scoring has to happen to
     * pick a winner anyway and collapsing to one here would throw away a
     * decision the frontend has not made yet.
     *
     * @param  array<string,mixed>  $params
     * @return array<int,array{slug:string,title:string,url:string}>
     */
    public static function for(string $routeName, array $params = []): array
    {
        $matches = [];

        foreach (self::index() as $pattern => $entries) {
            $exact = $pattern === $routeName;

            if (! $exact && ! (str_contains($pattern, '*') && Str::is($pattern, $routeName))) {
                continue;
            }

            foreach ($entries as $entry) {
                if (! self::satisfies($entry['constraints'], $params)) {
                    continue;
                }

                $candidate = [
                    'slug' => $entry['slug'],
                    'exact' => $exact,
                    'constraints' => count($entry['constraints']),
                    // A longer literal prefix is a more deliberate wildcard:
                    // settings.bot.expressions.* beats settings.bot.*
                    'literal' => strlen(str_replace('*', '', $pattern)),
                ];

                // A page may declare several contexts that all match at once
                // (a bare route name plus a narrowed one). It earns its best
                // score, not one entry per declaration.
                $existing = $matches[$entry['slug']] ?? null;

                if ($existing === null || self::compare($candidate, $existing) < 0) {
                    $matches[$entry['slug']] = $candidate;
                }
            }
        }

        uasort($matches, self::compare(...));

        return array_values(array_map(
            fn (array $match): array => [
                'slug' => $match['slug'],
                'title' => self::$cards[$match['slug']]['title'] ?? Str::headline($match['slug']),
                'lead' => self::$cards[$match['slug']]['lead'] ?? '',
                'url' => HelpPage::url($match['slug']),
            ],
            $matches
        ));
    }

    /**
     * Rank two candidates. Negative means the first one wins.
     *
     * Exactness first: naming the route outright is a stronger signal than a
     * wildcard that happens to cover it. Then the number of constraints the
     * page pinned down, then how literal its pattern was, then the slug so the
     * order is stable and testable. There is deliberately no `priority:` key -
     * a page cannot buy rank, it earns it by being more specific.
     *
     * @param  array{slug:string,exact:bool,constraints:int,literal:int}  $a
     * @param  array{slug:string,exact:bool,constraints:int,literal:int}  $b
     */
    private static function compare(array $a, array $b): int
    {
        return [$b['exact'], $b['constraints'], $b['literal'], $a['slug']]
            <=> [$a['exact'], $a['constraints'], $a['literal'], $b['slug']];
    }

    /**
     * Declared constraints must all be present and equal. Anything undeclared
     * is ignored, so `templates.index?type=block` still matches a URL carrying
     * search, sort and pagination - and no allowlist of "meaningful" parameters
     * has to be maintained anywhere.
     *
     * @param  array<string,string>  $constraints
     * @param  array<string,mixed>  $params
     */
    private static function satisfies(array $constraints, array $params): bool
    {
        foreach ($constraints as $key => $value) {
            $actual = $params[$key] ?? null;

            if (! is_scalar($actual) || (string) $actual !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Route pattern to the pages claiming it.
     *
     * @return array<string,array<int,array{slug:string,pattern:string,constraints:array<string,string>}>>
     */
    public static function index(): array
    {
        if (self::$index !== null) {
            return self::$index;
        }

        $index = [];
        self::$cards = [];

        foreach (HelpPage::all() as $slug) {
            $meta = HelpPage::meta($slug);

            self::$cards[$slug] = [
                // `heading` is the page's own short name ("Blocks"); `title` is
                // written for a browser tab and search results ("Blocks -
                // reusable building pieces for the Builder"), which is too long
                // for a 375px panel. Prefer the heading, fall back to the title.
                'title' => $meta['heading'] ?? $meta['title'] ?? Str::headline($slug),
                // The lead is already the page's opening paragraph, authored to
                // introduce it. That makes it the excerpt, at no render cost -
                // no markdown pass, no body read, nothing to keep in sync.
                'lead' => $meta['lead'] ?? $meta['description'] ?? '',
            ];

            foreach (self::parse($slug, $meta['context'] ?? '') as $entry) {
                $index[$entry['pattern']][] = $entry;
            }
        }

        return self::$index = $index;
    }

    /**
     * Parse one page's `context:` line.
     *
     * @return array<int,array{slug:string,pattern:string,constraints:array<string,string>}>
     */
    public static function declared(string $slug): array
    {
        return self::parse($slug, HelpPage::meta($slug)['context'] ?? '');
    }

    /**
     * @return array<int,array{slug:string,pattern:string,constraints:array<string,string>}>
     */
    private static function parse(string $slug, string $line): array
    {
        $line = trim($line);

        if ($line === '') {
            return [];
        }

        $entries = [];

        foreach (array_filter(array_map('trim', explode(',', $line))) as $context) {
            [$pattern, $query] = array_pad(explode('?', $context, 2), 2, '');

            parse_str($query, $constraints);

            $entries[] = [
                'slug' => $slug,
                'pattern' => $pattern,
                'constraints' => array_map(fn ($value): string => (string) $value, $constraints),
            ];
        }

        return $entries;
    }

    /** Drop the memoised index. For tests that write help pages at runtime. */
    public static function flush(): void
    {
        self::$index = null;
        self::$titles = [];
    }
}
