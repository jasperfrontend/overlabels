<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Services\ViewerErasureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BotUnforgetMeController extends Controller
{
    /** Holds a Twitch id for the confirm window and nothing else. */
    private const PENDING_PREFIX = 'unforgetme:pending:';

    public const ASK_REPLY = 'This lets Overlabels store you again, like anyone else, in every channel. Nothing deleted before comes back.'
        .' To go ahead, type !unforgetme confirm within 2 minutes.';

    /**
     * Handle one `!unforgetme` from chat: the way back from `!forgetme`.
     *
     * Platform-wide and channel-less for the same reason `!forgetme` is (see
     * BotForgetMeController), and platform-owned so no channel can close it.
     * Same two steps too, same word and window: undoing an opt-out is a choice
     * about being stored, and it should be made on purpose.
     *
     * Someone who is not suppressed is told so at once rather than asked to
     * confirm nothing. That reveals no more than they did by typing it.
     *
     * Only the viewer themselves can do this. The id comes from Twitch's own
     * chat event, so nobody can type it on someone else's behalf.
     */
    public function handle(Request $request, ViewerErasureService $erasures): JsonResponse
    {
        $data = $request->validate([
            'chatter_id' => 'required|string|max:32',
            'answer' => 'nullable|string|max:64',
        ]);

        if (! $erasures->isSuppressed($data['chatter_id'])) {
            return response()->json(['reply' => 'Nothing to undo - Overlabels was not ignoring you.', 'lifted' => false]);
        }

        $confirming = mb_strtolower(trim($data['answer'] ?? '')) === BotForgetMeController::CONFIRM_WORD;
        $pendingKey = self::PENDING_PREFIX.$data['chatter_id'];

        if (! $confirming || ! Cache::pull($pendingKey)) {
            Cache::put($pendingKey, true, BotForgetMeController::CONFIRM_WINDOW);

            return response()->json(['reply' => self::ASK_REPLY, 'lifted' => null]);
        }

        $lifted = $erasures->unerase($data['chatter_id']);

        // A boolean, never the viewer. Same rule as the erasure log line.
        Log::info('Viewer erasure lifted', ['lifted' => $lifted]);

        return response()->json([
            'reply' => 'Done - Overlabels can store you again, like anyone else. What was deleted before stays deleted.',
            'lifted' => $lifted,
        ]);
    }
}
