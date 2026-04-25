<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BotChannelsChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $login,
        public bool $enabled,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('bot-channels'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'login' => $this->login,
            'enabled' => $this->enabled,
        ];
    }

    public function broadcastAs(): string
    {
        return 'bot.channels.changed';
    }
}
