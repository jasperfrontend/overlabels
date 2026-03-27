<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VersionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ?string $sha;

    public function __construct(?string $sha = null)
    {
        $this->sha = $sha;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('app-updates'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'sha' => $this->sha,
        ];
    }

    public function broadcastAs(): string
    {
        return 'version.updated';
    }
}
