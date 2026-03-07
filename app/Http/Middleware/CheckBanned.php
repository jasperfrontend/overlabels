<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mchev\Banhammer\IP;
use Symfony\Component\HttpFoundation\Response;

class CheckBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        // Don't block the banned page or webhook endpoints
        if ($request->is('banned', 'api/twitch/webhook', 'api/webhooks/*', 'api/eventsub-health-check')) {
            return $next($request);
        }

        // Admins are never blocked (defense-in-depth)
        if ($request->user()?->isAdmin()) {
            return $next($request);
        }

        // Check IP ban (covers both guests and authenticated users)
        if ($request->ip() && IP::isBanned($request->ip())) {
            return $this->blocked($request);
        }

        // Check user ban
        if ($request->user()?->isBanned()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $this->blocked($request);
        }

        return $next($request);
    }

    private function blocked(Request $request): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        return redirect('/banned');
    }
}
