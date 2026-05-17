<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast once a queued TTS synthesis job has produced an mp3 in storage.
 * Pairs with AlertTriggered via alertId so the overlay can play the audio
 * relative to the originating alert's tts_delay_ms.
 *
 * Same private channel as AlertTriggered (alerts.{twitch_id}) - the overlay
 * is already subscribed and authorized.
 */
class TtsAudioReady implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $alertId,
        public readonly string $broadcasterId,
        public readonly string $audioUrl,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('alerts.'.$this->broadcasterId),
        ];
    }

    /**
     * @return array<string,string>
     */
    public function broadcastWith(): array
    {
        return [
            'alert_id' => $this->alertId,
            'audio_url' => $this->audioUrl,
        ];
    }

    public function broadcastAs(): string
    {
        return 'tts.ready';
    }
}
