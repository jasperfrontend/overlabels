<?php

namespace App\Http\Controllers;

use App\Services\HelpReferenceService;
use App\Services\OgImageService;
use App\Support\HelpNav;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class HelpReferenceController extends Controller
{
    public function __construct(
        private readonly HelpReferenceService $service,
        private readonly OgImageService $og,
    ) {}

    public function show(Request $request, ?string $category = null, ?string $slug = null): SymfonyResponse
    {
        // This route is intentionally NOT an Inertia response - it's a plain
        // Blade page so Google can read it. If Inertia's client made the
        // request (X-Inertia header set), tell it to do a hard reload instead
        // of trying to parse the HTML as an Inertia payload.
        if ($request->header('X-Inertia')) {
            return response('', 409)->header('X-Inertia-Location', $request->fullUrl());
        }

        $entry = null;
        $renderedBody = null;
        $tagSnippet = null;

        if ($category !== null && $slug !== null) {
            $entry = $this->service->get($category, $slug);
            if ($entry === null) {
                abort(404);
            }
            $renderedBody = $this->service->render($entry['body']);
            $tagSnippet = $this->buildTagSnippet($entry['category'], $entry['slug']);
        }

        $canonicalUrl = 'https://overlabels.com/help/reference'
            .($entry ? "/{$entry['category']}/{$entry['slug']}" : '');

        $totalCount = count($this->service->all());

        $ogImage = $entry
            ? $this->og->urlFor($entry, $canonicalUrl)
            : $this->og->urlForIndex($totalCount, $canonicalUrl);

        return response()->view('help.reference', [
            'groups' => $this->service->grouped(),
            'helpSection' => 'reference',
            'navGroups' => HelpNav::referenceGroups($entry['category'] ?? null, $entry['slug'] ?? null),
            'totalCount' => $totalCount,
            'entry' => $entry,
            'renderedBody' => $renderedBody,
            'tagSnippet' => $tagSnippet,
            'pageTitle' => $entry
                ? "{$entry['title']} - Reference - Overlabels"
                : 'Reference - Overlabels',
            'pageDescription' => $entry
                ? mb_substr(preg_replace('/\s+/', ' ', $entry['body']) ?? '', 0, 180)
                : 'Searchable reference for every Overlabels template tag, EventSub event, and foreach loop field.',
            'canonicalUrl' => $canonicalUrl,
            'ogImage' => $ogImage,
        ]);
    }

    /**
     * The entry's source file, verbatim, as text/markdown. Served from the
     * path rather than the cached body so it is byte-identical to the file on
     * disk, the same guarantee HelpController::markdown() makes for prose.
     */
    public function markdown(string $category, string $slug): Response
    {
        $entry = $this->service->get($category, $slug);

        if ($entry === null) {
            abort(404);
        }

        return response(
            (string) file_get_contents($entry['path']),
            200,
            ['Content-Type' => 'text/markdown; charset=utf-8']
        );
    }

    /**
     * Match the JS-side tagSnippet logic from Reference.vue: skip aggregate
     * `all-*` slugs and only emit a snippet for categories where slug-to-tag
     * is 1:1.
     */
    private function buildTagSnippet(string $category, string $slug): ?array
    {
        if (str_starts_with($slug, 'all-')) {
            return null;
        }

        if ($category === 'template-tags') {
            return [
                'label' => 'Tag',
                'code' => "[[[{$slug}]]]",
            ];
        }

        if ($category === 'foreach-loops') {
            $parts = explode('.', $slug);
            $alias = preg_replace('/s$/', '', end($parts) ?: 'item') ?: 'item';

            return [
                'label' => 'Loop',
                'code' => "[[[foreach:{$slug} as {$alias}]]]\n  [[[{$alias}.id]]]\n[[[endforeach]]]",
            ];
        }

        return null;
    }
}
