<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response
    {
        if ($request->has('redirect_to')) {
            $request->session()->put('url.intended', $request->query('redirect_to'));
        }

        return Inertia::render('auth/Login', [
            'status' => $request->session()->get('status'),
            'redirectTo' => $request->query('redirect_to'),
        ]);
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
