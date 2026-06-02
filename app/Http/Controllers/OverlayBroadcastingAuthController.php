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
 *   - `private-lists.<owner_twitch_id>.<slug>`  (any of the owner's lists)
 *
 * The list channel lets external consumers (a custom wheel page) subscribe
 * to a single List's live updates with just an overlay token. The
 * `<owner_twitch_id>` segment is the security boundary - a token can only
 * ever authorize channels under its own owner's id, so it can never read
 * another user's lists or alerts.
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

        // List-scoped channels: private-lists.<owner_twitch_id>.<slug>. The
        // owner-id segment is fixed to the token's user, so a token can only
        // authorize its own lists; the slug just has to be well-formed (the
        // channel only ever carries broadcasts for lists that actually
        // exist, so an unknown slug subscribes to silence, not data).
        $listPrefix = 'private-lists.'.$twitchId.'.';
        $isOwnListChannel = str_starts_with($channel, $listPrefix)
            && preg_match('/^[a-z][a-z0-9_]{0,49}$/', substr($channel, strlen($listPrefix))) === 1;

        if (! in_array($channel, $allowed, true) && ! $isOwnListChannel) {
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
