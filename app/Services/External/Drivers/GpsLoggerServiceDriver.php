<?php

namespace App\Services\External\Drivers;

use App\Contracts\ExternalServiceDriver;
use App\Contracts\StatefulExternalServiceDriver;
use App\Models\ExternalIntegration;
use App\Services\External\NormalizedExternalEvent;
use Illuminate\Http\Request;

class GpsLoggerServiceDriver implements ExternalServiceDriver, StatefulExternalServiceDriver
{
    public function getServiceKey(): string
    {
        return 'gpslogger';
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
        return 'location_update';
    }

    public function normalizeEvent(array $payload, string $eventType): NormalizedExternalEvent
    {
        $lat = $payload['latitude'] ?? $payload['lat'] ?? null;
        $lng = $payload['longitude'] ?? $payload['lng'] ?? $payload['lon'] ?? null;
        $speed = $payload['speed'] ?? null;
        $altitude = $payload['altitude'] ?? $payload['alt'] ?? null;
        $accuracy = $payload['accuracy'] ?? $payload['acc'] ?? null;
        $timestamp = $payload['timestamp'] ?? $payload['time'] ?? null;
        $serial = $payload['serial'] ?? $payload['ser'] ?? '0';

        $messageId = 'gps_'.($timestamp ?? now()->timestamp).'_'.$serial;

        $tags = [
            'event.latitude' => (string) ($lat ?? ''),
            'event.longitude' => (string) ($lng ?? ''),
            'event.speed' => (string) ($speed ?? ''),
            'event.altitude' => (string) ($altitude ?? ''),
            'event.accuracy' => (string) ($accuracy ?? ''),
            'event.source' => 'GPSLogger',
        ];

        return new NormalizedExternalEvent(
            service: 'gpslogger',
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
        return ['location_update'];
    }

    public function getAutoProvisionedControls(): array
    {
        return [
            ['key' => 'gps_speed', 'type' => 'number', 'label' => 'GPS Speed', 'value' => '0'],
            ['key' => 'gps_lat', 'type' => 'text', 'label' => 'GPS Latitude', 'value' => ''],
            ['key' => 'gps_lng', 'type' => 'text', 'label' => 'GPS Longitude', 'value' => ''],
            ['key' => 'gps_distance', 'type' => 'number', 'label' => 'GPS Distance (km)', 'value' => '0'],
        ];
    }

    /**
     * Return control updates for speed, lat, lng.
     * Distance is handled separately in beforeControlUpdates().
     */
    public function getControlUpdates(NormalizedExternalEvent $event): array
    {
        $raw = $event->getRaw();
        $lat = $raw['latitude'] ?? $raw['lat'] ?? null;
        $lng = $raw['longitude'] ?? $raw['lng'] ?? $raw['lon'] ?? null;
        $speedMs = $raw['speed'] ?? null;

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

        return $updates;
    }

    /**
     * Calculate distance from previous position using haversine formula
     * and add it as an incremental update.
     */
    public function beforeControlUpdates(
        ExternalIntegration $integration,
        NormalizedExternalEvent $event,
        array &$updates
    ): void {
        $raw = $event->getRaw();
        $lat = $raw['latitude'] ?? $raw['lat'] ?? null;
        $lng = $raw['longitude'] ?? $raw['lng'] ?? $raw['lon'] ?? null;

        if ($lat === null || $lng === null) {
            return;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        $settings = $integration->settings ?? [];
        $lastLat = $settings['last_lat'] ?? null;
        $lastLng = $settings['last_lng'] ?? null;

        // Apply speed unit preference
        $speedUnit = $settings['speed_unit'] ?? 'kmh';
        if ($speedUnit === 'mph' && isset($updates['gps_speed'])) {
            // Re-convert from km/h to mph
            $kmh = (float) $updates['gps_speed'];
            $updates['gps_speed'] = (string) round($kmh / 1.609344, 1);
        }

        if ($lastLat !== null && $lastLng !== null) {
            $deltaKm = $this->haversineDistance((float) $lastLat, (float) $lastLng, $lat, $lng);

            // Only add meaningful distance (> 1 meter) to filter GPS jitter
            if ($deltaKm > 0.001) {
                $updates['gps_distance'] = ['action' => 'add', 'amount' => round($deltaKm, 4)];
            }
        }

        // Store current position for next calculation
        $integration->settings = array_merge($settings, [
            'last_lat' => $lat,
            'last_lng' => $lng,
        ]);
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
