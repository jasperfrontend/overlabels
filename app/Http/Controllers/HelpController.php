<?php

namespace App\Http\Controllers;

use App\Support\HelpPage;
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
}
