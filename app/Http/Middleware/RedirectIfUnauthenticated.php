<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfUnauthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            // Non-navigational requests (fetch, XHR, Inertia) get a 401 — not a redirect.
            // Redirecting them into the login flow stores their URL as session('url.intended')
            // which then poisons redirect()->intended() after OAuth, landing the user on a JSON endpoint.
            if ($request->expectsJson() || $request->header('X-Inertia')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            $loginUrl = route('login').'?redirect_to='.urlencode($request->fullUrl());

            return redirect($loginUrl);
        }

        return $next($request);
    }
}
