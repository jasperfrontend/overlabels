<?php

namespace App\Http\Controllers;

use App\Support\HelpPage;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders a help page from its markdown source.
 *
 * Every help route resolves through here, so adding a page is adding a file to
 * resources/help/pages - no route, no controller, no Vue component. The card
 * grids at /help and /help/bot stay as their own Vue pages because they are
 * navigation, not prose.
 */
class HelpController extends Controller
{
    public function show(string $slug): Response
    {
        if (! HelpPage::exists($slug)) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('help/Page', HelpPage::render($slug));
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
