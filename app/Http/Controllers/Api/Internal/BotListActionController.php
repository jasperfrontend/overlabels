<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\BotChatOutbox;
use App\Models\ListMetaCommand;
use App\Models\User;
use App\Services\Lists\ListActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Sibling of BotExpressionController / BotRecipeTriggerController /
 * BotListAppenderController. The bot POSTs here when a chatter fires
 * the user's `!list` meta-command. We confirm the meta-command exists
 * and is enabled, hand the args + chatter badges to ListActionService,
 * and queue the resulting reply string to bot_chat_outbox.
 *
 * Per-action permission gating lives in ListActionService - each list
 * keeps its own action->level map in OptionSet->chat_permissions.
 * Defaults stay at moderator+ to match the pre-migration single gate,
 * but streamers can open individual actions (typically count / random /
 * search / searchall) to viewers per list.
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
        $invoker = (string) ($data['chatter_display_name'] ?? $data['chatter_login'] ?? '');

        // Per-action gating happens inside the service - it knows which
        // action was typed and what level the target list requires for
        // that action. The bot's command-map entry announces 'everyone'
        // for list_meta so viewer messages reach us; the service either
        // runs the action or returns a friendly mods-only reply.
        $reply = $this->service->handleInvocation(
            $owner,
            (string) ($data['args'] ?? ''),
            $invoker,
            $badges,
        );

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
