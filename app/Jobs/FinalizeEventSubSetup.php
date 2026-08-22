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

/**
 * Runs a short while after SetupUserEventSubSubscriptions so Twitch's webhook
 * challenges have settled, then reconciles local subscription statuses against
 * Twitch and broadcasts the completion payload to the settings page.
 *
 * The delay is the fix for a race: the setup loop's inline verify ran within
 * one second of the last create, while challenges for the tail-end
 * subscriptions were still in flight. A challenge that arrived before its row
 * was inserted was lost (the handler's update matched nothing), and the
 * too-early verify then froze the row at webhook_callback_verification_pending
 * even though Twitch had the subscription enabled.
 */
class FinalizeEventSubSetup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private User $user;

    private array $results;

    public function __construct(User $user, array $results)
    {
        $this->user = $user;
        $this->results = $results;
    }

    public function handle(UserEventSubManager $manager): void
    {
        // Best-effort: the setup results are already known, so a failed
        // reconciliation must not swallow the broadcast the page is waiting on.
        try {
            $manager->verifyUserSubscriptions($this->user);
        } catch (Exception $e) {
            Log::warning('EventSub finalize verification failed', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
            ]);
        }

        EventSubSetupCompleted::dispatch(
            (string) $this->user->twitch_id,
            $this->results['created'] ?? [],
            $this->results['failed'] ?? [],
            $this->results['existing'] ?? [],
            $this->results['skipped_missing_scope'] ?? [],
            true,
        );
    }
}
