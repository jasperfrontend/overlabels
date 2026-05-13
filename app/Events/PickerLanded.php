<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired (and broadcast) when a Picker primitive settles on a result.
 * Consumers: Alerts (via overlay renderer), Bot Expressions, future
 * Recipe install layer that mirrors the result into overlay_controls.
 *
 * Broadcast channel matches the user-scoped controls channel so the
 * existing Echo subscription on overlays picks it up without adding
 * new channel plumbing.
 */
class PickerLanded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $pickerId,
        public string $pickerSlug,
        public string $result,
        public int $resultAt,
        public string $broadcasterId,
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

    public function broadcastWith(): array
    {
        return [
            'picker_id' => $this->pickerId,
            'picker_slug' => $this->pickerSlug,
            'result' => $this->result,
            'result_at' => $this->resultAt,
        ];
    }

    public function broadcastAs(): string
    {
        return 'picker.landed';
    }
}
