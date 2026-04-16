<?php

namespace App\Services\External\Drivers;

use App\Contracts\ExternalServiceDriver;
use App\Contracts\StatefulExternalServiceDriver;
use App\Models\ExternalIntegration;
use App\Services\External\NormalizedExternalEvent;
use Illuminate\Http\Request;

class OverlabelsMobileServiceDriver implements ExternalServiceDriver, StatefulExternalServiceDriver
{
    public function getServiceKey(): string
    {
        return 'overlabels-mobile';
    }

    /**
     * Verify the incoming request using the X-GPSLogger-Token header.
     */
    public function verifyRequest(Request $request, ExternalIntegration $integration): bool
    {
        $credentials = $integration->getCredentialsDecrypted();
        $stored = $credentials['token'] ?? null;

        if (empty($stored)) {
            return false;
        }

        $headerToken = $request->header('X-GPSLogger-Token', '');

        return hash_equals($stored, (string) $headerToken);
    }

    public function parseEventType(array $payload): ?string
    {
        $event = $payload['event'] ?? null;

        return match ($event) {
            'session_start' => 'session_start',
            'session_end' => 'session_end',
            'settings_sync' => 'settings_sync',
            default => 'location_update',
        };
    }

    public function normalizeEvent(array $payload, string $eventType): NormalizedExternalEvent
    {
        $sessionId = $payload['session_id'] ?? null;
        $timestamp = $payload['timestamp'] ?? $payload['time'] ?? null;

        if ($eventType === 'session_start' || $eventType === 'session_end') {
            $messageId = $eventType.'_'.($sessionId ?? now()->timestamp);

            $tags = [
                'event.session_id' => (string) ($sessionId ?? ''),
                'event.source' => 'Overlabels GPS',
            ];

            return new NormalizedExternalEvent(
                service: 'overlabels-mobile',
                eventType: $eventType,
                messageId: $messageId,
                fromName: null,
                message: null,
                amount: null,
                currency: null,
                templateTags: $tags,
                raw: $payload,
            );
        }

        // location_update
        $lat = $payload['latitude'] ?? $payload['lat'] ?? null;
        $lng = $payload['longitude'] ?? $payload['lng'] ?? $payload['lon'] ?? null;
        $speed = $payload['speed'] ?? $payload['spd'] ?? null;
        $altitude = $payload['altitude'] ?? $payload['alt'] ?? null;
        $accuracy = $payload['accuracy'] ?? $payload['acc'] ?? null;
        $serial = $payload['serial'] ?? $payload['ser'] ?? '0';
        $bearing = $payload['bearing'] ?? null;
        $battery = $payload['battery'] ?? null;
        $charging = $payload['charging'] ?? null;

        $messageId = 'gps_'.($timestamp ?? now()->timestamp).'_'.$serial;

        $tags = [
            'event.latitude' => (string) ($lat ?? ''),
            'event.longitude' => (string) ($lng ?? ''),
            'event.speed' => (string) ($speed ?? ''),
            'event.altitude' => (string) ($altitude ?? ''),
            'event.accuracy' => (string) ($accuracy ?? ''),
            'event.bearing' => (string) ($bearing ?? ''),
            'event.battery' => (string) ($battery ?? ''),
            'event.charging' => (string) ($charging ?? ''),
            'event.session_id' => (string) ($sessionId ?? ''),
            'event.source' => 'Overlabels GPS',
        ];

        return new NormalizedExternalEvent(
            service: 'overlabels-mobile',
            eventType: $eventType,
            messageId: $messageId,
            fromName: null,
            message: null,
            amount: null,
            currency: null,
            templateTags: $tags,
            raw: $payload,
        );
    }

    public function getSupportedEventTypes(): array
    {
        return ['location_update', 'session_start', 'session_end', 'settings_sync'];
    }

