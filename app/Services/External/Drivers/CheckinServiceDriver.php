<?php

namespace App\Services\External\Drivers;

use App\Contracts\ExternalServiceDriver;
use App\Contracts\StatefulExternalServiceDriver;
use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Services\External\NormalizedExternalEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * First-party !checkin integration: viewers pin themselves on the streamer's
 * globe from chat. Events never arrive over the public webhook route - the
 * bot relays `!checkin <place>` to the internal BotCheckinController, which
 * resolves the place against the local gazetteer, upserts the pin, and runs
 * this driver through the same pipeline every webhook service uses.
 *
 * The payload this driver normalizes is therefore controller-built, already
 * enriched with resolver output and upsert facts (pin_created,
 * new_this_stream, total_pins, distance_km). The driver maps facts onto
 * controls; it never re-derives them.
 */
class CheckinServiceDriver implements ExternalServiceDriver, StatefulExternalServiceDriver
{
    /**
     * Per-stream keys reset at go-live by StreamSessionService::resetControls.
     * A key belongs here only if its label promises per-stream scope - the
     * latest_* controls persist, same rule as latest_cheer* and the 25
     * donation-service equivalents.
     */
    public const array PER_STREAM_CONTROL_KEYS = [
        'checkins_this_stream',
        'unique_countries_this_stream',
        'farthest_checkin_km_this_stream',
    ];

    /**
     * Same TTL reasoning as the unique-chatters set: longer than any plausible
     * stream, because the authoritative clear is the go-live reset.
     */
    private const int COUNTRY_SET_TTL = 43200; // 12 hours

    public function getServiceKey(): string
    {
        return 'checkin';
    }

    /**
     * Checkins have no public webhook: the only entry point is the internal
     * bot endpoint behind X-Internal-Secret. A POST to
     * /api/webhooks/checkin/{token} must always fail verification.
     */
    public function verifyRequest(Request $request, ExternalIntegration $integration): bool
    {
        return false;
    }

    public function parseEventType(array $payload): ?string
    {
        return ($payload['type'] ?? null) === 'checkin' ? 'checkin' : null;
    }

    public function normalizeEvent(array $payload, string $eventType): NormalizedExternalEvent
    {
        $distanceKm = $payload['distance_km'] ?? null;

        $tags = [
            'event.user_name' => (string) ($payload['chatter_display_name'] ?? ''),
            'event.user_login' => (string) ($payload['chatter_login'] ?? ''),
            'event.place' => (string) ($payload['place_label'] ?? ''),
            'event.country' => (string) ($payload['country_name'] ?? ''),
            'event.country_code' => (string) ($payload['country_code'] ?? ''),
            'event.lat' => (string) ($payload['lat'] ?? ''),
            'event.lng' => (string) ($payload['lng'] ?? ''),
            'event.distance_km' => $distanceKm !== null ? (string) $distanceKm : '',
        ];

        return new NormalizedExternalEvent(
            service: 'checkin',
            eventType: $eventType,
            messageId: 'checkin_'.($payload['chatter_id'] ?? '0').'_'.($payload['at'] ?? now()->timestamp),
            fromName: $payload['chatter_display_name'] ?? null,
            message: $payload['place_label'] ?? null,
            amount: null,
            currency: null,
            templateTags: $tags,
            raw: $payload,
        );
    }

    public function getSupportedEventTypes(): array
    {
        return ['checkin'];
    }

