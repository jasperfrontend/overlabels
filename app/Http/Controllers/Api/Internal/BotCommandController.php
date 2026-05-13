<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\BotCommand;
use App\Models\BotExpression;
use App\Models\ListAppender;
use App\Models\RecipeChatTrigger;
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
     *       { "command": "control",  "permission_level": "everyone", "type": "builtin"        },
     *       { "command": "distance", "permission_level": "everyone", "type": "expression"     },
     *       { "command": "flip",     "permission_level": "everyone", "type": "recipe_trigger" },
     *       { "command": "raffle",   "permission_level": "everyone", "type": "list_append"    }
     *     ]
     *   }
     *
     * Resolution order on command-name collision:
     *   builtin > expression > recipe_trigger > list_append.
     * Validation at save / install time should refuse colliding rows, but enforcing
     * the order here keeps the bot deterministic if a stale row sneaks through.
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

        $triggersByUser = RecipeChatTrigger::where('enabled', true)
            ->whereIn('user_id', $users->pluck('id'))
            ->get()
            ->groupBy('user_id');

        $appendersByUser = ListAppender::where('enabled', true)
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

            $claimed = array_column($entries, 'command');

            foreach ($expressionsByUser->get($user->id, collect()) as $expr) {
                if (in_array($expr->command, $claimed, true)) {
                    continue;
                }
                $entries[] = [
                    'command' => $expr->command,
                    'permission_level' => $expr->permission_level,
                    'type' => 'expression',
                ];
                $claimed[] = $expr->command;
            }

            foreach ($triggersByUser->get($user->id, collect()) as $trigger) {
                if (in_array($trigger->command, $claimed, true)) {
                    continue;
                }
                $entries[] = [
                    'command' => $trigger->command,
                    'permission_level' => $trigger->permission_level,
                    'type' => 'recipe_trigger',
                ];
                $claimed[] = $trigger->command;
            }

            foreach ($appendersByUser->get($user->id, collect()) as $appender) {
                if (in_array($appender->command, $claimed, true)) {
                    continue;
                }
                $entries[] = [
                    'command' => $appender->command,
                    'permission_level' => $appender->permission_level,
                    'type' => 'list_append',
                ];
                $claimed[] = $appender->command;
            }

            $map[strtolower($login)] = $entries;
        }

        return response()->json(['channels' => $map]);
    }
}
