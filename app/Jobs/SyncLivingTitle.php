<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\LivingTitleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Render the living title once and write it to Twitch if it changed.
 *
 * Dispatched with a delay by LivingTitleService::schedule(), which is the
 * debounce: one of these per user per window, whatever arrives in between is
 * absorbed. Deliberately NOT ShouldBeUnique - the cache key in the service is
 * the coalescing mechanism, and a uniqueness lock on a delayed job is the
 * shape that silently killed the VerifyStreamState chain.
 *
 * Carries the user id, not the model: the whole point is to read the user's
 * state as it is when the job RUNS, not as it was when the event arrived.
 */
class SyncLivingTitle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $userId) {}

    public function handle(LivingTitleService $service): void
    {
        // First act: close the window, so a change arriving during this
        // render opens a new one instead of being lost.
        Cache::forget(LivingTitleService::pendingKey($this->userId));

        $user = User::find($this->userId);

        if ($user) {
            $service->sync($user);
        }
    }
}
