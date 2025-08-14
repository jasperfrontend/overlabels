<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlertTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $html;
    public string $css;
    public array $data;
    public int $duration;
    public string $transition;
    public string $broadcasterId;

    /**
     * Create a new event instance.
     */
    public function __construct(
        string $html,
        string $css,
        array $data,
        int $duration,
        string $transition,
        string $broadcasterId
    ) {
        $this->html = $html;
        $this->css = $css;
        $this->data = $data;
        $this->duration = $duration;
        $this->transition = $transition;
        $this->broadcasterId = $broadcasterId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('alerts.' . $this->broadcasterId),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'alert' => [
                'html' => $this->html,
                'css' => $this->css,
                'data' => $this->data,
                'duration' => $this->duration,
                'transition' => $this->transition,
                'timestamp' => now()->timestamp,
            ]
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'alert.triggered';
    }
}
