<?php

namespace App\Services\External\Drivers;

use App\Contracts\ExternalServiceDriver;
use App\Contracts\StatefulExternalServiceDriver;
use App\Models\ExternalIntegration;
use App\Services\External\NormalizedExternalEvent;
use App\Services\Location\GeoMath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GpsServiceDriver implements ExternalServiceDriver, StatefulExternalServiceDriver
{
    /**
     * Reject any ping whose distance from the previous fix implies a speed
     * faster than this. Catches GPS "teleports" (null-island fixes, garbage
     * coordinates) that would otherwise inject thousands of phantom km. Well
     * above any real car/train speed, so legitimate movement is never dropped.
     */
    private const MAX_PLAUSIBLE_KMH = 400.0;

    /**
     * Ignore sub-metre jitter so a stationary device doesn't slowly accrue
     * distance from GPS noise.
     */
    private const MIN_DELTA_KM = 0.001;

    public function getServiceKey(): string
    {
        return 'gps';
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
                service: 'gps',
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
            service: 'gps',
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
            ['key' => 'speed', 'type' => 'number', 'label' => 'GPS Speed (m/s)', 'value' => '0'],
            ['key' => 'lat', 'type' => 'text', 'label' => 'GPS Latitude', 'value' => ''],
            ['key' => 'lng', 'type' => 'text', 'label' => 'GPS Longitude', 'value' => ''],
            ['key' => 'distance', 'type' => 'number', 'label' => 'GPS Distance (km, cumulative)', 'value' => '0'],
            ['key' => 'bearing', 'type' => 'number', 'label' => 'GPS Bearing (degrees)', 'value' => '0'],
            ['key' => 'accuracy', 'type' => 'number', 'label' => 'GPS Accuracy (meters)', 'value' => '0'],
            ['key' => 'battery', 'type' => 'number', 'label' => 'Phone Battery (%)', 'value' => '0'],
            ['key' => 'charging', 'type' => 'boolean', 'label' => 'Phone Charging', 'value' => '0'],
            ['key' => 'tracking', 'type' => 'boolean', 'label' => 'GPS Tracking Active', 'value' => '0'],
            ['key' => 'session_distance', 'type' => 'number', 'label' => 'GPS Session Distance (km)', 'value' => '0'],
            ['key' => 'session_max_speed', 'type' => 'number', 'label' => 'GPS Session Max Speed (m/s)', 'value' => '0'],
            ['key' => 'session_avg_speed', 'type' => 'number', 'label' => 'GPS Session Avg Speed (m/s)', 'value' => '0'],
            ['key' => 'session_duration', 'type' => 'number', 'label' => 'GPS Session Duration (seconds)', 'value' => '0'],
        ];
    }

    /**
     * Return control updates for the given event.
     * Session events only toggle `tracking`. Location pings update GPS controls.
     */
    public function getControlUpdates(NormalizedExternalEvent $event): array
    {
        if ($event->getEventType() === 'session_start') {
            return ['tracking' => '1'];
        }

        if ($event->getEventType() === 'session_end') {
            return ['tracking' => '0'];
        }

        // location_update
        $raw = $event->getRaw();
        $lat = $raw['latitude'] ?? $raw['lat'] ?? null;
        $lng = $raw['longitude'] ?? $raw['lng'] ?? $raw['lon'] ?? null;
        $speedMs = $raw['speed'] ?? $raw['spd'] ?? null;
        $bearing = $raw['bearing'] ?? null;
        $accuracy = $raw['accuracy'] ?? $raw['acc'] ?? null;
        $battery = $raw['battery'] ?? null;
        $charging = $raw['charging'] ?? null;

        $updates = [];

        if ($lat !== null) {
            $updates['lat'] = (string) $lat;
        }

        if ($lng !== null) {
            $updates['lng'] = (string) $lng;
        }

        if ($speedMs !== null) {
            // Store raw m/s; templates format with the speed:* pipe.
            $updates['speed'] = (string) round((float) $speedMs, 4);
        }

        if ($bearing !== null) {
            $updates['bearing'] = (string) round((float) $bearing, 1);
        }

        if ($accuracy !== null) {
            $updates['accuracy'] = (string) round((float) $accuracy, 1);
        }

        if ($battery !== null) {
            $updates['battery'] = (string) (int) $battery;
        }

        if ($charging !== null) {
            $updates['charging'] = (string) $charging;
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
        $type = $event->getEventType();
        $raw = $event->getRaw();

        if ($type === 'session_start') {
            $this->mutateSettingsLocked($integration, function (array &$settings) use ($raw) {
                $this->resetSessionState($settings, $raw['session_id'] ?? null);
            });
            $updates['session_distance'] = '0';
            $updates['session_max_speed'] = '0';
            $updates['session_avg_speed'] = '0';
            $updates['session_duration'] = '0';

            return;
        }

        if ($type === 'session_end') {
            // Freeze the final duration so overlays show the total session length
            // even after pings stop arriving. Read-only, no lock needed.
            $startedAt = ($integration->settings ?? [])['session_started_at_unix'] ?? null;
            if ($startedAt !== null) {
                $updates['session_duration'] = (string) max(0, now()->timestamp - (int) $startedAt);
            }

            return;
        }

        if ($type !== 'location_update') {
            return;
        }

        $lat = $raw['latitude'] ?? $raw['lat'] ?? null;
        $lng = $raw['longitude'] ?? $raw['lng'] ?? $raw['lon'] ?? null;

        // A ping with no usable coordinate, or one outside the valid lat/lng
        // range (garbage like lat=1, lon=1e150), can't drive distance or
        // position. Drop the position-derived control updates so we never
        // broadcast a junk fix.
        if ($lat === null || $lng === null || ! $this->isValidCoordinate((float) $lat, (float) $lng)) {
            $this->dropPositionUpdates($updates);

            return;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;
        $fixAt = isset($raw['timestamp'])
            ? (int) $raw['timestamp']
            : (isset($raw['time']) ? (int) $raw['time'] : now()->timestamp);

        // All reads and writes of the per-session accumulators happen under a
        // row lock so overlapping webhook requests (rapid pings, settings_sync)
        // can't clobber each other's updates — that lost-update race is how a
        // session reset silently got reverted and the counter ran away.
        $rejected = false;
        $this->mutateSettingsLocked($integration, function (array &$settings) use (&$updates, &$rejected, $raw, $lat, $lng, $fixAt) {
            $sessionId = $raw['session_id'] ?? null;

            // New session_id (session_start lost, stale state, first deploy):
            // treat this ping as the start of a fresh session.
            if ($sessionId !== null && ($settings['session_id'] ?? null) !== $sessionId) {
                $this->resetSessionState($settings, $sessionId);
            }

            $lastLat = $settings['last_lat'] ?? null;
            $lastLng = $settings['last_lng'] ?? null;
            $lastFixAt = $settings['last_fix_at_unix'] ?? null;

            $deltaKm = 0.0;
            if ($lastLat !== null && $lastLng !== null) {
                $deltaKm = GeoMath::haversineDistance((float) $lastLat, (float) $lastLng, $lat, $lng);

                // Teleport guard: if the implied speed is physically impossible,
                // this is a bad fix. Reject it without advancing position so the
                // next good ping measures from the last real location.
                if ($lastFixAt !== null) {
                    $elapsed = max(1, $fixAt - (int) $lastFixAt);
                    $impliedKmh = $deltaKm / ($elapsed / 3600);
                    if ($impliedKmh > self::MAX_PLAUSIBLE_KMH) {
                        $rejected = true;

                        return;
                    }
                }

                if ($deltaKm > self::MIN_DELTA_KM) {
                    $updates['distance'] = ['action' => 'add', 'amount' => round($deltaKm, 4)];
                } else {
                    $deltaKm = 0.0;
                }
            }

            // Per-session distance (replacement, not add — we hold the accumulator in settings).
            $sessionDistanceKm = (float) ($settings['session_distance_km'] ?? 0.0) + $deltaKm;
            $settings['session_distance_km'] = $sessionDistanceKm;
            $updates['session_distance'] = (string) round($sessionDistanceKm, 4);

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

                $updates['session_max_speed'] = (string) round($maxMs, 4);
                $updates['session_avg_speed'] = (string) round($sumMs / $count, 4);
            }

            // Duration in seconds since session_start.
            $startedAt = (int) ($settings['session_started_at_unix'] ?? $fixAt);
            $updates['session_duration'] = (string) max(0, now()->timestamp - $startedAt);

            $settings['last_lat'] = $lat;
            $settings['last_lng'] = $lng;
            $settings['last_fix_at_unix'] = $fixAt;
        });

        if ($rejected) {
            $this->dropPositionUpdates($updates);
        }
    }

    /**
     * Wipe all per-session running counters and stamp a new session_started_at.
     * Also clears last_lat/last_lng so the first ping of the new session
     * establishes its own baseline instead of differencing against the previous
     * session's end point (which injected phantom cross-session distance into
     * both the session and cumulative counters).
     *
     * Mutates $settings in place; the caller owns persistence (under lock).
     */
    private function resetSessionState(array &$settings, ?string $sessionId): void
    {
        $settings = array_merge($settings, [
            'session_id' => $sessionId,
            'session_started_at_unix' => now()->timestamp,
            'session_distance_km' => 0.0,
            'session_max_speed_ms' => 0.0,
            'session_speed_sum_ms' => 0.0,
            'session_speed_count' => 0,
        ]);

        unset($settings['last_lat'], $settings['last_lng'], $settings['last_fix_at_unix']);
    }

    /**
     * Read-modify-write the integration's settings JSONB under a row lock so
     * concurrent GPS webhook requests for the same integration serialize
     * instead of clobbering each other. The caller's in-memory model is synced
     * to the committed state so a later last_received_at write doesn't revert it.
     */
    private function mutateSettingsLocked(ExternalIntegration $integration, callable $mutator): void
    {
        DB::transaction(function () use ($integration, $mutator) {
            $fresh = ExternalIntegration::whereKey($integration->getKey())
                ->lockForUpdate()
                ->first();

            if ($fresh === null) {
                return;
            }

            $settings = $fresh->settings ?? [];
            $mutator($settings);
            $fresh->settings = $settings;
            $fresh->save();

            // Sync the caller's model to committed state and mark it clean so the
            // controller's subsequent last_received_at update only touches that
            // column, never rewriting (stale) settings.
            $integration->setRawAttributes($fresh->getAttributes(), true);
        });
    }

    /**
     * A coordinate is usable only inside the real lat/lng range. Exact (0,0)
     * is the classic "no fix yet" sentinel, so it's rejected too.
     */
    private function isValidCoordinate(float $lat, float $lng): bool
    {
        return is_finite($lat) && is_finite($lng)
            && $lat >= -90.0 && $lat <= 90.0
            && $lng >= -180.0 && $lng <= 180.0
            && ! ($lat === 0.0 && $lng === 0.0);
    }

    /**
     * Strip position-derived control updates so a rejected/garbage fix is never
     * broadcast to overlays or the public map.
     */
    private function dropPositionUpdates(array &$updates): void
    {
        unset($updates['lat'], $updates['lng'], $updates['speed'], $updates['bearing']);
    }
}
