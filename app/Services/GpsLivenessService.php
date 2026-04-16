<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Liveness checks for the overlabels-mobile GPS integration.
 *
 * "Broadcasting" means: there is a session_start event in external_events
 * without a matching session_end AND at least one location_update has been
 * recorded inside that session. The location_update requirement is critical
 * because the mobile app creates a session_start even while the user is inside
 * their configured safe zone, but suppresses location broadcasts until they
 * leave it. Gating on session_start alone would leak the safe zone.
 */
class GpsLivenessService
{
    public function isBroadcasting(int $userId): bool
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
