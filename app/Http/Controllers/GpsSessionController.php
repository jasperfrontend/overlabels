<?php

namespace App\Http\Controllers;

use App\Models\ExternalIntegration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GpsSessionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'overlabels-mobile')
            ->first();

        $speedUnit = ($integration?->settings ?? [])['speed_unit'] ?? 'kmh';

        $sessions = $this->aggregateSessions($user->id);

        return Inertia::render('dashboard/gps-sessions', [
            'sessions' => $sessions,
            'speedUnit' => $speedUnit,
        ]);
    }

    /**
     * DELETE /dashboard/gps-sessions/{sessionId}
     * Remove all events for a given session.
     */
    public function destroy(string $sessionId): JsonResponse
    {
        $userId = auth()->id();

        $deleted = DB::table('external_events')
            ->where('service', 'overlabels-mobile')
            ->where('user_id', $userId)
            ->whereRaw("raw_payload->>'session_id' = ?", [$sessionId])
            ->delete();

        if ($deleted === 0) {
            return response()->json(['error' => 'Session not found.'], 404);
        }

        // Clear cached GeoJSON for this session
        \Illuminate\Support\Facades\Cache::forget("gps_session_geojson_{$userId}_{$sessionId}");

        return response()->json(['status' => 'ok', 'deleted' => $deleted]);
    }

    /**
     * Aggregate GPS session data from external_events using jsonb queries.
     *
     * @return array<int, array<string, mixed>>
     */
    private function aggregateSessions(int $userId): array
    {
        $rows = DB::select("
            SELECT
                raw_payload->>'session_id' AS session_id,
                MIN(created_at) AS first_event_at,
                MAX(created_at) AS last_event_at,
                COUNT(*) FILTER (WHERE event_type = 'location_update') AS ping_count,
                BOOL_OR(event_type = 'session_end') AS has_end,
                MAX((raw_payload->>'spd')::float)
                    FILTER (WHERE event_type = 'location_update') AS max_speed_ms,
                AVG((raw_payload->>'spd')::float)
                    FILTER (WHERE event_type = 'location_update') AS avg_speed_ms,
                MIN((raw_payload->>'alt')::float)
                    FILTER (WHERE event_type = 'location_update' AND raw_payload->>'alt' IS NOT NULL) AS min_altitude,
                MAX((raw_payload->>'alt')::float)
                    FILTER (WHERE event_type = 'location_update' AND raw_payload->>'alt' IS NOT NULL) AS max_altitude,
                (array_agg((raw_payload->>'battery')::int ORDER BY created_at ASC)
                    FILTER (WHERE event_type = 'location_update' AND raw_payload->>'battery' IS NOT NULL))[1] AS battery_start,
                (array_agg((raw_payload->>'battery')::int ORDER BY created_at DESC)
                    FILTER (WHERE event_type = 'location_update' AND raw_payload->>'battery' IS NOT NULL))[1] AS battery_end
            FROM external_events
            WHERE service = 'overlabels-mobile'
                AND user_id = ?
                AND raw_payload->>'session_id' IS NOT NULL
            GROUP BY raw_payload->>'session_id'
            HAVING COUNT(*) FILTER (WHERE event_type = 'location_update') > 0
            ORDER BY MIN(created_at) DESC
            LIMIT 50
        ", [$userId]);

        // Compute distance per session from ordered pings
        $sessionIds = array_map(fn ($r) => $r->session_id, $rows);

        $distances = [];
        if (! empty($sessionIds)) {
            $distances = $this->computeDistances($userId, $sessionIds);
        }

        return array_map(function ($row) use ($distances) {
            $startedAt = $row->first_event_at;
            $endedAt = $row->last_event_at;

            return [
                'session_id' => $row->session_id,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'completed' => (bool) $row->has_end,
                'ping_count' => (int) $row->ping_count,
                'max_speed_ms' => $row->max_speed_ms !== null ? round((float) $row->max_speed_ms, 2) : null,
                'avg_speed_ms' => $row->avg_speed_ms !== null ? round((float) $row->avg_speed_ms, 2) : null,
                'min_altitude' => $row->min_altitude !== null ? round((float) $row->min_altitude, 1) : null,
                'max_altitude' => $row->max_altitude !== null ? round((float) $row->max_altitude, 1) : null,
                'battery_start' => $row->battery_start !== null ? (int) $row->battery_start : null,
                'battery_end' => $row->battery_end !== null ? (int) $row->battery_end : null,
                'distance_km' => $distances[$row->session_id] ?? 0,
            ];
        }, $rows);
    }

    /**
     * Compute haversine distance for each session from ordered pings.
     *
     * @param  array<int, string>  $sessionIds
     * @return array<string, float>  session_id => distance in km
     */
    private function computeDistances(int $userId, array $sessionIds): array
    {
        $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));

        $pings = DB::select("
            SELECT
                raw_payload->>'session_id' AS session_id,
                (raw_payload->>'lat')::float AS lat,
                (raw_payload->>'lon')::float AS lng
            FROM external_events
            WHERE service = 'overlabels-mobile'
                AND user_id = ?
                AND event_type = 'location_update'
                AND raw_payload->>'session_id' IN ({$placeholders})
            ORDER BY raw_payload->>'session_id', created_at ASC
        ", array_merge([$userId], $sessionIds));

        $distances = [];
        $prevBySession = [];

        foreach ($pings as $ping) {
            $sid = $ping->session_id;

            if (isset($prevBySession[$sid])) {
                $prev = $prevBySession[$sid];
                $delta = $this->haversineDistance($prev->lat, $prev->lng, $ping->lat, $ping->lng);
                if ($delta > 0.001) { // >1m jitter filter
                    $distances[$sid] = ($distances[$sid] ?? 0) + $delta;
                }
            } else {
                $distances[$sid] = 0;
            }

            $prevBySession[$sid] = $ping;
        }

        return array_map(fn ($d) => round($d, 3), $distances);
    }

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }
}
