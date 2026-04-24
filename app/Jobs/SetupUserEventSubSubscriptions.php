<?php

namespace App\Jobs;

use App\Events\EventSubSetupCompleted;
use App\Models\User;
use App\Services\UserEventSubManager;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SetupUserEventSubSubscriptions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [10, 30, 60]; // Retry after 10s, 30s, 60s

    private User $user;

    private bool $forceRecreate;

    public function __construct(User $user, bool $forceRecreate = false)
    {
        $this->user = $user;
        $this->forceRecreate = $forceRecreate;
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function handle(UserEventSubManager $manager): void
    {
        try {
            Log::info('Setting up EventSub subscriptions for user', [
                'user_id' => $this->user->id,
                'twitch_id' => $this->user->twitch_id,
                'force_recreate' => $this->forceRecreate,
            ]);

            // If force recreate, remove existing subscriptions first
            if ($this->forceRecreate) {
                $manager->removeUserSubscriptions($this->user);
            }

            // Setup subscriptions
            $results = $manager->setupUserSubscriptions($this->user);

            Log::info('EventSub setup completed', [
                'user_id' => $this->user->id,
                'created' => count($results['created']),
                'failed' => count($results['failed']),
                'existing' => count($results['existing']),
            ]);

            // Surface the results payload to the frontend via the user's alerts
            // channel so the settings page can update without polling.
            EventSubSetupCompleted::dispatch(
                (string) $this->user->twitch_id,
                $results['created'] ?? [],
                $results['failed'] ?? [],
                $results['existing'] ?? [],
                $results['skipped_missing_scope'] ?? [],
                true,
            );
        } catch (Exception $e) {
            Log::error('Failed to setup EventSub subscriptions', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry logic
            throw $e;
        }
    }

    public function failed(Exception $exception): void
    {
        Log::error('EventSub setup job failed after all retries', [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage(),
        ]);

        EventSubSetupCompleted::dispatch(
            (string) $this->user->twitch_id,
            [],
            ['job_failed' => $exception->getMessage()],
            [],
            [],
            false,
        );
    }
}
