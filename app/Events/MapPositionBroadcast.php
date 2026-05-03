<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Public-by-design GPS broadcast for the live map page.
 *
 * Only fires when the streamer has opted into public map sharing
 * (`overlabels-mobile.settings.map_sharing_enabled = true`). The private
 * `alerts.{twitchId}` channel still carries the same control updates for
 * the streamer's own overlay clients, but those carry donor names, alert
 * payloads, etc. and must stay gated. This event ships only the fields the
 * public map page needs - never names, messages, or amounts.
 *
 * Channel name uses the public map slug (Sqids-encoded Twitch ID), not the
 * raw numeric ID, so chatters watching the WebSocket frames in DevTools
 * never see the streamer's Twitch ID.
 */
class MapPositionBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $slug,
        public string $key,
        public string $value,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('map.'.$this->slug),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'updated_at' => now()->timestamp,
        ];
    }

    public function broadcastAs(): string
    {
        return 'map.position';
    }
}
