<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\BotCommand;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class BotCommandController extends Controller
{
    /**
     * Return the enabled command set for every opted-in user, keyed by
     * lowercased Twitch login. Shape:
     *
     *   {
     *     "jasperdiscovers": [
     *       { "command": "control", "permission_level": "everyone" },
     *       ...
     *     ]
     *   }
     */
    public function index(): JsonResponse
    {
        $users = User::where('bot_enabled', true)
            ->whereNotNull('twitch_data')
            ->with(['botCommands' => fn ($q) => $q->where('enabled', true)])
            ->get();

        $map = [];

        foreach ($users as $user) {
            $login = $user->twitch_data['login'] ?? null;
            if (! $login) {
                continue;
            }

            $map[strtolower($login)] = $user->botCommands
                ->map(fn (BotCommand $c) => [
                    'command' => $c->command,
                    'permission_level' => $c->permission_level,
                ])
                ->values()
                ->all();
        }

        return response()->json(['channels' => $map]);
    }
}
