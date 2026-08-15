<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\BotChatOutbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BotOutboxController extends Controller
{
    /**
     * Claim all pending chat messages for the bot to post. Marks rows as sent
     * atomically so two concurrent polls can't double-send. The bot is
     * responsible for actually delivering them - we don't retry on failure
     * because duplicate chat messages are worse than a missed mention.
     *
     * ANYTHING STALE IS DROPPED HERE, NOT DELIVERED. This is the moment of
     * decision: if the bot was down for six hours, the rows waiting for it are
     * answers to questions nobody remembers asking, and posting them all at
     * once on reconnect is worse than staying quiet. Rows older than
     * BotChatOutbox::STALE_AFTER_SECONDS are marked discarded and never handed
     * out. A daily prune cannot do this job - the bot reconnects and claims
     * long before the sweep runs.
     *
     * Discarded rather than deleted so the drop is visible for as long as the
     * prune keeps it, and so a run of discards can be spotted for what it is:
     * the bot flapping.
     *
     * @throws Throwable
     */
    public function index(): JsonResponse
    {
        $messages = DB::transaction(function () {
            $rows = BotChatOutbox::query()
                ->whereNull('sent_at')
                ->whereNull('discarded_at')
                ->with('user:id,twitch_data')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($rows->isEmpty()) {
                return collect();
            }

            // Split on age inside the same transaction and the same row set, so
            // a message can never be both delivered and discarded, and nothing
            // is left pending for a later poll to pick up.
            $cutoff = now()->subSeconds(BotChatOutbox::STALE_AFTER_SECONDS);
            [$fresh, $stale] = $rows->partition(fn (BotChatOutbox $row) => $row->created_at >= $cutoff);

            if ($stale->isNotEmpty()) {
                BotChatOutbox::whereIn('id', $stale->pluck('id'))
                    ->update(['discarded_at' => now()]);

                Log::info('bot_outbox.discarded_stale', [
                    'count' => $stale->count(),
                    'oldest_seconds' => (int) $stale->min('created_at')?->diffInSeconds(now()),
                ]);
            }

            if ($fresh->isEmpty()) {
                return collect();
            }

            BotChatOutbox::whereIn('id', $fresh->pluck('id'))
                ->update(['sent_at' => now()]);

            return $fresh;
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
