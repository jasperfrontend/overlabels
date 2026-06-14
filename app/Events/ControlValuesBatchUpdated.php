<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * One broadcast carrying many control updates from a single service tick.
 *
 * A GPS ping changes ~11 control keys, each potentially duplicated across
 * several overlays. Dispatching one {@see ControlValueUpdated} per control
 * instance fans a single ping out to dozens of broadcasts. This event collapses
 * that into one message on the user's channel: the overlay applies each entry
 * through the exact same path as a single update (slug filter + value apply),
 * so each `updates` element keeps the per-update shape of
 * ControlValueUpdated::broadcastWith().
 */
class ControlValuesBatchUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, array{overlay_slug: string, key: string, type: string, value: string, timer_state?: ?array, expression?: ?string, random_state?: ?array}>  $updates
     */
    public function __construct(
        public string $broadcasterId,
        public array $updates,
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
            'updates' => $this->updates,
            'updated_at' => now()->timestamp,
        ];
    }

    public function broadcastAs(): string
    {
        return 'control.batch';
    }
}
