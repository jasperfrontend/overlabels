<?php

namespace App\Services\Lists;

use App\Events\ListUpdated;
use App\Models\BotChatOutbox;
use App\Models\ListAppender;
use App\Models\ListAppendHistory;
use App\Models\OptionSet;
use App\Models\User;
use App\Services\Bot\BotExpressionResolver;
use App\Services\Bot\BotExpressionService;
use App\Services\StreamSessionService;
use App\Support\BotChatGate;
use App\Support\ListItemTimestamps;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors the canFire / fire pattern of BotExpressionService and
 * RecipeChatTriggerService. fire() appends a resolved string value to
 * the linked OptionSet (a List), respecting dedup policy and max_size,
 * and records every successful fire in list_append_history.
 *
 * The value to append goes through BotExpressionResolver so the
 * template language is identical to Bot Expressions ([[[bot:from_user]]],
 * [[[c:foo:bar]]], pipe formatters, etc.). args_empty_reply, when
 * present, is resolved the same way and queued into bot_chat_outbox.
 */
class ListAppendService
{
    public function __construct(
        private readonly BotExpressionResolver $resolver,
        private readonly BotExpressionService $expressionService,
    ) {}

    /**
     * @param  array<int,string>  $badges  Lowercased IRC badge names
     */
    public function canFire(ListAppender $appender, array $badges): bool
    {
        if (! $appender->enabled) {
            return false;
        }

        if (! BotChatGate::hasPermission($appender->permission_level, $badges)) {
            return false;
        }

        if (BotChatGate::isBroadcaster($badges)) {
            return true;
        }

        return BotChatGate::isOffCooldown($appender->last_fired_at, $appender->cooldown_seconds);
    }

    /**
     * Fire the appender. Caller is expected to have gated via canFire().
     *
     * Possible return shapes:
     *   ['fired' => true, 'value' => string]                            - appended
     *   ['fired' => false, 'reason' => 'args_empty', 'reply' => string] - empty args, reply queued to outbox
     *   ['fired' => false, 'reason' => 'args_empty']                    - empty args, silent
     *   ['fired' => false, 'reason' => 'list_full']
     *   ['fired' => false, 'reason' => 'already_in_list']
     *   ['fired' => false, 'reason' => 'list_gone']
     *
     * @param  array<string,mixed>  $payload  bot payload (chatter_id, chatter_login, chatter_display_name, args, channel_login)
     * @return array<string,mixed>
     */
    public function fire(ListAppender $appender, User $user, array $payload): array
    {
        return DB::transaction(function () use ($appender, $user, $payload) {
            /** @var OptionSet|null $list */
            $list = OptionSet::lockForUpdate()->find($appender->target_list_id);
            if (! $list) {
                return ['fired' => false, 'reason' => 'list_gone'];
            }

            // Disabled lists silently refuse appends - the streamer
            // disabled the list intentionally, no need to apologise
            // in chat. Existing items stay visible to overlays; only
            // new chat-driven appends are blocked. Streamer can
            // still curate manually via /dashboard/lists.
            if ($list->disabled_at !== null) {
                return ['fired' => false, 'reason' => 'list_disabled'];
            }

            $context = $this->expressionService->buildBotContext($appender->command, $payload);
            $args = (string) ($context['args'] ?? '');

            // Args-empty gate: only triggers when the template references
            // [[[bot:args]]] in some form AND the chatter didn't supply
            // any. A template that doesn't use args (e.g. just
            // [[[bot:from_user]]] for a raffle) ignores this check.
            $templateUsesArgs = str_contains($appender->value_template, '[[[bot:args');
            if ($templateUsesArgs && $args === '') {
                return $this->handleArgsEmpty($appender, $user, $context);
            }

            $resolvedValue = $this->resolver->resolve($user, $appender->value_template, $context);

            $currentItems = $list->items ?? [];

            // Max-size silent refuse. We refuse rather than truncate so
            // a streamer running a 100-slot raffle gets a predictable
            // "the list filled up at slot 100" rather than mystery
            // missing late entrants.
            if ($appender->max_size !== null && count($currentItems) >= $appender->max_size) {
                return ['fired' => false, 'reason' => 'list_full'];
            }

            $chatterId = (string) ($context['from_user_id'] ?? '');
            $streamSessionId = $user->streamState?->current_session_id;

            if (! $this->passesDedup($appender, $chatterId, $streamSessionId)) {
                return ['fired' => false, 'reason' => 'already_in_list'];
            }

            $newItems = array_merge(array_values($currentItems), [$resolvedValue]);
            $newTimestamps = ListItemTimestamps::append($list->item_added_at ?? []);
            $list->update([
                'items' => $newItems,
                'item_added_at' => $newTimestamps,
            ]);

            ListAppendHistory::create([
                'list_appender_id' => $appender->id,
                'target_list_id' => $list->id,
                'chatter_id' => $chatterId,
                'chatter_login' => (string) ($context['from_user_login'] ?? ''),
                'value' => $resolvedValue,
                'stream_session_id' => $streamSessionId,
                'fired_at' => now(),
            ]);

            $appender->forceFill(['last_fired_at' => Carbon::now()])->save();

            ListUpdated::dispatchFor((string) $user->twitch_id, $list->fresh());

            return ['fired' => true, 'value' => $resolvedValue];
        });
    }

    /**
     * Args-empty branch: queue the rejection reply (if configured) and
     * return without appending. Reply uses the same template language
     * as value_template - it'll resolve [[[bot:from_user]]] etc.
     *
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function handleArgsEmpty(ListAppender $appender, User $user, array $context): array
    {
        if (! $appender->args_empty_reply) {
            return ['fired' => false, 'reason' => 'args_empty'];
        }

        $reply = $this->resolver->resolve($user, $appender->args_empty_reply, $context);
        if ($reply !== '') {
            BotChatOutbox::create([
                'user_id' => $user->id,
                'message' => $reply,
            ]);
        }

        return ['fired' => false, 'reason' => 'args_empty', 'reply' => $reply];
    }

    /**
     * Dedup check. Returns true if the fire is allowed to proceed.
     */
    private function passesDedup(ListAppender $appender, string $chatterId, ?int $streamSessionId): bool
    {
        if ($appender->dedup_policy === ListAppender::DEDUP_NONE || $chatterId === '') {
            return true;
        }

        $query = ListAppendHistory::where('list_appender_id', $appender->id)
            ->where('chatter_id', $chatterId);

        // per_chatter_per_stream scoped to current session WHEN we have
        // one. Stream offline -> fall back to lifetime per_chatter so
        // an offline test doesn't sneak duplicates in.
        if ($appender->dedup_policy === ListAppender::DEDUP_PER_CHATTER_PER_STREAM && $streamSessionId !== null) {
            $query->where('stream_session_id', $streamSessionId);
        }

        return ! $query->exists();
    }
}
