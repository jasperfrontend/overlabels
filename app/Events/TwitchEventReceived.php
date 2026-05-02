<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TwitchEventReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $eventType;

    public array $eventData;

    public string $broadcasterId;

    public string $timestamp;

    /**
     * Create a new event instance.
     *
     * $broadcasterId scopes the broadcast to that user's private channel so
     * unrelated viewers can no longer subscribe to a platform-wide firehose
     * of every streamer's Twitch events.
     */
    public function __construct(string $eventType, array $eventData, string $broadcasterId)
    {
        $this->eventType = $eventType;
        $this->eventData = $eventData;
        $this->broadcasterId = $broadcasterId;
        $this->timestamp = now()->toISOString();
        Log::info('Twitch event received: '.$eventType);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('twitch-events.'.$this->broadcasterId);
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'twitch.event';
    }
}
