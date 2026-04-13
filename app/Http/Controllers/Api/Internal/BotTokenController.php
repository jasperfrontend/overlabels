<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\BotToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BotTokenController extends Controller
{
    private const ACCOUNT = 'overlabels';

    public function show(): JsonResponse
    {
        $token = BotToken::where('account', self::ACCOUNT)->first();

        if (! $token) {
            return response()->json(['error' => 'bot account not initialised'], 404);
        }

        return response()->json([
            'access_token' => $token->access_token,
            'refresh_token' => $token->refresh_token,
            'expires_at' => $token->expires_at,
            'obtained_at' => $token->obtained_at,
            'scopes' => $token->scopes ?? [],
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $request->validate([
            'access_token' => 'required|string',
            'refresh_token' => 'required|string',
            'expires_at' => 'required|integer',
            'obtained_at' => 'required|integer',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string',
        ]);

        BotToken::updateOrCreate(
            ['account' => self::ACCOUNT],
            $data,
        );

        return response()->noContent();
    }
}
