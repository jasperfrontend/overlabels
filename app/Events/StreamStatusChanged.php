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

    public ?string $state;

    public ?float $confidence;

    public ?string $startedAt;

    public function __construct(
        string $broadcasterId,
        bool $live,
        ?string $state = null,
        ?float $confidence = null,
        ?string $startedAt = null
    ) {
        $this->broadcasterId = $broadcasterId;
        $this->live = $live;
        $this->state = $state;
        $this->confidence = $confidence;
        $this->startedAt = $startedAt;
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
            'state' => $this->state,
            'confidence' => $this->confidence,
            'startedAt' => $this->startedAt,
        ];
    }

    public function broadcastAs(): string
    {
        return 'stream.status';
    }
}
