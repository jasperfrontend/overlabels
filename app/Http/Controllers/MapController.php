<?php

namespace App\Http\Controllers;

use App\Models\ExternalIntegration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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

        // Safety gate: only render the live map if there's an unfinished GPS session
        // (session_start without a matching session_end). Without this, the map would
        // stay frozen on the last broadcast position after the user stops streaming,
        // which is a potential doxxing vector if their safe zone isn't configured.
        $hasActiveSession = DB::table('external_events as s')
            ->where('s.service', 'overlabels-mobile')
            ->where('s.user_id', $user->id)
            ->where('s.event_type', 'session_start')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('external_events as e')
                    ->whereColumn('e.user_id', 's.user_id')
                    ->where('e.service', 'overlabels-mobile')
                    ->where('e.event_type', 'session_end')
                    ->whereRaw("e.raw_payload->>'session_id' = s.raw_payload->>'session_id'");
            })
            ->exists();

        if (! $hasActiveSession) {
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
