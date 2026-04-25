<?php

namespace App\Http\Controllers\Settings;

use App\Events\BotChannelsChanged;
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

        $user = $request->user();
        $user->update(['bot_enabled' => $data['enabled']]);

        $login = $user->twitch_data['login'] ?? null;
        if ($login) {
            BotChannelsChanged::dispatch(strtolower($login), (bool) $data['enabled']);
        }

        return back();
    }
}
