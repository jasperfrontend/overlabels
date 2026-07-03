<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ExternalEventController;
use App\Http\Controllers\TwitchEventSubController;
use App\Models\ExternalEvent;
use App\Models\OverlayAccessToken;
use App\Models\TwitchEvent;
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
 * First consumers of token abilities: reading the feed requires `read`; the
 * writes - the mute toggle and event replay - require `write` (tokens with no
 * abilities set are unrestricted, matching hasAbility(), so existing overlay
 * tokens keep working). Every write lands in the token's access log.
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

    /**
     * Replay a Twitch event as an alert. Same core as the dashboard replay
     * (mute guard, mapping resolution, broadcast + TTS + bot message).
     */
    public function replayTwitch(Request $request, TwitchEvent $twitchEvent): JsonResponse
    {
        $accessToken = $this->resolveToken($request, 'write');
        if ($accessToken instanceof JsonResponse) {
            return $accessToken;
        }

        // 404 (not 403) for foreign events: a token must not be able to probe
        // which event ids exist for other users.
        if ($twitchEvent->user_id !== $accessToken->user_id) {
            return response()->json(['error' => 'Event not found.'], 404);
        }

        $result = app(TwitchEventSubController::class)->replayForUser($accessToken->user, $twitchEvent);

        return $this->replayResponse($request, $accessToken, $result);
    }

    /**
     * Replay an external (Ko-fi, StreamLabs, ...) event as an alert.
     */
    public function replayExternal(Request $request, ExternalEvent $externalEvent): JsonResponse
    {
        $accessToken = $this->resolveToken($request, 'write');
        if ($accessToken instanceof JsonResponse) {
            return $accessToken;
        }

        if ($externalEvent->user_id !== $accessToken->user_id) {
            return response()->json(['error' => 'Event not found.'], 404);
        }

        $result = app(ExternalEventController::class)->replayForUser($accessToken->user, $externalEvent);

        return $this->replayResponse($request, $accessToken, $result);
    }

    /**
     * Map a replay core result to JSON: success and warning (muted) are 200s
     * the feed shows as-is, error (no active mapping) is a 422.
     *
     * @param  array{message: string, type: string}  $result
     */
    private function replayResponse(Request $request, OverlayAccessToken $accessToken, array $result): JsonResponse
    {
        if ($result['type'] === 'success') {
            // Writes leave a trail in the token's access log.
            $accessToken->recordAccess($request->ip(), $request->userAgent(), 'events-feed:replay');
        }

        return response()->json(
            ['message' => $result['message'], 'type' => $result['type']],
            $result['type'] === 'error' ? 422 : 200,
        );
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
