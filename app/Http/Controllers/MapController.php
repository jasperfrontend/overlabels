<?php

namespace App\Http\Controllers;

use App\Models\ExternalIntegration;
use App\Models\User;
use Illuminate\View\View;

class MapController extends Controller
{
    /**
     * GET /map/{twitchId}
     * Public live map page.
     */
    public function live(string $twitchId): View
    {
        $user = User::where('twitch_id', $twitchId)->firstOrFail();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'overlabels-mobile')
            ->where('enabled', true)
            ->first();

        if (! $integration || empty(($integration->settings ?? [])['map_sharing_enabled'])) {
            abort(404);
        }

        $delay = (int) (($integration->settings ?? [])['map_delay_seconds'] ?? 0);
        $speedUnit = ($integration->settings ?? [])['speed_unit'] ?? 'kmh';

        return view('map.live', [
            'twitchId' => $twitchId,
            'streamerName' => $user->name,
            'delay' => $delay,
            'speedUnit' => $speedUnit,
        ]);
    }

    /**
     * GET /map/{twitchId}/{sessionId}
     * Public saved session map page.
     */
    public function session(string $twitchId, string $sessionId): View
    {
        $user = User::where('twitch_id', $twitchId)->firstOrFail();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'overlabels-mobile')
            ->where('enabled', true)
            ->first();

        if (! $integration || empty(($integration->settings ?? [])['map_sharing_enabled'])) {
            abort(404);
        }

        $speedUnit = ($integration->settings ?? [])['speed_unit'] ?? 'kmh';

        return view('map.session', [
            'twitchId' => $twitchId,
            'sessionId' => $sessionId,
            'streamerName' => $user->name,
            'speedUnit' => $speedUnit,
        ]);
    }
}
