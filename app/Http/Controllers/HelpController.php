<?php

namespace App\Http\Controllers;

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

        return response()->view('help.doc', [
            ...$page,
            'markdownUrl' => HelpPage::url($slug).'.md',
            'helpSection' => 'docs',
            'navGroups' => HelpNav::docGroups($slug),
            'pageTitle' => $page['title'],
            'pageDescription' => $page['description'],
            'canonicalUrl' => $page['canonical'],
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
}
