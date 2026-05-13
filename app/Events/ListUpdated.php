<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a user saves or deletes a List. The frontend overlay
 * renderer listens for this on the user-scoped `alerts.{twitch_id}`
 * channel and patches its in-memory data store so any
 * [[[c:list:<slug>]]] tags or [[[foreach:c:list:<slug> as item]]] loops
 * update without a page refresh.
 *
 * A delete is signalled by `items === null`; otherwise `items` is the
 * full current array.
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
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('alerts.'.$this->broadcasterId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'slug' => $this->slug,
            'items' => $this->items,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function broadcastAs(): string
    {
        return $this->items === null ? 'list.deleted' : 'list.updated';
    }
}
