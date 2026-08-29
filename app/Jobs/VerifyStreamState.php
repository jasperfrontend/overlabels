<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\StreamStateMachineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class VerifyStreamState implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [10, 30, 60];

    /**
     * Cache lock duration for uniqueness (seconds). Prevents duplicate
     * verification jobs from piling up while one is already queued.
     *
     * UntilProcessing, not plain ShouldBeUnique, and that distinction is the
     * whole state machine.
     *
     * This job schedules its own successor from inside handle() - that chain is
     * how "starting" reaches "live" in ~25 seconds. Plain ShouldBeUnique holds
     * the lock until handle() RETURNS, so the successor is dispatched against a
     * lock the running job is still holding: PendingDispatch::shouldDispatch()
     * returns false and the job vanishes with no exception and no failed_jobs
     * row. Releasing on pickup instead lets the chain re-arm.
     *
     * Only the 10s links were affected, because uniqueFor is 15: the 60s live
     * heartbeat always ran after its own lock had already expired, so it looked
     * healthy while go-live silently fell back to the 5-minute safety net and
     * took ~10 minutes on production.
     */
    public $uniqueFor = 15;

    public function __construct(
        private User $user,
    ) {}

    public function handle(StreamStateMachineService $stateMachine): void
    {
        $stateMachine->verify($this->user);
    }

    public function uniqueId(): string
    {
        return 'verify_stream_'.$this->user->id;
    }

    public function failed(Throwable $exception): void
    {
        Log::error('VerifyStreamState job failed after all retries', [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
