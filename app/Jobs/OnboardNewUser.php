<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Random\RandomException;

/**
 * Runs once for a new account.
 *
 * It used to copy the starter kit into every new account and wire its alerts
 * to Twitch events by matching words in the alert names. Nothing is copied
 * here any more (Nov 2026, OL-2611-009): a new account lands on the dashboard
 * and picks a product from there.
 */
class OnboardNewUser implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public array $backoff = [30, 60];

    public int $timeout = 120;

    public function __construct(
        public User $user
    ) {}

    /**
     * @throws RandomException
     */
    public function handle(): void
    {
        $this->generateWebhookSecret();
    }

    /**
     * @throws RandomException
     */
    private function generateWebhookSecret(): void
    {
        // Skip if already generated (e.g., at signup time in OAuth callback)
        if ($this->user->webhook_secret) {
            return;
        }

        $this->user->update([
            'webhook_secret' => bin2hex(random_bytes(32)),
        ]);

        Log::info('OnboardNewUser: Generated webhook secret', [
            'user_id' => $this->user->id,
        ]);
    }
}
