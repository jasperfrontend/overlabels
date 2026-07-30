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
        // Inbound machine endpoints only. These carry their own secret/signature
        // checks and are called by Twitch, Ko-fi and friends, so an IP ban must
        // not take webhook delivery down for everyone sharing that egress.
        //
        // `banned` used to be exempt here so the redirect target stayed
        // reachable. There is no redirect any more - a banned requester gets a
        // hard 404 on everything, that page included.
        if ($request->is('api/twitch/webhook', 'api/webhooks/*', 'api/eventsub-health-check')) {
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

    /**
     * A banned requester gets a hard 404 on everything, not a redirect and not
     * a 403. 403 confirms the resource exists; 404 says nothing at all, which
     * is the intended posture. Applies identically to user bans and IP bans.
     */
    private function blocked(Request $request): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->view('errors.404', [], 404);
    }
}
