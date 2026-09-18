<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Services\ViewerErasureService;
use App\Services\ViewerNoticeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BotForgetMeController extends Controller
{
    /**
     * Handle one `!forgetme` from chat.
     *
     * Deliberately NOT scoped to a channel, unlike every other bot endpoint.
     * A viewer typing this is asking Overlabels to stop holding them, not
     * asking one streamer for a favour, so it runs platform-wide and takes no
     * `{login}`. That is also why the builtin is owned by the platform rather
     * than the streamer: a channel must not be able to switch off the only
     * chat route a viewer has to us.
     *
     * The bot has already checked the command map, so anything reaching here
     * is from a channel that has the bot. No live gate, no cooldown on the
     * useful path: someone asking to be erased should never be told to come
     * back when the stream is live.
     */
    public function handle(Request $request, ViewerErasureService $erasures, ViewerNoticeService $notices): JsonResponse
    {
        $data = $request->validate([
            'chatter_id' => 'required|string|max:32',
            'chatter_login' => 'nullable|string|max:64',
            'chatter_display_name' => 'nullable|string|max:64',
        ]);

        $removed = $erasures->erase(
            $data['chatter_id'],
            $data['chatter_login'] ?? null,
            $data['chatter_display_name'] ?? null,
        );

        // They should be told again if they ever come back, rather than being
        // treated as already informed.
        $notices->forget($data['chatter_id']);

        // Counts only. Logging which viewer asked to be forgotten, by name,
        // would be a small monument to the thing they just asked us to stop.
        Log::info('Viewer erasure completed', $removed);

        $total = array_sum($removed);

        return response()->json([
            'reply' => $this->replyFor($total),
            'removed' => $removed,
        ]);
    }

    /**
     * The reply is spoken in public chat, so it says what happened without
     * repeating anything about the person. It also points at the one thing
     * this cannot reach: a streamer's own Lists are their content, and are
     * theirs to edit.
     */
    private function replyFor(int $total): string
    {
        $base = $total > 0
            ? "Done - everything Overlabels held about you is deleted, and we won't store you again."
            : "Done - Overlabels held nothing about you, and we won't store you again.";

        return $base.' Anything on a streamer\'s own list, ask them. overlabels.com/viewers';
    }
}
