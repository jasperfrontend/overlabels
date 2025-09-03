<?php

namespace App\Jobs;

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

    public function handle(UserEventSubManager $manager): void
    {
        try {
            Log::info("Setting up EventSub subscriptions for user", [
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
            
            Log::info("EventSub setup completed", [
                'user_id' => $this->user->id,
                'created' => count($results['created']),
                'failed' => count($results['failed']),
                'existing' => count($results['existing']),
            ]);
            
            // If there were failures, we might want to notify the user
            if (!empty($results['failed'])) {
                // You could dispatch an event here to notify the user
                // event(new EventSubSetupPartiallyFailed($this->user, $results));
            }
            
        } catch (Exception $e) {
            Log::error("Failed to setup EventSub subscriptions", [
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
        Log::error("EventSub setup job failed after all retries", [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage(),
        ]);
        
        // You could notify the user that setup failed
        // event(new EventSubSetupFailed($this->user));
    }
}