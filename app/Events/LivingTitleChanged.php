<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The living title's state changed: paused by a dashboard edit, resumed,
 * switched on or off, written, or refused by Twitch. Fired on the same
 * per-user channel as stream.status so every app page hears it through the
 * Reverb connection it already holds - the header shows "Title paused"
 * without a reload, and the settings page flips its banner in place.
 *
 * Carries the state, never the template: the template is on the settings
 * page as a prop and nothing else needs it.
 */
class LivingTitleChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array{enabled:bool,paused:bool,paused_title:?string,last_written:?string,written_at:?int,last_error:?string}  $state
     */
    public function __construct(
        public string $broadcasterId,
        public array $state,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('alerts.'.$this->broadcasterId),
        ];
    }

    public function broadcastWith(): array
    {
        return $this->state;
    }

    public function broadcastAs(): string
    {
        return 'living-title.updated';
    }
}
