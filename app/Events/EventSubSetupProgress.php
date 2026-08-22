<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Per-subscription progress while EventSub setup runs, so the settings page
 * can show a live counter instead of a frozen button for 30 seconds.
 *
 * MUST stay ShouldBroadcastNow: the dispatching code runs inside the setup
 * job on the queue worker, so a queued broadcast would wait in line behind
 * that very job and all the progress would arrive at once, after the fact.
 */
class EventSubSetupProgress implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $broadcasterId,
        public string $phase, // 'connecting' while subscriptions are being created, 'verifying' during the finalize delay
        public int $processed,
        public int $total,
        public int $connected, // created + already existing so far
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
            'phase' => $this->phase,
            'processed' => $this->processed,
            'total' => $this->total,
            'connected' => $this->connected,
        ];
    }

    public function broadcastAs(): string
    {
        return 'eventsub.setup-progress';
    }
}
