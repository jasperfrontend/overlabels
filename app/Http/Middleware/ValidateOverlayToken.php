<?php

namespace App\Http\Middleware;

use App\Models\OverlayAccessToken;
use Closure;
use Illuminate\Http\Request;

class ValidateOverlayToken
{
    public function handle(Request $request, Closure $next, ...$abilities)
    {
        $token = $request->bearerToken() ?? $request->input('token');

        if (! $token) {
            return response()->json(['error' => 'No token provided'], 401);
        }

        $accessToken = OverlayAccessToken::findByToken($token, $request->ip());

        if (! $accessToken) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        // Check abilities if specified
        foreach ($abilities as $ability) {
            if (! $accessToken->hasAbility($ability)) {
                return response()->json(['error' => 'Insufficient permissions'], 403);
            }
        }

        // Attach token and user to the request
        $request->merge(['overlay_token' => $accessToken]);
        $request->setUserResolver(function () use ($accessToken) {
            return $accessToken->user;
        });

        return $next($request);
    }
}
