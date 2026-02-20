<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ControlValueUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $overlaySlug;

    public string $key;

    public string $type;

    public string $value;

    public ?array $timerState;

    public string $broadcasterId;

    /**
     * Create a new event instance.
     */
    public function __construct(
        string $overlaySlug,
        string $key,
        string $type,
        string $value,
        string $broadcasterId,
        ?array $timerState = null
    ) {
        $this->overlaySlug = $overlaySlug;
        $this->key = $key;
        $this->type = $type;
        $this->value = $value;
        $this->broadcasterId = $broadcasterId;
        $this->timerState = $timerState;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('alerts.'.$this->broadcasterId),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'overlay_slug' => $this->overlaySlug,
            'key' => $this->key,
            'type' => $this->type,
            'value' => $this->value,
            'timer_state' => $this->timerState,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'control.updated';
    }
}
