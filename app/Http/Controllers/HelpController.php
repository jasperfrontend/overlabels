<?php

namespace App\Http\Controllers;

use App\Services\HelpReferenceService;
use App\Services\OgImageService;
use App\Support\HelpCorpus;
use App\Support\HelpNav;
use App\Support\HelpPage;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders a help page from its markdown source.
 *
 * Every help route resolves through here, so adding a page is adding a file to
 * resources/help/pages - no route, no controller, no Vue component. A page in
 * the `tutorials/` subdirectory becomes a tutorial at /help/tutorials/<slug>
 * with no extra wiring, the same way `bot/` pages already worked.
 *
 * These are plain Blade pages, not Inertia. Documentation that a crawler cannot
 * read is documentation that only existing users can find, and the reference
 * half of this section was already server-rendered for exactly that reason -
 * the prose half was the odd one out, serving ~27KB of shell and no content.
 */
class HelpController extends Controller
{
    /** How many sibling pages the "Related docs" row offers. */
    private const int RELATED_LIMIT = 3;

    public function __construct(
        private readonly OgImageService $og,
    ) {}

    public function show(Request $request, string $slug): SymfonyResponse
    {
        if (! HelpPage::exists($slug)) {
            throw new NotFoundHttpException;
        }

        // Not an Inertia response. If Inertia's client made the request (an
        // in-app <Link> to /help), tell it to do a hard reload rather than try
        // to parse HTML as an Inertia payload. HelpReferenceController has done
        // this since the reference went server-rendered.
        if ($request->header('X-Inertia')) {
            return response('', 409)->header('X-Inertia-Location', $request->fullUrl());
        }

        $page = HelpPage::render($slug);

        $shared = [
            'pageTitle' => $page['title'],
            'pageDescription' => $page['description'],
            'canonicalUrl' => $page['canonical'],
            // Pre-rendered on deploy by og:generate; this is a cache hit unless
            // the frontmatter changed since. The layout falls back to the
            // generic image when the render fails.
            'ogImage' => $this->og->urlForPage($page, $page['canonical']),
        ];

        // The root index is the landing page: a derived listing of the whole
        // corpus, not a rendering of index.md. The markdown file still carries
        // the hand-written listing for /help.md, the crawl entry point, and
        // HelpTaxonomyTest keeps the two in step.
        if ($slug === 'index') {
            return response()->view('help.landing', [
                ...$shared,
                ...$page,
                'helpSection' => 'landing',
                'tutorials' => HelpCorpus::ordered(HelpCorpus::KIND_TUTORIAL),
                'deepDives' => HelpCorpus::ordered(HelpCorpus::KIND_DEEP_DIVE),
                'sections' => HelpCorpus::sections(),
                'referenceCount' => count(app(HelpReferenceService::class)->all()),
            ]);
        }

        [$prev, $next, $related, $group] = $this->neighbours($slug);

        return response()->view('help.doc', [
            ...$shared,
            ...$page,
            'markdownUrl' => HelpPage::url($slug).'.md',
            'helpSection' => 'docs',
            'navGroups' => HelpNav::docGroups($slug),
            'group' => $group,
            'prev' => $prev,
            'next' => $next,
            'related' => $related,
        ]);
    }

    /**
     * The same page as plain markdown, at /help/<slug>.md
     *
     * This is the machine-readable rail: the old hand-written .vue help pages
     * served ~27KB of <head> and zero prose to anything that fetched them, so
     * llms.txt could only ever link to a dead end. Serving the source file
     * verbatim means there is nothing to build and nothing to drift - what a
     * machine reads is byte-identical to what the site renders.
     */
    public function markdown(string $slug): HttpResponse
    {
        $path = HelpPage::path($slug);

        if ($path === null) {
            throw new NotFoundHttpException;
        }

        return response(
            (string) file_get_contents($path),
            200,
            ['Content-Type' => 'text/markdown; charset=utf-8']
        );
    }

    /**
     * Previous, next and related pages, plus the group that defines "nearby".
     *
     * A guide's group is its section; a tutorial's or deep dive's is its kind.
     * Previous and next walk that group in order, and related is the rest of
     * it, up to RELATED_LIMIT. Nothing here is authored: a page's neighbours
     * are whatever sits beside it in the taxonomy, so adding a page to a
     * section links it from every page already there.
     *
     * @return array{0:?array<string,mixed>,1:?array<string,mixed>,2:array<int,array<string,mixed>>,3:array{label:string,url:string}}
     */
    private function neighbours(string $slug): array
    {
        $kind = HelpCorpus::kindOf($slug);

        if ($kind === HelpCorpus::KIND_GUIDE) {
            $section = HelpCorpus::sectionOf($slug);
            $docs = $section['items'] ?? [];
            $group = [
                'label' => $section['label'] ?? HelpCorpus::KIND_LABELS[$kind].'s',
                'url' => '/help#'.($section['anchor'] ?? 'guides'),
            ];
        } else {
            $docs = HelpCorpus::ordered($kind);
            $group = [
                'label' => $kind === HelpCorpus::KIND_TUTORIAL ? 'Tutorials' : 'Deep dives',
                'url' => '/help#'.($kind === HelpCorpus::KIND_TUTORIAL ? 'tutorials' : 'deep-dives'),
            ];
        }

        $index = null;
        foreach ($docs as $i => $doc) {
            if ($doc['slug'] === $slug) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            return [null, null, [], $group];
        }

        $prev = $docs[$index - 1] ?? null;
        $next = $docs[$index + 1] ?? null;

        $related = array_values(array_filter(
            $docs,
            fn (array $d, int $i): bool => $i !== $index && $i !== $index - 1 && $i !== $index + 1,
            ARRAY_FILTER_USE_BOTH,
        ));

        return [$prev, $next, array_slice($related, 0, self::RELATED_LIMIT), $group];
    }
}
