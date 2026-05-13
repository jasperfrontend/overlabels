<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\ListAppender;
use App\Models\User;
use App\Services\Lists\ListAppendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sibling of BotExpressionController and BotRecipeTriggerController. The
 * bot POSTs here when a chatter fires a command flagged in the synced
 * commandMap as type=list_append. We gate (enabled / permission /
 * cooldown), then call ListAppendService::fire which resolves the
 * value_template, runs dedup + max_size checks, appends to the list,
 * records history, and broadcasts ListUpdated.
 *
 * Silent on chat by default. If the appender has args_empty_reply
 * configured and the fire bounces because args were empty, the resolved
 * reply is queued into bot_chat_outbox - the bot's outbox poller picks
 * it up and speaks it. Bot stays dumb either way.
 */
class BotListAppenderController extends Controller
{
    public function __construct(
        private readonly ListAppendService $service,
    ) {}

    public function fire(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel_login' => 'required|string',
            'command' => 'required|string|max:30',
            'chatter_id' => 'required|string',
            'chatter_login' => 'required|string',
            'chatter_display_name' => 'nullable|string',
            'badges' => 'nullable|array',
            'badges.*' => 'string',
            'args' => 'nullable|string',
        ]);

        $login = strtolower($data['channel_login']);
        $command = ltrim($data['command'], '!');

        $user = User::where('bot_enabled', true)
            ->whereNotNull('twitch_data')
            ->get()
            ->first(fn (User $u) => strtolower($u->twitch_data['login'] ?? '') === $login);

        if (! $user) {
            return response()->json(['fired' => false, 'reason' => 'channel_not_found']);
        }

        $appender = ListAppender::where('user_id', $user->id)
            ->where('command', $command)
            ->first();

        if (! $appender) {
            return response()->json(['fired' => false, 'reason' => 'appender_not_found']);
        }

        $badges = array_map('strtolower', $data['badges'] ?? []);

        if (! $this->service->canFire($appender, $badges)) {
            return response()->json(['fired' => false, 'reason' => 'gate']);
        }

        $result = $this->service->fire($appender, $user, $data);

        return response()->json($result);
    }
}
