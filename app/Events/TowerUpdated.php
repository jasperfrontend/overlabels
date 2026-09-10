<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * One Chat Tower change for the overlay's `tower.*` iterable.
 *
 * Delta on purpose, never the whole tower: a stack carries the ONE block
 * that landed plus the authoritative height, and the client appends it and
 * trims to its window (the checkins.updated shape - a full tower would
 * overflow Reverb's 10 KB payload limit long before the fall line does).
 * The full window arrives via the HTTP render payload.
 *
 * `cleared` tells the client to drop every block before applying: the
 * go-live reset in per_stream mode, the settings-page reset, and a topple.
 * A topple also carries `toppled` (who placed the last block and how tall
 * it was) and the culprit block itself, so the overlay can land it and
 * then bring the whole thing down.
 */
class TowerUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, string>|null  $block
     * @param  array<string, mixed>|null  $toppled
     */
    public function __construct(
        public string $broadcasterId,
        public ?array $block,
        public int $height,
        public bool $cleared = false,
        public ?array $toppled = null,
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
            'block' => $this->block,
            'height' => $this->height,
            'cleared' => $this->cleared,
            'toppled' => $this->toppled,
        ];
    }

    public function broadcastAs(): string
    {
        return 'tower.updated';
    }
}
