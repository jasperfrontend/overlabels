<?php

namespace App\Jobs;

use App\Events\VersionUpdated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BroadcastVersionUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?string $commitHash,
        public string $nonce,
    ) {}

    public function handle(): void
    {
        // Only the last-dispatched job's nonce will still be in cache.
        // Earlier jobs see a mismatch and silently exit.
        if (Cache::get('railway:deploy:nonce') !== $this->nonce) {
            Log::debug('Railway deploy broadcast skipped (superseded)', [
                'sha' => $this->commitHash ? substr($this->commitHash, 0, 7) : null,
            ]);

            return;
        }

        Cache::forget('railway:deploy:nonce');

        broadcast(new VersionUpdated($this->commitHash));

        Log::info('Version update broadcast sent', [
            'sha' => $this->commitHash ? substr($this->commitHash, 0, 7) : null,
        ]);
    }
}
