<?php

namespace App\Jobs;

use App\Models\TwitchEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Deletes the synthetic TwitchEvent row that `testCheer` persisted so the test
 * cheer shows up briefly in event logs (long enough for the user to see it fire)
 * and then disappears, mirroring StreamElements' "test event vanishes on refresh"
 * UX.
 */
class DeleteTestTwitchEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    public function __construct(
        public int $twitchEventId,
    ) {}

    public function handle(): void
    {
        TwitchEvent::where('id', $this->twitchEventId)->delete();
    }
}
