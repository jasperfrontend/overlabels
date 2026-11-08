<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Recipes\RecipeCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request, RecipeCatalog $catalog): Response
    {
        if ($request->has('redirect_to')) {
            $request->session()->put('url.intended', $request->query('redirect_to'));
        }

        return Inertia::render('auth/Login', [
            'status' => $request->session()->get('status'),
            'redirectTo' => $request->query('redirect_to'),
            'installing' => $this->installingProduct($request, $catalog),
        ]);
    }

    /**
     * The product a logged-out visitor was about to install, when the login
     * page was reached from its install page, so the page can tell them why
     * it wants their Twitch account. Only our own /products/{slug}/install
     * for a listed product counts, and the name comes from the catalogue,
     * never from the URL: a crafted link cannot put its own words on the page.
     *
     * @return array{slug: string, name: string}|null
     */
    private function installingProduct(Request $request, RecipeCatalog $catalog): ?array
    {
        $redirectTo = $request->query('redirect_to');
        if (! is_string($redirectTo)) {
            return null;
        }

        $parts = parse_url($redirectTo);
        if ($parts === false) {
            return null;
        }

        $host = $parts['host'] ?? null;
        if ($host !== null && $host !== $request->getHost()) {
            return null;
        }

        if (! preg_match('#^/products/([a-z0-9-]+)/install$#', $parts['path'] ?? '', $m)) {
            return null;
        }

        $product = collect($catalog->listed())->firstWhere('slug', $m[1]);

        return $product ? ['slug' => $product['slug'], 'name' => $product['name']] : null;
    }

    // Inertia::location, not redirect(). Logout is an Inertia XHR whose target
    // is '/' - a plain Blade view with no x-inertia header. A 302 would be
    // followed by the XHR itself, landing Inertia on a full HTML document it
    // cannot parse. Inertia::location answers the XHR with 409 +
    // X-Inertia-Location so the client performs a real navigation, which is
    // what a session teardown wants anyway: a clean document, no stale props.
    // Non-Inertia callers still get an ordinary redirect.
    public function destroy(Request $request): SymfonyResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Inertia::location(route('home'));
    }
}