    public function getAutoProvisionedControls(): array
    {
        return [
            ['key' => 'gps_speed', 'type' => 'number', 'label' => 'GPS Speed', 'value' => '0'],
            ['key' => 'gps_lat', 'type' => 'text', 'label' => 'GPS Latitude', 'value' => ''],
            ['key' => 'gps_lng', 'type' => 'text', 'label' => 'GPS Longitude', 'value' => ''],
            ['key' => 'gps_distance', 'type' => 'number', 'label' => 'GPS Distance (km, cumulative)', 'value' => '0'],
            ['key' => 'gps_bearing', 'type' => 'number', 'label' => 'GPS Bearing (degrees)', 'value' => '0'],
            ['key' => 'gps_battery', 'type' => 'number', 'label' => 'Phone Battery (%)', 'value' => '0'],
            ['key' => 'gps_charging', 'type' => 'boolean', 'label' => 'Phone Charging', 'value' => '0'],
            ['key' => 'gps_tracking', 'type' => 'boolean', 'label' => 'GPS Tracking Active', 'value' => '0'],
            ['key' => 'gps_session_distance', 'type' => 'number', 'label' => 'GPS Session Distance (km)', 'value' => '0'],
            ['key' => 'gps_session_max_speed', 'type' => 'number', 'label' => 'GPS Session Max Speed (m/s)', 'value' => '0'],
            ['key' => 'gps_session_avg_speed', 'type' => 'number', 'label' => 'GPS Session Avg Speed (m/s)', 'value' => '0'],
            ['key' => 'gps_session_duration', 'type' => 'number', 'label' => 'GPS Session Duration (seconds)', 'value' => '0'],
        ];
    }

    /**
     * Return control updates for the given event.
     * Session events only toggle gps_tracking. Location pings update GPS controls.
     */
    public function getControlUpdates(NormalizedExternalEvent $event): array
    {
        if ($event->getEventType() === 'session_start') {
            return ['gps_tracking' => '1'];
        }

        if ($event->getEventType() === 'session_end') {
            return ['gps_tracking' => '0'];
        }

        // location_update
        $raw = $event->getRaw();
        $lat = $raw['latitude'] ?? $raw['lat'] ?? null;
        $lng = $raw['longitude'] ?? $raw['lng'] ?? $raw['lon'] ?? null;
        $speedMs = $raw['speed'] ?? $raw['spd'] ?? null;
        $bearing = $raw['bearing'] ?? null;
        $battery = $raw['battery'] ?? null;
        $charging = $raw['charging'] ?? null;

        $updates = [];

        if ($lat !== null) {
            $updates['gps_lat'] = (string) $lat;
        }

        if ($lng !== null) {
            $updates['gps_lng'] = (string) $lng;
        }

        if ($speedMs !== null) {
            // Default conversion: m/s -> km/h
            $updates['gps_speed'] = (string) round((float) $speedMs * 3.6, 1);
        }

        if ($bearing !== null) {
            $updates['gps_bearing'] = (string) round((float) $bearing, 1);
        }

        if ($battery !== null) {
            $updates['gps_battery'] = (string) (int) $battery;
        }

        if ($charging !== null) {
            $updates['gps_charging'] = (string) $charging;
        }

        return $updates;
    }

