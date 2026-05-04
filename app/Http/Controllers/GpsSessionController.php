<?php

namespace App\Http\Controllers;

use App\Models\ExternalIntegration;
use App\Services\GpsSessionAggregator;
use App\Services\MapSlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GpsSessionController extends Controller
{
    public function __construct(private GpsSessionAggregator $aggregator) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'gps')
            ->first();

        $settings = $integration?->settings ?? [];
        $speedUnit = $settings['speed_unit'] ?? 'kmh';
        $mapSharingEnabled = ! empty($settings['map_sharing_enabled']);

        $sessions = $this->aggregator->forUser($user->id);

        return Inertia::render('dashboard/gps-sessions', [
            'sessions' => $sessions,
            'speedUnit' => $speedUnit,
            'mapSharingEnabled' => $mapSharingEnabled,
            'mapSlug' => app(MapSlugService::class)->encode($user->twitch_id),
        ]);
    }

    /**
     * DELETE /dashboard/gps-sessions/{sessionId}
     * Remove all events for a given session.
     */
    public function destroy(string $sessionId): JsonResponse
    {
        $userId = auth()->id();

        $deleted = DB::table('external_events')
            ->where('service', 'gps')
            ->where('user_id', $userId)
            ->whereRaw("raw_payload->>'session_id' = ?", [$sessionId])
            ->delete();

        if ($deleted === 0) {
            return response()->json(['error' => 'Session not found.'], 404);
        }

        // Clear cached GeoJSON for this session
        Cache::forget("gps_session_geojson_{$userId}_$sessionId");

        return response()->json(['status' => 'ok', 'deleted' => $deleted]);
    }
}
