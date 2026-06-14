<?php

namespace App\Listeners;

use App\Events\ControlValuesBatchUpdated;
use App\Events\ControlValueUpdated;
use App\Events\ListUpdated;
use App\Models\OptionSet;
use App\Models\OverlayControl;
use App\Models\User;
use App\Support\ListItems;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Listens for ControlValueUpdated and finds every list_writer control
 * owned by the same user whose `source_control_id` config points at the
 * updating control. For each match, appends the updating control's new
 * value to the writer's target list.
 *
 * Works for any source control type - including expression. Expression
 * Controls land here after RecomputeExpressionControls has run them
 * through the sidecar and re-dispatched the event with the new computed
 * value. We don't care about the `alreadyRecomputed` flag; whether the
 * value came from a raw update or a recompute cascade, our job is the
 * same: append the new value to the bound list.
 *
 * FIFO drop on max_items so the streamer's "keep latest N" intent is
 * honoured (chat appenders silently refuse on overflow because there's
 * a chatter waiting; control-driven appends have no human to apologise
 * to).
 */
class ListWriterAppend
{
    public function handle(ControlValueUpdated $event): void
    {
        $user = User::where('twitch_id', $event->broadcasterId)->first();
        if (! $user) {
            return;
        }

        $this->process($user, $event->key, $event->value, $event->overlaySlug);
    }

    /**
     * Batched service-control updates (one GPS ping, one donation) arrive as a
     * single event carrying many changed keys. Append for each.
     */
    public function handleBatch(ControlValuesBatchUpdated $event): void
    {
        $user = User::where('twitch_id', $event->broadcasterId)->first();
        if (! $user) {
            return;
        }

        foreach ($event->updates as $update) {
            $key = $update['key'] ?? null;
            if (! is_string($key) || $key === '') {
                continue;
            }
            $this->process($user, $key, (string) ($update['value'] ?? ''), (string) ($update['overlay_slug'] ?? ''));
        }
    }

    /**
     * Find every list_writer control bound to the updating control and append
     * the new value to each writer's target list.
     */
    private function process(User $user, string $key, string $value, string $overlaySlug): void
    {
        $sourceControl = $this->findSourceControl($user, $key, $overlaySlug);
        if (! $sourceControl) {
            return;
        }

        // Find list_writer controls pointing at this source. The pivot is
        // stored on config->source_control_id (int). whereJsonContains
        // matches scalar values too on jsonb.
        $writers = OverlayControl::where('user_id', $user->id)
            ->where('type', 'list_writer')
            ->whereJsonContains('config->source_control_id', $sourceControl->id)
            ->get();

        if ($writers->isEmpty()) {
            return;
        }

        foreach ($writers as $writer) {
            $targetId = $writer->listWriterTargetId();
            if ($targetId === null) {
                continue;
            }
            $this->appendToList($targetId, $value, $user);
        }
    }

    /**
     * Resolve the OverlayControl row from the event payload. Same
     * disambiguation rules used elsewhere: split source:key when present,
     * narrow by overlay slug if the user has multiple controls with the
     * same key on different templates.
     */
    private function findSourceControl(User $user, string $eventKey, string $overlaySlug): ?OverlayControl
    {
        $colon = strpos($eventKey, ':');
        if ($colon !== false) {
            $source = substr($eventKey, 0, $colon);
            $key = substr($eventKey, $colon + 1);
        } else {
            $source = null;
            $key = $eventKey;
        }

        $query = OverlayControl::where('user_id', $user->id)->where('key', $key);
        if ($source !== null) {
            $query->where('source', $source);
        } else {
            $query->whereNull('source');
        }

        $candidates = $query->get();
        if ($candidates->isEmpty()) {
            return null;
        }
        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        if ($overlaySlug !== '') {
            $bySlug = $candidates->first(fn (OverlayControl $c) => $c->template?->slug === $overlaySlug);
            if ($bySlug) {
                return $bySlug;
            }
        }

        return $candidates->firstWhere('overlay_template_id', null) ?? $candidates->first();
    }

    /**
     * Append the value to the target list, respecting max_items (FIFO
     * drop). Wraps the write in a transaction with row-level lock to
     * prevent two near-simultaneous control updates from racing.
     */
    private function appendToList(int $listId, string $value, User $user): void
    {
        DB::transaction(function () use ($listId, $value, $user) {
            /** @var OptionSet|null $list */
            $list = OptionSet::lockForUpdate()->find($listId);
            if (! $list) {
                return;
            }
            if ($list->user_id !== $user->id) {
                Log::warning('[list-writer] cross-user binding refused', [
                    'list_id' => $listId,
                    'list_user_id' => $list->user_id,
                    'event_user_id' => $user->id,
                ]);

                return;
            }
            if ($list->disabled_at !== null) {
                return;
            }

            $itemId = $list->next_item_id;
            $items = ListItems::appendValue($list->items ?? [], $value, $itemId);

            // FIFO drop when capped. The streamer's intent with a capped
            // binding is "keep latest N" - silent refusal (chat appender
            // behaviour) would be wrong here since there's no chatter to
            // tell about the rejection. Dropping from the front does not
            // free the ids - next_item_id only ever moves forward.
            if ($list->max_items !== null && count($items) > $list->max_items) {
                $overflow = count($items) - $list->max_items;
                $items = array_values(array_slice($items, $overflow));
            }

            $list->update([
                'items' => $items,
                'next_item_id' => $itemId + 1,
            ]);

            ListUpdated::dispatchFor((string) $user->twitch_id, $list->fresh());
        });
    }
}
