<?php

namespace App\Http\Controllers;

use App\Services\AlertMuteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AlertMuteController extends Controller
{
    /**
     * Session-authed mute toggle for the dashboard events page. The
     * token-authed sibling lives in Api\EventFeedController::mute().
     */
    public function update(Request $request, AlertMuteService $alertMute): RedirectResponse
    {
        $validated = $request->validate([
            'muted' => 'required|boolean',
        ]);

        $muted = $alertMute->setMuted($request->user(), (bool) $validated['muted']);

        return back()
            ->with('message', $muted ? 'All alerts muted.' : 'Alerts unmuted.')
            ->with('type', $muted ? 'warning' : 'success');
    }
}
