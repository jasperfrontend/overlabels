<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\BotCommand;
use App\Models\BotExpression;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class BotCommandController extends Controller
{
    /**
     * Return the enabled command set for every opted-in user, keyed by
     * lowercased Twitch login. Each entry carries a "type" so the bot can
     * route to the right dispatch path: hardcoded JS handler for builtins,
     * POST to the fire endpoint for expressions.
     *
     * Shape:
     *
     *   {
     *     "jasperdiscovers": [
     *       { "command": "control",  "permission_level": "everyone", "type": "builtin"    },
     *       { "command": "distance", "permission_level": "everyone", "type": "expression" }
     *     ]
     *   }
     *
     * On collision (a user authored an expression with the same command name as
     * a builtin), the builtin wins. Validation should refuse such an expression
     * at save time, but enforcing here too keeps the bot deterministic if a
     * stale row exists.
     */
    public function index(): JsonResponse
    {
        $users = User::where('bot_enabled', true)
            ->whereNotNull('twitch_data')
            ->with(['botCommands' => fn ($q) => $q->where('enabled', true)])
            ->get();

        $expressionsByUser = BotExpression::where('enabled', true)
            ->whereIn('user_id', $users->pluck('id'))
            ->get()
            ->groupBy('user_id');

        $map = [];

        foreach ($users as $user) {
            $login = $user->twitch_data['login'] ?? null;
            if (! $login) {
                continue;
            }

            $entries = $user->botCommands
                ->map(fn (BotCommand $c) => [
                    'command' => $c->command,
                    'permission_level' => $c->permission_level,
                    'type' => 'builtin',
                ])
                ->values()
                ->all();

            $builtinNames = array_column($entries, 'command');

            $userExpressions = $expressionsByUser->get($user->id, collect());
            foreach ($userExpressions as $expr) {
                if (in_array($expr->command, $builtinNames, true)) {
                    continue;
                }
                $entries[] = [
                    'command' => $expr->command,
                    'permission_level' => $expr->permission_level,
                    'type' => 'expression',
                ];
            }

            $map[strtolower($login)] = $entries;
        }

        return response()->json(['channels' => $map]);
    }
}
