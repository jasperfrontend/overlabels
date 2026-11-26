<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Floods\FloodGate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Applies a flood's count every FloodGate::TICK_SECONDS while it lasts, and
 * finishes it once it is over (#346). See FloodGate.
 *
 * On the streamer's own lane: the burst state it grows is read and written
 * only there, and the counters it moves must not race the events of the same
 * streamer that are still being handled one by one.
 */
class FloodTick implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public readonly int $userId,
        string $broadcasterTwitchId,
        public readonly string $eventType,
        public readonly string $kind,
    ) {
        $this->onQueue(ProcessTwitchEvent::queueFor($broadcasterTwitchId));
    }

    public function handle(FloodGate $gate): void
    {
        $user = User::find($this->userId);
        if ($user === null) {
            $gate->forget($this->userId, $this->kind);

            return;
        }

        $gate->tick($user, $this->eventType, $this->kind);
    }
}
