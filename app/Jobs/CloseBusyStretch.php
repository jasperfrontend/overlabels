<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\OverloadReports\BusyStretch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Closes a streamer's busy stretch once it has been quiet long enough, and
 * makes its Overload Report if it was busy enough (#345). Re-dispatches itself
 * while events keep coming. See BusyStretch.
 *
 * On the streamer's own lane, like FloodTick: the stretch it reads is written
 * only there.
 */
class CloseBusyStretch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public readonly int $userId,
        string $broadcasterTwitchId,
    ) {
        $this->onQueue(ProcessTwitchEvent::queueFor($broadcasterTwitchId));
    }

    public function handle(BusyStretch $stretch): void
    {
        $user = User::find($this->userId);
        if ($user === null) {
            BusyStretch::forget($this->userId);

            return;
        }

        $stretch->close($user);
    }
}
