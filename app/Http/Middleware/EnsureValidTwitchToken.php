<?php

namespace App\Http\Middleware;

use App\Services\TwitchTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidTwitchToken
{
    protected TwitchTokenService $tokenService;

    public function __construct(TwitchTokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Check and refresh token if needed
        if (!$this->tokenService->ensureValidToken($user)) {
            Log::warning('Failed to ensure valid Twitch token for user', ['user_id' => $user->id]);

            // If we're making an API call, return a specific error
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Token refresh failed',
                    'message' => 'Unable to refresh Twitch authentication. Please re-authenticate.',
                    'requires_reauth' => true
                ], 401);
            }

            // For regular requests, redirect to re-authentication
            return redirect('/auth/redirect/twitch')
                ->with('error', 'Your Twitch session has expired. Please re-authenticate.');
        }

        return $next($request);
    }
}
