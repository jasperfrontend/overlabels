<?php

namespace App\Http\Controllers\Settings;

use App\Events\BotChannelsChanged;
use App\Http\Controllers\Controller;
use App\Models\BotAlias;
use App\Models\BotBuiltin;
use App\Models\BotCommand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BotSettingsController extends Controller
{
    /**
     * The bot has a settings page like every other integration, so the
     * integrations list can stay one flat list of rows. Counts are
     * enabled-only: what the bot actually answers in chat is what the command
     * map publishes, and that filters on `enabled`.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/integrations/bot', [
            'bot' => [
                'enabled' => (bool) $user->bot_enabled,
                'command_count' => BotCommand::where('user_id', $user->id)->where('enabled', true)->count(),
                'alias_count' => BotAlias::where('user_id', $user->id)->where('enabled', true)->count(),
                'builtin_count' => BotBuiltin::where('user_id', $user->id)->where('enabled', true)->count(),
            ],
        ]);
    }

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
