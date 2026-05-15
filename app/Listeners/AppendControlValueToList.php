<?php

namespace App\Listeners;

use App\Events\ControlValueUpdated;
use App\Events\ListUpdated;
use App\Models\OptionSet;
use App\Models\OverlayControl;
use App\Models\User;
use App\Support\ListItemTimestamps;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Listens for ControlValueUpdated and appends the new value to any List
 * whose source_control_id points at the updating control. The 1-to-1
 * unique constraint on option_sets.source_control_id means at most one
 * list fires per event.
 *
 * Identification flow: ControlValueUpdated carries (broadcasterId, key,
 * overlaySlug), not control_id, so we resolve back to a row via
 * (user.twitch_id, key+source, template if present). For service-managed
 * keys ("kofi:total_received") that's unambiguous. For user-authored keys
 * shared across templates, we narrow by template via overlaySlug when set.
 *
 * Why this listener lives where it does instead of inside the
 * dispatchers: dispatchers fire from many places (ExternalControlService,
 * BotControlController, dashboard PATCHes, EventSub, GPS, picker bridge).
 * Centralising the append rule here means the binding works regardless
 * of who moved the underlying value.
 *
 * Limitation in v1: type=expression sources don't work. The expression
 * engine (jsep) runs only in the overlay; the server never sees the
 * computed value at the moment it changes. Saves with an expression-typed
 * source are rejected at write time by the controller validator, so this
 * listener should never encounter one — but if it does, it appends the
 * stored (stale) value rather than crashing.
 */
class AppendControlValueToList
{
    public function handle(ControlValueUpdated $event): void
    {
        // We need the user (resolved from twitch_id) to scope the lookup;
        // ControlValueUpdated only carries broadcasterId.
        $user = User::where('twitch_id', $event->broadcasterId)->first();
        if (! $user) {
            return;
        }

        $control = $this->findControl($user, $event);
        if (! $control) {
            return;
        }

        /** @var OptionSet|null $list */
        $list = OptionSet::where('source_control_id', $control->id)->first();
        if (! $list || $list->disabled_at !== null) {
            return;
        }

        $this->appendValue($list, $event->value, $user);
    }

    /**
     * Resolve the OverlayControl row from the event payload. The event's
     * `key` is the broadcastKey, which is either "key" (user-authored) or
     * "source:key" (service-managed). For service-managed, one row per
     * user. For user-authored, the same key can appear on multiple
     * templates - we narrow by overlaySlug if it's present.
     */
    private function findControl(User $user, ControlValueUpdated $event): ?OverlayControl
    {
        $colon = strpos($event->key, ':');
        if ($colon !== false) {
            $source = substr($event->key, 0, $colon);
            $key = substr($event->key, $colon + 1);
        } else {
            $source = null;
            $key = $event->key;
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

        // Multiple matches (user-authored key reused across templates).
        // Disambiguate by overlay slug if present; otherwise prefer the
        // user-scoped row (no template) and fall back to first match.
        if ($event->overlaySlug !== '') {
            $bySlug = $candidates->first(fn (OverlayControl $c) => $c->template?->slug === $event->overlaySlug);
            if ($bySlug) {
                return $bySlug;
            }
        }

        return $candidates->firstWhere('overlay_template_id', null) ?? $candidates->first();
    }

    /**
     * Append the value to the list, respecting max_items (FIFO drop when
     * the cap is hit so the latest value is always preserved). Wraps the
     * write in a transaction with row-level lock to prevent two near-
     * simultaneous control updates from racing.
     */
    private function appendValue(OptionSet $list, string $value, User $user): void
    {
        DB::transaction(function () use ($list, $value, $user) {
            /** @var OptionSet|null $locked */
            $locked = OptionSet::lockForUpdate()->find($list->id);
            if (! $locked) {
                return;
            }

            $items = array_values($locked->items ?? []);
            $stamps = $locked->item_added_at ?? [];

            $items[] = $value;
            $stamps = ListItemTimestamps::append($stamps);

            // FIFO drop when capped. Unlike chat appenders (which silently
            // refuse on overflow), control-driven appends always succeed -
            // there's no chatter to inform that the list is full, and the
            // streamer's intent with a capped binding is "keep the latest
            // N" rather than "stop after N."
            if ($locked->max_items !== null && count($items) > $locked->max_items) {
                $overflow = count($items) - $locked->max_items;
                $items = array_slice($items, $overflow);
                $stamps = array_slice($stamps, $overflow);
            }

            $locked->update([
                'items' => $items,
                'item_added_at' => $stamps,
            ]);

            ListUpdated::dispatchFor((string) $user->twitch_id, $locked->fresh());
        });

        Log::debug('control->list append', [
            'list_id' => $list->id,
            'list_slug' => $list->slug,
            'value' => $value,
        ]);
    }
}
