<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\BroadcastMeter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsageController extends Controller
{
    public function __construct(private readonly BroadcastMeter $meter) {}

    public function index(Request $request): Response
    {
        $twitchId = (string) $request->user()->twitch_id;

        return Inertia::render('settings/Usage', [
            'usage' => $this->meter->summaryFor($twitchId),
            'history' => $this->meter->historyFor($twitchId, 6),
        ]);
    }
}
