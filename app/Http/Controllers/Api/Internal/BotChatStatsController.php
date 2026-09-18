<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StreamSessionService;
use App\Services\ViewerErasureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotChatStatsController extends Controller
{
    public function __construct(
        private readonly StreamSessionService $sessions,
        private readonly ViewerErasureService $erasures,
    ) {}

    /**
     * Accept one aggregated chat summary for a channel and apply it to the
     * four chat controls.
     *
     * The bot aggregates in memory and POSTs a summary every 30-60s; it does
     * NOT call this per message. Rejected alternative: Laravel subscribing to
     * channel.chat.message by webhook, which is one synchronous POST per
     * message into a handler doing 6-10 DB ops on a shared 4 GB box, and where
     * a slow webhook risks Twitch disabling the user's OTHER subscriptions via
     * notification_failures_exceeded.
     *
     * `chatters` is the distinct logins seen in THIS window. Stream-scoped
     * uniqueness is resolved server-side - the bot is a thin relay and has no
     * notion of where a stream starts.
     *
     * `message_count` and `chatters` must be native-only: Shared Chat messages
     * duplicated in from another channel are excluded by the bot so a collab
     * does not inflate the streamer's numbers.
     */
    public function store(Request $request, string $login): JsonResponse
    {
        $data = $request->validate([
            'message_count' => 'required|integer|min:0|max:1000000',
            'chatters' => 'sometimes|array|max:10000',
            'chatters.*' => 'string|max:64',
            // Twitch caps a chat message at 500 characters.
            'latest_chatter_name' => 'nullable|string|max:64',
            'latest_chat_message' => 'nullable|string|max:500',
            // Optional, and only used to honour !forgetme. An older bot does
            // not send it, in which case the pair below is written as before.
            'latest_chatter_id' => 'nullable|string|max:32',
        ]);

        $user = $this->resolveUser($login);

        if (! $user) {
            return response()->json(['error' => 'channel not found'], 404);
        }

        // A viewer who used !forgetme keeps chatting - nothing about erasure
        // removes them from a channel - but their name and their message must
        // not be written into a control that puts them back on screen. The
        // count still moves: it is a number, not a person.
        $erased = $this->erasures->isSuppressed($data['latest_chatter_id'] ?? null);

        $result = $this->sessions->applyChatSummary(
            $user,
            $data['message_count'],
            $data['chatters'] ?? [],
            $erased ? null : ($data['latest_chatter_name'] ?? null),
            $erased ? null : ($data['latest_chat_message'] ?? null),
        );

        // 200 with applied=false when the channel is not confidently live. The
        // bot must not retry - the summary is genuinely not wanted, and a 4xx
        // here would look like a bug worth retrying.
        return response()->json($result);
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
