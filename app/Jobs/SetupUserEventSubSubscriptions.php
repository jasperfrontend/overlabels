<?php

namespace App\Jobs;

use App\Events\EventSubSetupCompleted;
use App\Events\EventSubSetupProgress;
use App\Models\User;
use App\Services\UserEventSubManager;
use DateMalformedStringException;
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

    public int $tries = 3;

    public array $backoff = [10, 30, 60]; // Retry after 10s, 30s, 60s

    private User $user;

    private bool $forceRecreate;

    public function __construct(User $user, bool $forceRecreate = false)
    {
        $this->user = $user;
        $this->forceRecreate = $forceRecreate;
    }

    /**
     * @throws DateMalformedStringException
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

            // Tell the page the creates are done and the verify wait has begun.
            EventSubSetupProgress::dispatch(
                (string) $this->user->twitch_id,
                'verifying',
                count(UserEventSubManager::SUPPORTED_EVENTS),
                count(UserEventSubManager::SUPPORTED_EVENTS),
                count($results['created'] ?? []) + count($results['existing'] ?? []),
            );

            // Do NOT broadcast completion here. Twitch's webhook challenges for
            // the last-created subscriptions are still in flight at this point
            // (the inline verify above has been observed stamping rows one
            // second after their create), so statuses read now undercount and
            // a challenge that raced its row insert stays stuck at pending
            // forever. FinalizeEventSubSetup re-verifies once the challenges
            // have settled, then broadcasts the results to the settings page.
            FinalizeEventSubSetup::dispatch($this->user, $results)
                ->delay(now()->addSeconds(15));
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
