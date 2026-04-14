<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BotSettingsController extends Controller
{
    public function setEnabled(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $request->user()->update(['bot_enabled' => $data['enabled']]);

        return back();
    }
}
