<?php

namespace App\Http\Controllers;

use App\Models\ExternalIntegration;
use App\Models\User;
use App\Services\GpsLivenessService;
use App\Services\MapSlugService;
use Illuminate\View\View;

class MapController extends Controller
{
    public function __construct(
        private GpsLivenessService $liveness,
        private MapSlugService $slugService,
    ) {}

    /**
     * GET /map/{slug}
     * Public live map page. Slug is a Sqids-encoded Twitch ID.
     */
    public function live(string $slug): View
    {
        $user = $this->resolveUser($slug);

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'overlabels-mobile')
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
            ->where('service', 'overlabels-mobile')
            ->where('enabled', true)
            ->first();

        if (! $integration || empty(($integration->settings ?? [])['map_sharing_enabled'])) {
            abort(404);
        }

        $speedUnit = ($integration->settings ?? [])['speed_unit'] ?? 'kmh';

        return view('map.session', [
            'slug' => $slug,
            'sessionId' => $sessionId,
            'streamerName' => $user->name,
            'speedUnit' => $speedUnit,
        ]);
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
