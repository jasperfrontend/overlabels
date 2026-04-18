<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\BotChatOutbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BotOutboxController extends Controller
{
    /**
     * Claim all pending chat messages for the bot to post. Marks rows as sent
     * atomically so two concurrent polls can't double-send. The bot is
     * responsible for actually delivering them - we don't retry on failure
     * because duplicate chat messages are worse than a missed mention.
     */
    public function index(): JsonResponse
    {
        $messages = DB::transaction(function () {
            $rows = BotChatOutbox::query()
                ->whereNull('sent_at')
                ->with('user:id,twitch_data')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($rows->isEmpty()) {
                return collect();
            }

            BotChatOutbox::whereIn('id', $rows->pluck('id'))
                ->update(['sent_at' => now()]);

            return $rows;
        });

        $payload = $messages
            ->map(function (BotChatOutbox $row) {
                $login = $row->user?->twitch_data['login'] ?? null;
                if (! $login) {
                    return null;
                }

                return [
                    'id' => $row->id,
                    'channel_login' => strtolower($login),
                    'message' => $row->message,
                ];
            })
            ->filter()
            ->values();

        return response()->json(['messages' => $payload]);
    }
}
