<?php

namespace App\Http\Controllers;

use App\Services\Alerts\AlertsOverlay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Creates the account's Alerts overlay when someone asks for it (the kit
 * page's "Create my Alerts overlay"). Idempotent: an account that already has
 * one gets it back. Alerts do not move into it until OBS has loaded it once.
 */
class AlertsOverlayController extends Controller
{
    public function __invoke(Request $request, AlertsOverlay $alertsOverlay): RedirectResponse
    {
        $alertsOverlay->ensure($request->user());

        return back();
    }
}
