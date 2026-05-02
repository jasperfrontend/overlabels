<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventSubSetupCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $broadcasterId,
        public array $created,
        public array $failed,
        public array $existing,
        public array $skippedMissingScope,
        public bool $success,
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
            'created' => $this->created,
            'failed' => $this->failed,
            'existing' => $this->existing,
            'skipped_missing_scope' => $this->skippedMissingScope,
            'success' => $this->success,
            'completed_at' => now()->timestamp,
        ];
    }

    public function broadcastAs(): string
    {
        return 'eventsub.setup-completed';
    }
}
