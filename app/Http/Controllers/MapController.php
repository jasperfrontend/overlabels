<?php

namespace App\Http\Controllers;

use App\Models\ExternalIntegration;
use App\Models\User;
use App\Services\GpsLivenessService;
use App\Services\GpsSessionAggregator;
use App\Services\MapSlugService;
use App\Services\OgImageService;
use Illuminate\View\View;

class MapController extends Controller
{
    public function __construct(
        private GpsLivenessService $liveness,
        private MapSlugService $slugService,
        private GpsSessionAggregator $aggregator,
        private OgImageService $ogImages,
    ) {}

    /**
     * GET /map/{slug}
     * Public live map page. Slug is a Sqids-encoded Twitch ID.
     */
    public function live(string $slug): View
    {
        $user = $this->resolveUser($slug);

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'gps')
            ->where('enabled', true)
            ->first();

        if (! $integration || empty(($integration->settings ?? [])['map_sharing_enabled'])) {
            abort(404);
        }

        $isLive = $this->liveness->isBroadcasting($user->id);

        $delay = (int) (($integration->settings ?? [])['map_delay_seconds'] ?? 0);
        $speedUnit = ($integration->settings ?? [])['speed_unit'] ?? 'kmh';

        return view('map.live', [
            'slug' => $slug,
            'streamerName' => $user->name,
            'delay' => $delay,
            'speedUnit' => $speedUnit,
            'isLive' => $isLive,
        ]);
    }

    /**
     * GET /map/{slug}/{sessionId}
     * Public saved session map page.
     */
    public function session(string $slug, string $sessionId): View
    {
        $user = $this->resolveUser($slug);

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'gps')
            ->where('enabled', true)
            ->first();

        if (! $integration || empty(($integration->settings ?? [])['map_sharing_enabled'])) {
            abort(404);
        }

        $speedUnit = ($integration->settings ?? [])['speed_unit'] ?? 'kmh';
        $locale = $user->locale ?? 'en-US';

        // Best-effort OG image: a polyline silhouette + stat tiles. If the
        // session has no aggregate (e.g. brand-new before any pings), fall
        // through to the default OG image - this surface is decorative.
        $session = $this->aggregator->forSession($user->id, $sessionId);
        $ogImagePath = null;
        $ogDescription = null;

        if ($session !== null) {
            $coords = $this->aggregator->coordinatesFor($user->id, $sessionId);
            $canonicalUrl = url("/map/{$slug}/{$sessionId}");
            $ogImagePath = $this->ogImages->urlForGpsSession(
                $session,
                $coords,
                $user->name,
                $speedUnit,
                $locale,
                $canonicalUrl,
            );
            $ogDescription = $this->buildOgDescription($session, $speedUnit);
        }

        return view('map.session', [
            'slug' => $slug,
            'sessionId' => $sessionId,
            'streamerName' => $user->name,
            'speedUnit' => $speedUnit,
            'ogImagePath' => $ogImagePath,
            'ogDescription' => $ogDescription,
        ]);
    }

    /**
     * Compose the OG/twitter description string. Plain language so it reads well
     * inside Discord/Twitch link previews, with the unit suffix matching the
     * streamer's configured speed unit.
     *
     * @param  array<string, mixed>  $session
     */
    private function buildOgDescription(array $session, string $speedUnit): string
    {
        $distanceKm = (float) ($session['distance_km'] ?? 0);
        $distanceStr = $speedUnit === 'mph'
            ? number_format($distanceKm / 1.609344, 1).' mi'
            : number_format($distanceKm, 1).' km';

        $startedAt = isset($session['started_at']) ? strtotime((string) $session['started_at']) : false;
        $endedAt = isset($session['ended_at']) ? strtotime((string) $session['ended_at']) : false;
        $durationStr = '';
        if ($startedAt !== false && $endedAt !== false && $endedAt >= $startedAt) {
            $sec = $endedAt - $startedAt;
            $h = intdiv($sec, 3600);
            $m = intdiv($sec % 3600, 60);
            $durationStr = $h > 0 ? "{$h}h {$m}m" : "{$m}m";
        }

        $pings = (int) ($session['ping_count'] ?? 0);

        $parts = array_filter([
            $distanceStr,
            $durationStr,
            $pings > 0 ? number_format($pings).' GPS pings' : null,
        ]);

        return implode(' - ', $parts).'. Live route shared via Overlabels.';
    }

    private function resolveUser(string $slug): User
    {
        $twitchId = $this->slugService->decode($slug);
        if ($twitchId === null) {
            abort(404);
        }

        return User::where('twitch_id', $twitchId)->firstOrFail();
    }
}
