<?php

namespace App\Http\Controllers;

class PageController extends Controller
{
    /**
     * The web `Route::fallback`. `view()` alone answers 200, which made every
     * unknown URL a soft-404: the page said "404 - Not Found" while the status
     * line said success, and status is what machines branch on. Crawlers could
     * not tell a live page from a dead one, and neither could an agent
     * following the "append .md to any help URL" convention in llms.txt.
     */
    public function notfound()
    {
        return response()->view('errors.404', [], 404);
    }

    public function notAuthorized()
    {
        return route('login');
    }
}
