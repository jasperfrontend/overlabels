<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyBotListenerSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.twitchbot.listener_secret');
        $provided = (string) $request->header('X-Internal-Secret', '');

        if (empty($secret) || ! hash_equals($secret, $provided)) {
            abort(403);
        }

        return $next($request);
    }
}
