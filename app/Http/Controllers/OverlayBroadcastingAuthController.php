<?php

namespace App\Http\Controllers;

use App\Models\OverlayAccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Overlay-token-authenticated counterpart to Laravel's built-in
 * `/broadcasting/auth` route.
 *
 * Overlays are session-less browser sources, so the standard endpoint
 * (which depends on the auth guard) can't sign their subscriptions.
 * This endpoint accepts a 64-hex `OverlayAccessToken` instead and returns
 * the Pusher-protocol auth signature Reverb expects, but only for the two
 * private channels overlays legitimately need:
 *
 *   - `private-alerts.<owner_twitch_id>`
 *   - `private-twitch-events.<owner_twitch_id>`
 *
 * Anything else (different user, presence channel, gamejam, etc.) is
 * rejected with 403 before any signature is produced.
 */
class OverlayBroadcastingAuthController extends Controller
{
    public function authenticate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => 'required|string',
            'token' => 'required|string|size:64',
            'socket_id' => 'required|string',
            'channel_name' => 'required|string',
        ]);

        if (! ctype_xdigit($validated['token'])) {
            return response()->json(['error' => 'Invalid token.'], 401);
        }

        $token = OverlayAccessToken::findByToken($validated['token'], $request->ip());
        if (! $token || ! $token->user) {
            return response()->json(['error' => 'Invalid token.'], 401);
        }

        $twitchId = (string) $token->user->twitch_id;
        $channel = $validated['channel_name'];

        $allowed = [
            'private-alerts.'.$twitchId,
            'private-twitch-events.'.$twitchId,
        ];

        if (! in_array($channel, $allowed, true)) {
            return response()->json(['error' => 'Channel not permitted for this token.'], 403);
        }

        $secret = (string) config('broadcasting.connections.reverb.secret');
        $key = (string) config('broadcasting.connections.reverb.key');

        if ($secret === '' || $key === '') {
            return response()->json(['error' => 'Broadcasting is not configured.'], 500);
        }

        $stringToSign = $validated['socket_id'].':'.$channel;
        $signature = hash_hmac('sha256', $stringToSign, $secret);

        return response()->json([
            'auth' => $key.':'.$signature,
        ]);
    }
}
