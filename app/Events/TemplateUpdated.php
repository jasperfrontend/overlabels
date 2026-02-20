<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TemplateUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $overlaySlug;

    public string $broadcasterId;

    public function __construct(string $overlaySlug, string $broadcasterId)
    {
        $this->overlaySlug = $overlaySlug;
        $this->broadcasterId = $broadcasterId;
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
            'overlay_slug' => $this->overlaySlug,
        ];
    }

    public function broadcastAs(): string
    {
        return 'template.updated';
    }
}
