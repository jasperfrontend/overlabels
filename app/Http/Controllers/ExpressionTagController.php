<?php

namespace App\Http\Controllers;

use App\Services\TemplateDataMapperService;
use App\Services\TwitchApiService;
use App\Services\TwitchTokenService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ExpressionTagController extends Controller
{
    public function __construct(
        private readonly TwitchApiService $twitchService,
        private readonly TemplateDataMapperService $mapper,
    ) {}

    /**
     * Resolve the full flat Twitch tag map for the authenticated user.
     * Powers the live-value preview in the expression builder modal so users
     * see real numbers (e.g. followers_total: 1523) instead of the mock 42.
     *
     * Piggybacks on TwitchApiService::getExtendedUserData's cache - so after
     * the first overlay render, this endpoint responds in milliseconds.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $tokenService = app(TwitchTokenService::class);
        if (! $tokenService->ensureValidToken($user)) {
            $user->refresh();
        }

        if (! $user->access_token) {
            return response()->json(['error' => 'User has no Twitch connection'], 400);
        }

        try {
            $twitchData = $this->twitchService->getExtendedUserData(
                $user->access_token,
                $user->twitch_id,
            );

            $tags = $this->mapper->mapForTemplate(
                $twitchData,
                'expression-preview',
                null,
            );

            return response()->json([
                'tags' => $tags,
            ]);
        } catch (Exception $e) {
            Log::warning('ExpressionTagController failed to resolve tags', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to resolve tags'], 500);
        }
    }
}
