<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * One checkin pin change for the overlay's `checkins.*` iterable.
 *
 * Delta-upsert on purpose, never the full window: 50 realistic pins overflow
 * the 10 KB Reverb payload limit once the envelope and escaping are counted.
 * The full window arrives via the HTTP render payload; this event carries one
 * pin plus the authoritative window count, and the client upserts by login
 * and trims to its cap. `cleared` (go-live reset in per_stream mode) tells
 * the client to drop every pin before applying.
 */
class CheckinsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, string>|null  $pin
     */
    public function __construct(
        public string $broadcasterId,
        public ?array $pin,
        public int $count,
        public bool $cleared = false,
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
            'pin' => $this->pin,
            'count' => $this->count,
            'cleared' => $this->cleared,
        ];
    }

    public function broadcastAs(): string
    {
        return 'checkins.updated';
    }
}
