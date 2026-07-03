<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OverlayAccessToken;
use App\Services\AlertMuteService;
use App\Services\UnifiedEventFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Token-authed events feed, the phone-friendly sibling of /dashboard/events.
 * Authenticated by the same OverlayAccessToken overlays use, so a streamer who
 * is not logged in to Twitch on their phone can still watch their events and
 * mute their alerts. Serves /events/feed, which reads the token from the URL
 * fragment client-side (the fragment never reaches the server).
 *
 * First consumers of token abilities: reading the feed requires `read`, the
 * mute toggle requires `write` (tokens with no abilities set are unrestricted,
 * matching hasAbility(), so existing overlay tokens keep working). The mute
 * toggle is deliberately the only write an overlay token can perform.
 */
class EventFeedController extends Controller
{
    public function index(Request $request, UnifiedEventFeedService $eventFeed, AlertMuteService $alertMute): JsonResponse
    {
        $accessToken = $this->resolveToken($request, 'read');
        if ($accessToken instanceof JsonResponse) {
            return $accessToken;
        }

        $filters = $eventFeed->normalizeFilters($request);
        $paginator = $eventFeed->paginate($accessToken->user_id, $filters, 25);
        $user = $accessToken->user;

        // Lean paginator shape: no links array, so the token in the request
        // query is never echoed back inside pagination URLs.
        return response()->json([
            'events' => [
                'data' => $paginator->items(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => $filters,
            'facets' => $eventFeed->facets($accessToken->user_id),
            'alerts_muted' => $alertMute->isMuted($user),
            // For subscribing to the owner's private channels via the overlay
            // broadcasting auth endpoint, which only ever signs the token
            // owner's own channels.
            'twitch_id' => $user->twitch_id,
            'ts' => now()->timestamp,
        ]);
    }

    public function mute(Request $request, AlertMuteService $alertMute): JsonResponse
    {
        $accessToken = $this->resolveToken($request, 'write');
        if ($accessToken instanceof JsonResponse) {
            return $accessToken;
        }

        $validated = $request->validate([
            'muted' => 'required|boolean',
        ]);

        $muted = $alertMute->setMuted($accessToken->user, (bool) $validated['muted']);

        // Writes leave a trail in the token's access log.
        $accessToken->recordAccess($request->ip(), $request->userAgent(), 'events-feed:mute');

        return response()->json(['alerts_muted' => $muted]);
    }

    private function resolveToken(Request $request, string $ability): OverlayAccessToken|JsonResponse
    {
        $token = (string) $request->input('token', '');
        if (strlen($token) !== 64) {
            return response()->json([
                'error' => 'A valid 64-character overlay token is required.',
            ], 401);
        }

        $accessToken = OverlayAccessToken::findByToken($token, $request->ip());
        if (! $accessToken || ! $accessToken->user) {
            return response()->json(['error' => 'Invalid or expired token.'], 401);
        }

        if (! $accessToken->hasAbility($ability)) {
            return response()->json(['error' => "This token does not have the '$ability' ability."], 403);
        }

        return $accessToken;
    }
}
