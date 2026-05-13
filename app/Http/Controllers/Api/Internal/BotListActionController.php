<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\BotChatOutbox;
use App\Models\ListMetaCommand;
use App\Models\User;
use App\Services\Lists\ListActionService;
use App\Support\BotChatGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Sibling of BotExpressionController / BotRecipeTriggerController /
 * BotListAppenderController. The bot POSTs here when a chatter fires
 * the user's `!list` meta-command. We gate (enabled + permission), hand
 * the args to ListActionService, then queue the resulting reply string
 * to bot_chat_outbox. The bot's outbox poller speaks it.
 *
 * Permission is fixed at ListMetaCommand::PERMISSION_LEVEL (moderator+)
 * because the vocabulary is destructive or chat-emitting - this is not
 * an "everyone" surface.
 */
class BotListActionController extends Controller
{
    public function __construct(
        private readonly ListActionService $service,
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

        $owner = User::where('bot_enabled', true)
            ->whereNotNull('twitch_data')
            ->get()
            ->first(fn (User $u) => strtolower($u->twitch_data['login'] ?? '') === $login);

        if (! $owner) {
            return response()->json(['fired' => false, 'reason' => 'channel_not_found']);
        }

        $meta = ListMetaCommand::where('user_id', $owner->id)
            ->where('command', $command)
            ->first();

        if (! $meta || ! $meta->enabled) {
            return response()->json(['fired' => false, 'reason' => 'meta_not_found']);
        }

        $badges = array_map('strtolower', $data['badges'] ?? []);

        // Mod-or-broadcaster requirement. canFire-style logic without
        // a cooldown - the action vocabulary self-rate-limits via the
        // dashboard / chat invocations being a streamer concern.
        if (! BotChatGate::hasPermission(ListMetaCommand::PERMISSION_LEVEL, $badges)) {
            return response()->json(['fired' => false, 'reason' => 'gate']);
        }

        $invoker = (string) ($data['chatter_display_name'] ?? $data['chatter_login'] ?? '');
        $reply = $this->service->handleInvocation($owner, (string) ($data['args'] ?? ''), $invoker);

        if ($reply !== '') {
            BotChatOutbox::create([
                'user_id' => $owner->id,
                'message' => $reply,
            ]);
        }

        $meta->forceFill(['last_fired_at' => Carbon::now()])->save();

        return response()->json([
            'fired' => true,
            'reply' => $reply,
        ]);
    }
}