    /**
     * Calculate distance from previous position using haversine formula
     * and maintain per-session running aggregates (distance, max/avg speed,
     * duration) in integration.settings.
     *
     * Session aggregate controls store RAW values (m/s for speed, km for
     * distance, seconds for duration) so templates can format with pipes
     * against the user's locale.
     */
    public function beforeControlUpdates(
        ExternalIntegration $integration,
        NormalizedExternalEvent $event,
        array &$updates
    ): void {
        $settings = $integration->settings ?? [];

        if ($event->getEventType() === 'session_start') {
            $this->resetSessionState($integration, $settings, $event->getRaw()['session_id'] ?? null);
            $updates['gps_session_distance'] = '0';
            $updates['gps_session_max_speed'] = '0';
            $updates['gps_session_avg_speed'] = '0';
            $updates['gps_session_duration'] = '0';
            return;
        }

        if ($event->getEventType() === 'session_end') {
            // Freeze the final duration so overlays show the total session length
            // even after pings stop arriving.
            $startedAt = $settings['session_started_at_unix'] ?? null;
            if ($startedAt !== null) {
                $updates['gps_session_duration'] = (string) max(0, now()->timestamp - (int) $startedAt);
            }
            return;
        }

        if ($event->getEventType() !== 'location_update') {
            return;
        }

        $raw = $event->getRaw();
        $lat = $raw['latitude'] ?? $raw['lat'] ?? null;
        $lng = $raw['longitude'] ?? $raw['lng'] ?? $raw['lon'] ?? null;

        if ($lat === null || $lng === null) {
            return;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;
        $sessionId = $raw['session_id'] ?? null;

        // If the incoming session_id doesn't match what we've been tracking
        // (session_start lost, stale state, first deployment), treat this ping
        // as the start of a fresh session.
        $trackedSessionId = $settings['session_id'] ?? null;
        if ($sessionId !== null && $trackedSessionId !== $sessionId) {
            $this->resetSessionState($integration, $settings, $sessionId);
            $trackedSessionId = $sessionId;
        }

        $lastLat = $settings['last_lat'] ?? null;
        $lastLng = $settings['last_lng'] ?? null;

        // Honor the user's display-speed preference for gps_speed (legacy control).
        $speedUnit = $settings['speed_unit'] ?? 'kmh';
        if ($speedUnit === 'mph' && isset($updates['gps_speed'])) {
            $kmh = (float) $updates['gps_speed'];
            $updates['gps_speed'] = (string) round($kmh / 1.609344, 1);
        }

        $deltaKm = 0.0;
        if ($lastLat !== null && $lastLng !== null) {
            $deltaKm = $this->haversineDistance((float) $lastLat, (float) $lastLng, $lat, $lng);
            if ($deltaKm > 0.001) {
                $updates['gps_distance'] = ['action' => 'add', 'amount' => round($deltaKm, 4)];
            } else {
                $deltaKm = 0.0;
            }
        }

        // Per-session distance (replacement, not add — we hold the accumulator in settings).
        $sessionDistanceKm = (float) ($settings['session_distance_km'] ?? 0.0) + $deltaKm;
        $settings['session_distance_km'] = $sessionDistanceKm;
        $updates['gps_session_distance'] = (string) round($sessionDistanceKm, 4);

        // Speed stats in raw m/s.
        $speedMsRaw = $raw['speed'] ?? $raw['spd'] ?? null;
        if ($speedMsRaw !== null) {
            $speedMs = (float) $speedMsRaw;
            $maxMs = max((float) ($settings['session_max_speed_ms'] ?? 0.0), $speedMs);
            $sumMs = (float) ($settings['session_speed_sum_ms'] ?? 0.0) + $speedMs;
            $count = (int) ($settings['session_speed_count'] ?? 0) + 1;

            $settings['session_max_speed_ms'] = $maxMs;
            $settings['session_speed_sum_ms'] = $sumMs;
            $settings['session_speed_count'] = $count;

            $updates['gps_session_max_speed'] = (string) round($maxMs, 4);
            $updates['gps_session_avg_speed'] = (string) round($sumMs / $count, 4);
        }

        // Duration in seconds since session_start.
        $startedAt = (int) ($settings['session_started_at_unix'] ?? now()->timestamp);
        $updates['gps_session_duration'] = (string) max(0, now()->timestamp - $startedAt);

        $integration->settings = array_merge($settings, [
            'last_lat' => $lat,
            'last_lng' => $lng,
        ]);
        $integration->save();
    }

    /**
     * Wipe all per-session running counters and stamp a new session_started_at.
     * Leaves last_lat/last_lng alone — those drive the cumulative gps_distance.
     */
    private function resetSessionState(ExternalIntegration $integration, array &$settings, ?string $sessionId): void
    {
        $settings = array_merge($settings, [
            'session_id' => $sessionId,
            'session_started_at_unix' => now()->timestamp,
            'session_distance_km' => 0.0,
            'session_max_speed_ms' => 0.0,
            'session_speed_sum_ms' => 0.0,
            'session_speed_count' => 0,
        ]);
        $integration->settings = $settings;
        $integration->save();
    }

    /**
     * Calculate distance between two points using the haversine formula.
     * Returns distance in kilometers.
     */
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
