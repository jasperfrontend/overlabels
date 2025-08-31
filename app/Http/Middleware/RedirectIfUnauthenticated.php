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
        if (!Auth::check()) {
            // Store the intended URL for redirect after authentication
            $intendedUrl = $request->fullUrl();
            
            // Build login URL with redirect parameter
            $loginUrl = route('login') . '?redirect_to=' . urlencode($intendedUrl);
            
            return redirect($loginUrl);
        }

        return $next($request);
    }
}