    public function getAutoProvisionedControls(): array
    {
        return [
            ['key' => 'checkins_this_stream', 'type' => 'counter', 'label' => 'Checkins This Stream', 'value' => '0'],
            ['key' => 'unique_countries_this_stream', 'type' => 'number', 'label' => 'Unique Countries This Stream', 'value' => '0'],
            ['key' => 'farthest_checkin_km_this_stream', 'type' => 'number', 'label' => 'Farthest Checkin This Stream (km)', 'value' => '0'],
            ['key' => 'checkins_total', 'type' => 'number', 'label' => 'Checkins Total (all time)', 'value' => '0'],
            ['key' => 'latest_checkin_name', 'type' => 'text', 'label' => 'Latest Checkin Name', 'value' => ''],
            ['key' => 'latest_checkin_place', 'type' => 'text', 'label' => 'Latest Checkin Place', 'value' => ''],
            ['key' => 'latest_checkin_country', 'type' => 'text', 'label' => 'Latest Checkin Country', 'value' => ''],
            ['key' => 'latest_checkin_lat', 'type' => 'text', 'label' => 'Latest Checkin Latitude', 'value' => ''],
            ['key' => 'latest_checkin_lng', 'type' => 'text', 'label' => 'Latest Checkin Longitude', 'value' => ''],
            ['key' => 'latest_checkin_distance_km', 'type' => 'number', 'label' => 'Latest Checkin Distance (km)', 'value' => '0'],
        ];
    }

    public function getControlUpdates(NormalizedExternalEvent $event): array
    {
        $raw = $event->getRaw();

        $updates = [
            'latest_checkin_name' => (string) ($raw['chatter_display_name'] ?? ''),
            'latest_checkin_place' => (string) ($raw['place_label'] ?? ''),
            'latest_checkin_country' => (string) ($raw['country_name'] ?? ''),
            'latest_checkin_lat' => (string) ($raw['lat'] ?? ''),
            'latest_checkin_lng' => (string) ($raw['lng'] ?? ''),
        ];

        if (isset($raw['total_pins'])) {
            $updates['checkins_total'] = (string) (int) $raw['total_pins'];
        }

        if (($raw['distance_km'] ?? null) !== null) {
            $updates['latest_checkin_distance_km'] = (string) $raw['distance_km'];
        }

        // Counters count unique viewers, not command invocations: a pin move
        // by someone already on this stream's map increments nothing.
        if (! empty($raw['new_this_stream'])) {
            $updates['checkins_this_stream'] = ['action' => 'increment'];
        }

        return $updates;
    }

    /**
     * The two upward-only per-stream aggregates. Both use max() against the
     * current control value so late or replayed events can never make a
     * counter visibly count down on stream; the go-live reset (which also
     * forgets the country set) is the only thing that lowers them.
     */
    public function beforeControlUpdates(
        ExternalIntegration $integration,
        NormalizedExternalEvent $event,
        array &$updates
    ): void {
        $raw = $event->getRaw();
        $user = $integration->user;

        if (! $user) {
            return;
        }

        $countryCode = strtoupper((string) ($raw['country_code'] ?? ''));

        if ($countryCode !== '') {
            $key = self::countrySetCacheKey($user->id);
            $set = Cache::get($key, []);
            $set[$countryCode] = true;
            Cache::put($key, $set, self::COUNTRY_SET_TTL);

            $current = (int) $this->currentControlValue($user->id, 'unique_countries_this_stream');
            $updates['unique_countries_this_stream'] = (string) max(count($set), $current);
        }

        $distanceKm = $raw['distance_km'] ?? null;

        if ($distanceKm !== null) {
            $current = (float) $this->currentControlValue($user->id, 'farthest_checkin_km_this_stream');
            $updates['farthest_checkin_km_this_stream'] = (string) max((float) $distanceKm, $current);
        }
    }

    /**
     * Public so StreamSessionService::resetControls can forget the set at
     * go-live - without that the country counter stays pinned at last
     * stream's total, exactly like the unique-chatters set it mirrors.
     */
    public static function countrySetCacheKey(int $userId): string
    {
        return "checkin:countries:{$userId}";
    }

    private function currentControlValue(int $userId, string $key): string
    {
        return (string) (OverlayControl::where('user_id', $userId)
            ->where('source', 'checkin')
            ->where('key', $key)
            ->where('source_managed', true)
            ->value('value') ?? '0');
    }
}
