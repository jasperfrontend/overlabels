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

        // Safety gate: only show live position when there's a session_start without
        // a matching session_end AND at least one location_update in that session.
        // The location_update requirement is critical — the app always creates a
        // session_start even when the user is inside their safe zone, but suppresses
        // location broadcasts until they leave it. Without requiring a location_update,
        // the map would render centered on the last known position (often their home)
        // as soon as they start a session inside the safe zone.
        $isLive = $this->hasActiveSessionWithLocation($user->id);

        $delay = (int) (($integration->settings ?? [])['map_delay_seconds'] ?? 0);
        $speedUnit = ($integration->settings ?? [])['speed_unit'] ?? 'kmh';

        return view('map.live', [
            'twitchId' => $twitchId,
            'streamerName' => $user->name,
            'delay' => $delay,
            'speedUnit' => $speedUnit,
            'isLive' => $isLive,
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

    /**
     * True if the user has a session_start without a matching session_end
     * and at least one location_update inside that same session.
     */
    private function hasActiveSessionWithLocation(int $userId): bool
    {
        return DB::table('external_events as s')
            ->where('s.service', 'overlabels-mobile')
            ->where('s.user_id', $userId)
            ->where('s.event_type', 'session_start')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('external_events as e')
                    ->whereColumn('e.user_id', 's.user_id')
                    ->where('e.service', 'overlabels-mobile')
                    ->where('e.event_type', 'session_end')
                    ->whereRaw("e.raw_payload->>'session_id' = s.raw_payload->>'session_id'");
            })
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('external_events as e')
                    ->whereColumn('e.user_id', 's.user_id')
                    ->where('e.service', 'overlabels-mobile')
                    ->where('e.event_type', 'location_update')
                    ->whereRaw("e.raw_payload->>'session_id' = s.raw_payload->>'session_id'");
            })
            ->exists();
    }
}
