<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExternalIntegration;
use App\Models\User;
use App\Services\GpsLivenessService;
use App\Services\RouteSimplifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GpsSessionMapController extends Controller
{
    public function __construct(private GpsLivenessService $liveness) {}

    /**
     * GET /api/gps-sessions/{sessionId}/geojson
     * Authenticated - for the GPS Sessions dashboard page.
     */
    public function authenticatedGeoJson(string $sessionId): JsonResponse
    {
        $userId = auth()->id();

        return $this->buildGeoJson($userId, $sessionId);
    }

    /**
     * GET /api/map/{twitchId}/{sessionId}/geojson
     * Public - checks map_sharing_enabled.
     */
    public function publicSessionGeoJson(string $twitchId, string $sessionId): JsonResponse
    {
        $user = User::where('twitch_id', $twitchId)->first();

        if (! $user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        if (! $this->isMapSharingEnabled($user->id)) {
            return response()->json(['error' => 'Map sharing is not enabled.'], 403);
        }

        return $this->buildGeoJson($user->id, $sessionId);
    }

    /**
     * GET /api/map/{twitchId}/position
     * Public - returns current (or delayed) GPS position.
     */
    public function currentPosition(string $twitchId): JsonResponse
    {
        $user = User::where('twitch_id', $twitchId)->first();

        if (! $user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'overlabels-mobile')
            ->where('enabled', true)
            ->first();

        if (! $integration) {
            return response()->json(['error' => 'Integration not found.'], 404);
        }

        $settings = $integration->settings ?? [];
        if (empty($settings['map_sharing_enabled'])) {
            return response()->json(['error' => 'Map sharing is not enabled.'], 403);
        }

        // Server-side liveness gate. The frontend also hides the map when
        // offline, but any caller (curl, a chatter with devtools) can hit
        // this endpoint directly, so we must gate here too. Without this,
        // the endpoint would happily return the most recent ping from any
        // past session — potentially doxxing the streamer's home.
        if (! $this->liveness->isBroadcasting($user->id)) {
            return response()->json([
                'position' => null,
                'speed_unit' => $settings['speed_unit'] ?? 'kmh',
                'streamer_name' => $user->name,
            ]);
        }

        $delay = (int) ($settings['map_delay_seconds'] ?? 0);

        $query = DB::table('external_events')
            ->where('service', 'overlabels-mobile')
            ->where('user_id', $user->id)
            ->where('event_type', 'location_update')
            ->whereNotNull(DB::raw("raw_payload->>'lat'"));

        if ($delay > 0) {
            $query->where('created_at', '<=', now()->subSeconds($delay));
        }

        $ping = $query->orderByDesc('created_at')->first();

        if (! $ping) {
            return response()->json(['position' => null]);
        }

        $payload = json_decode($ping->raw_payload, true);

        return response()->json([
            'position' => [
                'lat' => (float) ($payload['lat'] ?? 0),
                'lng' => (float) ($payload['lon'] ?? 0),
                'speed' => (float) ($payload['spd'] ?? 0),
                'bearing' => (float) ($payload['bearing'] ?? 0),
                'accuracy' => (float) ($payload['acc'] ?? 0),
                'altitude' => (float) ($payload['alt'] ?? 0),
                'timestamp' => $ping->created_at,
            ],
            'speed_unit' => $settings['speed_unit'] ?? 'kmh',
            'streamer_name' => $user->name,
        ]);
    }

    private function buildGeoJson(int $userId, string $sessionId): JsonResponse
    {
        $cacheKey = "gps_session_geojson_{$userId}_{$sessionId}";

        // Check if session is completed (immutable) - cache indefinitely
        $isCompleted = DB::table('external_events')
            ->where('service', 'overlabels-mobile')
            ->where('user_id', $userId)
            ->where('event_type', 'session_end')
            ->whereRaw("raw_payload->>'session_id' = ?", [$sessionId])
            ->exists();

        $ttl = $isCompleted ? now()->addDays(7) : now()->addSeconds(30);

        $geoJson = Cache::remember($cacheKey, $ttl, function () use ($userId, $sessionId) {
            $pings = DB::select("
                SELECT
                    (raw_payload->>'lon')::float AS lng,
                    (raw_payload->>'lat')::float AS lat,
                    (raw_payload->>'spd')::float AS speed,
                    (raw_payload->>'alt')::float AS altitude,
                    created_at
                FROM external_events
                WHERE service = 'overlabels-mobile'
                    AND user_id = ?
                    AND event_type = 'location_update'
                    AND raw_payload->>'session_id' = ?
                ORDER BY created_at ASC
            ", [$userId, $sessionId]);

            if (empty($pings)) {
                return [
                    'type' => 'FeatureCollection',
                    'features' => [],
                ];
            }

            $coordinates = array_map(fn ($p) => [$p->lng, $p->lat], $pings);
            $originalCount = count($coordinates);

            // Simplify for large sessions
            if ($originalCount > 100) {
                $coordinates = RouteSimplifier::simplify($coordinates);
            }

            return [
                'type' => 'FeatureCollection',
                'features' => [
                    [
                        'type' => 'Feature',
                        'geometry' => [
                            'type' => 'LineString',
                            'coordinates' => $coordinates,
                        ],
                        'properties' => [
                            'original_points' => $originalCount,
                            'simplified_points' => count($coordinates),
                        ],
                    ],
                    // Start marker
                    [
                        'type' => 'Feature',
                        'geometry' => [
                            'type' => 'Point',
                            'coordinates' => [$pings[0]->lng, $pings[0]->lat],
                        ],
                        'properties' => ['marker' => 'start'],
                    ],
                    // End marker
                    [
                        'type' => 'Feature',
                        'geometry' => [
                            'type' => 'Point',
                            'coordinates' => [end($pings)->lng, end($pings)->lat],
                        ],
                        'properties' => ['marker' => 'end'],
                    ],
                ],
            ];
        });

        return response()->json($geoJson);
    }

    private function isMapSharingEnabled(int $userId): bool
    {
        $integration = ExternalIntegration::where('user_id', $userId)
            ->where('service', 'overlabels-mobile')
            ->where('enabled', true)
            ->first();

        if (! $integration) {
            return false;
        }

        return ! empty(($integration->settings ?? [])['map_sharing_enabled']);
    }
}
