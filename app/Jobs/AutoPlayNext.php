<?php

namespace App\Jobs;

use App\Models\OptionSet;
use App\Services\Recipes\AutoPlayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * One step of a product's auto-play loop: pop the next one in line, then
 * arm the next step. Dispatched with a delay by AutoPlayService::schedule(),
 * which is the debounce; deliberately NOT ShouldBeUnique (see
 * SyncLivingTitle for why a uniqueness lock on a delayed job is wrong).
 *
 * Carries the list id, not the model: the loop reads the switch and the
 * queue as they are when the job RUNS, so switching the lane off or the
 * queue draining ends it with nothing to cancel.
 */
class AutoPlayNext implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $listId) {}

    public function handle(AutoPlayService $service): void
    {
        $list = OptionSet::find($this->listId);

        if ($list) {
            $service->run($list);
        }
    }
}
