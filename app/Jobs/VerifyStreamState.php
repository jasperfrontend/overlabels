<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\StreamStateMachineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class VerifyStreamState implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [10, 30, 60];

    /**
     * Cache lock duration for uniqueness (seconds).
     * Prevents duplicate verification jobs from piling up.
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
