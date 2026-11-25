<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Lists\EventFeedService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Writes the recent-events feed lines a window left waiting, once the window
 * has ended with no event after it to write them (#343). See
 * EventFeedService::handleTwitchEventOnLane().
 *
 * Runs on the streamer's own event lane, never `default`: the pending lines
 * are a read-modify-write that is only safe while one job at a time touches
 * them, and the lane is exactly that. It also keeps the feed in the order
 * the lane handled the events.
 */
class FlushEventFeed implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $userId,
        string $broadcasterTwitchId,
    ) {
        $this->onQueue(ProcessTwitchEvent::queueFor($broadcasterTwitchId));
    }

    public function handle(EventFeedService $feeds): void
    {
        $user = User::find($this->userId);
        if ($user === null) {
            return;
        }

        $feeds->flushPending($user);
    }
}
