<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotSettingsController extends Controller
{
    /**
     * Flip the controls_enabled flag for a streamer. Exposed via
     * !enablecontrols / !disablecontrols in chat; the bot handlers POST
     * here with {enabled: bool}. Returns 404 for unknown channels so the
     * bot silently drops the call rather than advertising that the channel
     * is opted-in but misrouted.
     */
    public function setControlsAccess(Request $request, string $login): JsonResponse
    {
        $data = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $user = $this->resolveUser($login);
        if (! $user) {
            return response()->json(['reply' => null], 404);
        }

        $user->setBotSetting('controls_enabled', (bool) $data['enabled']);

        return response()->json([
            'reply' => $data['enabled']
                ? 'chat control commands are now enabled'
                : 'chat control commands are now disabled',
        ]);
    }

    private function resolveUser(string $login): ?User
    {
        $login = strtolower($login);

        return User::where('bot_enabled', true)
            ->whereNotNull('twitch_data')
            ->get()
            ->first(fn (User $u) => strtolower($u->twitch_data['login'] ?? '') === $login);
    }
}
