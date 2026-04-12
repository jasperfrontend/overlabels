<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfUnauthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            $loginUrl = route('login').'?redirect_to='.urlencode($request->fullUrl());

            // Inertia XHR requests: use Inertia::location() to trigger a full-page
            // visit to the login URL. This avoids the session('url.intended') poisoning
            // that a normal redirect would cause, while still redirecting the user
            // instead of returning a raw JSON 401.
            if ($request->header('X-Inertia')) {
                return Inertia::location($loginUrl);
            }

            // Non-Inertia XHR / fetch requests get a plain 401.
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect($loginUrl);
        }

        return $next($request);
    }
}
