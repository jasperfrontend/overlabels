<?php

namespace App\Services;

use App\Services\Location\GeoMath;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates GPS session stats from external_events.
 *
 * Used by the authenticated GPS Sessions dashboard (full list) and by the
 * public map endpoints that want to surface a single session's meta on the
 * shared route view. Both call sites agree on the same shape so the frontend
 * formatter (useSessionDataFormatter) treats them identically.
 */
class GpsSessionAggregator
{
    /**
     * Recent sessions for a user, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forUser(int $userId, int $limit = 50): array
    {
        $rows = DB::select($this->aggregateSql().' LIMIT '.(int) $limit, [$userId]);

        $sessionIds = array_map(fn ($r) => $r->session_id, $rows);
        $distances = $sessionIds === [] ? [] : $this->computeDistances($userId, $sessionIds);

        return array_map(fn ($row) => $this->shape($row, $distances[$row->session_id] ?? 0), $rows);
    }

    /**
     * Stats for a single session, or null if no events match.
     *
     * @return array<string, mixed>|null
     */
    public function forSession(int $userId, string $sessionId): ?array
    {
        $rows = DB::select(
            $this->aggregateSql(' AND raw_payload->>\'session_id\' = ?'),
            [$userId, $sessionId]
        );

        if (empty($rows)) {
            return null;
        }

        $distances = $this->computeDistances($userId, [$sessionId]);

        return $this->shape($rows[0], $distances[$sessionId] ?? 0);
    }

    private function aggregateSql(string $extraWhere = ''): string
    {
        return "
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
                (array_agg((raw_payload->>'battery')::int ORDER BY created_at)
                    FILTER (WHERE event_type = 'location_update' AND raw_payload->>'battery' IS NOT NULL))[1] AS battery_start,
                (array_agg((raw_payload->>'battery')::int ORDER BY created_at DESC)
                    FILTER (WHERE event_type = 'location_update' AND raw_payload->>'battery' IS NOT NULL))[1] AS battery_end
            FROM external_events
            WHERE service = 'gps'
                AND user_id = ?
                AND raw_payload->>'session_id' IS NOT NULL
                $extraWhere
            GROUP BY raw_payload->>'session_id'
            ORDER BY MIN(created_at) DESC
        ";
    }

    /**
     * @param  array<int, string>  $sessionIds
     * @return array<string, float>
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
            WHERE service = 'gps'
                AND user_id = ?
                AND event_type = 'location_update'
                AND raw_payload->>'session_id' IN ($placeholders)
            ORDER BY raw_payload->>'session_id', created_at
        ", array_merge([$userId], $sessionIds));

        $distances = [];
        $prevBySession = [];

        foreach ($pings as $ping) {
            $sid = $ping->session_id;

            if (isset($prevBySession[$sid])) {
                $prev = $prevBySession[$sid];
                $delta = GeoMath::haversineDistance($prev->lat, $prev->lng, $ping->lat, $ping->lng);
                if ($delta > 0.001) {
                    $distances[$sid] = ($distances[$sid] ?? 0) + $delta;
                }
            } else {
                $distances[$sid] = 0;
            }

            $prevBySession[$sid] = $ping;
        }

        return array_map(fn ($d) => round($d, 3), $distances);
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(object $row, float $distanceKm): array
    {
        return [
            'session_id' => $row->session_id,
            'started_at' => $row->first_event_at,
            'ended_at' => $row->last_event_at,
            'completed' => (bool) $row->has_end,
            'ping_count' => (int) $row->ping_count,
            'max_speed_ms' => $row->max_speed_ms !== null ? round((float) $row->max_speed_ms, 2) : null,
            'avg_speed_ms' => $row->avg_speed_ms !== null ? round((float) $row->avg_speed_ms, 2) : null,
            'min_altitude' => $row->min_altitude !== null ? round((float) $row->min_altitude, 1) : null,
            'max_altitude' => $row->max_altitude !== null ? round((float) $row->max_altitude, 1) : null,
            'battery_start' => $row->battery_start !== null ? (int) $row->battery_start : null,
            'battery_end' => $row->battery_end !== null ? (int) $row->battery_end : null,
            'distance_km' => $distanceKm,
        ];
    }
}
