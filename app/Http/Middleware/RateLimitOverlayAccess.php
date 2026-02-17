<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitOverlayAccess
{
    public function handle(Request $request, Closure $next)
    {
        $key = 'overlay-access:'.$request->ip();

        // 100 requests per minute per IP
        if (RateLimiter::tooManyAttempts($key, 100)) {
            return response()->json([
                'error' => 'Too many requests',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
