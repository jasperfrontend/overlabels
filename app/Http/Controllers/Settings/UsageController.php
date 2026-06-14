<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\BroadcastMeter;
use App\Services\EventMeter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsageController extends Controller
{
    public function __construct(
        private readonly EventMeter $eventMeter,
        private readonly BroadcastMeter $broadcastMeter,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        // 'broadcast' mode still shows the legacy output count; otherwise the
        // displayed usage is inbound events (the unit pricing is set against).
        if (config('metering.meter_mode', 'both') === 'broadcast') {
            $twitchId = (string) $user->twitch_id;

            return Inertia::render('settings/Usage', [
                'usage' => $this->broadcastMeter->summaryFor($twitchId),
                'history' => $this->broadcastMeter->historyFor($twitchId, 6),
            ]);
        }

        return Inertia::render('settings/Usage', [
            'usage' => $this->eventMeter->summaryFor($user->id),
            'history' => $this->eventMeter->historyFor($user->id, 6),
        ]);
    }
}
