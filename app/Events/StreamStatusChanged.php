<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $broadcasterId;

    public bool $live;

    public function __construct(string $broadcasterId, bool $live)
    {
        $this->broadcasterId = $broadcasterId;
        $this->live = $live;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('alerts.'.$this->broadcasterId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'live' => $this->live,
        ];
    }

    public function broadcastAs(): string
    {
        return 'stream.status';
    }
}
