<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\BotChatOutbox;
use App\Models\User;
use App\Services\Bot\BotChatAdminService;
use App\Support\BotChatGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs the bot's `!ol <subverb>` chat-admin meta-command. The bot relays
 * a structured payload here; we resolve the channel owner, gate on the
 * chatter's badges (moderator+), hand off to BotChatAdminService for the
 * actual mutation, then queue the human-readable reply into
 * bot_chat_outbox. The bot's outbox poller speaks the reply.
 *
 * Sibling of BotListActionController in shape. Differences:
 *  - No per-channel meta-command row to look up; the verb is `ol` and the
 *    permission is uniform across all users (moderator+).
 *  - One endpoint covers all (subject, action) combinations; the service
 *    dispatches internally.
 */
class BotChatAdminController extends Controller
{
    /** Mod-or-broadcaster. Matches `!list` meta-command policy. */
    private const string PERMISSION_LEVEL = 'moderator';

    public function __construct(
        private readonly BotChatAdminService $service,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel_login' => 'required|string',
            'chatter_id' => 'required|string',
            'chatter_login' => 'required|string',
            'chatter_display_name' => 'nullable|string',
            'badges' => 'nullable|array',
            'badges.*' => 'string',
            // Subject identifies which primitive is being managed; action is
            // the verb. Both validated downstream by the service's dispatch
            // table, so we accept any string here and let the service emit
            // a chat-friendly error for unknown combos.
            'subject' => 'required|string|max:20',
            'action' => 'nullable|string|max:20',
            'name' => 'nullable|string|max:200',
            'payload' => 'nullable|string|max:2200',
            'option' => 'nullable|string|max:40',
            'value' => 'nullable|string|max:40',
        ]);

        $login = strtolower($data['channel_login']);

        $owner = User::where('bot_enabled', true)
            ->whereNotNull('twitch_data')
            ->get()
            ->first(fn (User $u) => strtolower($u->twitch_data['login'] ?? '') === $login);

        if (! $owner) {
            return response()->json(['queued' => false, 'reason' => 'channel_not_found']);
        }

        $badges = array_map('strtolower', $data['badges'] ?? []);
        if (! BotChatGate::hasPermission(self::PERMISSION_LEVEL, $badges)) {
            return response()->json(['queued' => false, 'reason' => 'gate']);
        }

        $reply = $this->service->dispatch($owner, [
            'subject' => $data['subject'],
            'action' => $data['action'] ?? '',
            'name' => $data['name'] ?? '',
            'payload' => $data['payload'] ?? '',
            'option' => $data['option'] ?? '',
            'value' => $data['value'] ?? '',
        ]);

        if ($reply !== '') {
            BotChatOutbox::create([
                'user_id' => $owner->id,
                'message' => $reply,
            ]);
        }

        return response()->json([
            'queued' => true,
            'reply' => $reply,
        ]);
    }
}
