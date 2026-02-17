<?php

namespace App\Http\Controllers;

use App\Models\OverlayAccessToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class OverlayAccessTokenController extends Controller
{
    /**
     * Display listing of user's tokens
     */
    public function index(Request $request)
    {
        $tokens = $request->user()
            ->overlayAccessTokens()
            ->with('accessLogs')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'prefix' => $token->token_prefix,
                    'is_active' => $token->is_active,
                    'expires_at' => $token->expires_at,
                    'last_used_at' => $token->last_used_at,
                    'access_count' => $token->access_count,
                    'created_at' => $token->created_at,
                    'allowed_ips' => $token->allowed_ips,
                    'abilities' => $token->abilities,
                ];
            });

        return Inertia::render('overlaytokens/index', [
            'tokens' => $tokens,
        ]);
    }

    /**
     * Store a new token
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'expires_at' => 'nullable|date|after:now',
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'ip',
            'abilities' => 'nullable|array',
            'abilities.*' => 'string|in:read,write,delete',
        ]);

        // Generate token
        $tokenData = OverlayAccessToken::generateToken();

        // Create token record
        $token = $request->user()->overlayAccessTokens()->create([
            'name' => $validated['name'],
            'token_hash' => $tokenData['hash'],
            'token_prefix' => $tokenData['prefix'],
            'expires_at' => $validated['expires_at'] ?? null,
            'allowed_ips' => $validated['allowed_ips'] ?? [],
            'abilities' => isset($validated['abilities']) ? implode(',', $validated['abilities']) : null,
        ]);

        Log::info('New overlay access token created', [
            'user_id' => $request->user()->id,
            'token_id' => $token->id,
            'prefix' => $tokenData['prefix'],
        ]);

        return response()->json([
            'token' => $token,
            'plain_token' => $tokenData['plain'], // Only shown once!
            'message' => 'Token created successfully. Please copy it now as it won\'t be shown again.',
        ]);
    }

    /**
     * Revoke a token
     */
    public function revoke(Request $request, OverlayAccessToken $token)
    {
        // Ensure user owns this token
        if ($token->user_id !== $request->user()->id) {
            abort(403);
        }

        $token->update(['is_active' => false]);

        Log::info('Overlay access token revoked', [
            'user_id' => $request->user()->id,
            'token_id' => $token->id,
        ]);

        return response()->json([
            'message' => 'Token revoked successfully',
        ]);
    }

    /**
     * Delete a token
     */
    public function destroy(Request $request, OverlayAccessToken $token)
    {
        // Ensure user owns this token
        if ($token->user_id !== $request->user()->id) {
            abort(403);
        }

        $token->delete();

        Log::info('Overlay access token deleted', [
            'user_id' => $request->user()->id,
            'token_id' => $token->id,
        ]);

        return response()->json([
            'message' => 'Token deleted successfully',
        ]);
    }

    /**
     * Show token usage/logs
     */
    public function usage(Request $request, OverlayAccessToken $token)
    {
        // Ensure user owns this token
        if ($token->user_id !== $request->user()->id) {
            abort(403);
        }

        $logs = $token->accessLogs()
            ->orderBy('accessed_at', 'desc')
            ->take(100)
            ->get();

        return Inertia::render('overlaytokens/usage', [
            'token' => $token,
            'logs' => $logs,
        ]);
    }
}
