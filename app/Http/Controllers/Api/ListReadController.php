<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OptionSet;
use App\Models\OverlayAccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public, read-only JSON view of a List, for consumers that live OUTSIDE an
 * Overlabels overlay - a custom wheel page, a leaderboard web component, a
 * Bun script. Overlay templates strip all JavaScript (HtmlSanitizationService),
 * so they cannot parse list data themselves; rich rendering happens in an
 * external page that fetches this endpoint where JS is allowed.
 *
 * Authenticated by the same OverlayAccessToken overlays use, passed as a query
 * param so it drops cleanly into a browser-source URL. The token resolves to
 * its owner; you only ever read your own lists. Returns the full item objects
 * ({id,value,added_at,label,weight,color}).
 *
 * This is the REST bootstrap rail from docs/design/lists-data-bus.md (§6):
 * "GET current state, then (later) subscribe to changes." The live diff layer
 * (ListMutated over a per-list channel) is a separate, future addition; this
 * endpoint stands alone - poll it, or render once.
 *
 * Cross-origin by design: it lives under /api/* so Laravel's default CORS
 * covers it. A web.php route would be CORS-blocked for an external browser
 * fetch, and would be dragged into Sanctum's stateful-session handling.
 *
 * Consistent with "overlays never phone home": the overlay itself never calls
 * this (it has no JS and could not); only an external consumer the streamer
 * controls does, GET-only, server-to-client.
 */
class ListReadController extends Controller
{
    public function show(Request $request, string $slug): JsonResponse
    {
        $token = (string) $request->query('token', '');
        if (strlen($token) !== 64) {
            return response()->json([
                'error' => 'A valid 64-character overlay token is required as the `token` query parameter.',
            ], 401);
        }

        $accessToken = OverlayAccessToken::findByToken($token, $request->ip());
        if (! $accessToken) {
            return response()->json(['error' => 'Invalid or expired token.'], 401);
        }

        // Slugs are unique per user, not globally - scope the lookup to the
        // token's owner so a token can never read another user's lists.
        /** @var OptionSet|null $list */
        $list = OptionSet::where('user_id', $accessToken->user_id)
            ->where('slug', $slug)
            ->first();

        if (! $list) {
            return response()->json(['error' => "No list named '{$slug}'."], 404);
        }

        $items = array_values($list->items ?? []);

        return response()->json([
            'slug' => $list->slug,
            'label' => $list->label,
            'count' => count($items),
            'items' => $items,
            'disabled_at' => $list->disabled_at?->timestamp,
            'expires_at' => $list->expires_at?->timestamp,
            'entry_ttl_seconds' => $list->entry_ttl_seconds,
            'updated_at' => $list->updated_at?->timestamp,
            'ts' => now()->timestamp,
            // Everything a consumer needs to ALSO subscribe to live updates:
            // the per-list private channel, the public Reverb connection
            // params, the token-auth endpoint, and the event name. Fetch
            // state once, then subscribe - no polling. (key/host/port are the
            // browser-public Pusher params; the secret is never exposed.)
            'realtime' => [
                'channel' => 'lists.'.$accessToken->user->twitch_id.'.'.$list->slug,
                'event' => 'list.updated',
                'auth_endpoint' => url('/api/overlay/broadcasting/auth'),
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => config('broadcasting.connections.reverb.options.host'),
                'port' => config('broadcasting.connections.reverb.options.port'),
                'scheme' => config('broadcasting.connections.reverb.options.scheme'),
            ],
        ]);
    }
}
