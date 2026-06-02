<?php

namespace App\Events;

use App\Models\OptionSet;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a user saves or deletes a List. Fires on two channels:
 *
 *   - `alerts.{twitch_id}` - the user-scoped firehose the internal overlay
 *     renderer + dashboard already listen on; they patch their in-memory
 *     data store so [[[c:list:<slug>]]] tags / foreach loops update live.
 *   - `lists.{twitch_id}.{slug}` - a list-scoped channel that EXTERNAL
 *     consumers (a custom wheel page, a web component) subscribe to, so they
 *     receive only this one list's updates instead of the whole alert
 *     stream. Authorized by OverlayBroadcastingAuthController via an
 *     OverlayAccessToken.
 *
 * Same event, same payload, both channels - no second event type. (The
 * richer surgical-diff layer, ListMutated with per-op payloads, is a
 * separate future addition; this carries the full after-state and lets a
 * consumer diff by item id.)
 *
 * A delete is signalled by `items === null`; otherwise `items` is the full
 * current array of item objects.
 */
class ListUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, string>|null  $items
     */
    public function __construct(
        public string $broadcasterId,
        public string $slug,
        public ?array $items,
        public ?int $updatedAt,
        public ?int $expiresAt = null,
        public ?int $disabledAt = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('alerts.'.$this->broadcasterId),
            new PrivateChannel('lists.'.$this->broadcasterId.'.'.$this->slug),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'slug' => $this->slug,
            'items' => $this->items,
            'updated_at' => $this->updatedAt,
            'expires_at' => $this->expiresAt,
            'disabled_at' => $this->disabledAt,
        ];
    }

    public function broadcastAs(): string
    {
        return $this->items === null ? 'list.deleted' : 'list.updated';
    }

    /**
     * Shorthand for the common "I mutated this list, broadcast the
     * current state" case. Pulls items + updated_at + expires_at off the
     * model so callers don't have to thread them through individually.
     */
    public static function dispatchFor(?string $broadcasterId, OptionSet $list): void
    {
        if (! $broadcasterId) {
            return;
        }

        self::dispatch(
            $broadcasterId,
            $list->slug,
            $list->items ?? [],
            $list->updated_at?->timestamp ?? now()->timestamp,
            $list->expires_at?->timestamp,
            $list->disabled_at?->timestamp,
        );
    }
}
