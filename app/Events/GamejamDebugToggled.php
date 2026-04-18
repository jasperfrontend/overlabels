<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GamejamDebugToggled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $broadcasterId,
        public bool $enabled,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('gamejam.'.$this->broadcasterId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'enabled' => $this->enabled,
        ];
    }

    public function broadcastAs(): string
    {
        return 'gamejam.debug';
    }
}
