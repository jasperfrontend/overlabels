<?php

namespace App\Console\Commands;

use App\Jobs\SetupUserEventSubSubscriptions;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * One-shot: dispatch setup jobs for every connected user so their existing
 * channel:read:goals grant translates into the three new goal subscriptions.
 *
 * setupUserSubscriptions() is idempotent - already-enabled subs short-circuit
 * in the 'existing' bucket, so this is safe to re-run and safe across users
 * who don't actually have the goals scope (those three events will land in
 * skipped_missing_scope instead).
 */
class BackfillEventSubGoals extends Command
{
    protected $signature = 'eventsub:backfill-goals
                            {--dry-run : List targeted users without dispatching jobs}';

    protected $description = 'Dispatch EventSub setup for connected users so they pick up the new goal subscriptions';

    public function handle(): int
    {
        $users = User::whereNotNull('eventsub_connected_at')
            ->whereNotNull('twitch_id')
            ->get();

        if ($users->isEmpty()) {
            $this->info('No connected users found.');

            return self::SUCCESS;
        }

        $this->info("Found {$users->count()} connected users.");

        $dryRun = $this->option('dry-run');

        foreach ($users as $user) {
            $this->line("  - {$user->name} (ID: {$user->id}, Twitch: {$user->twitch_id})");

            if (! $dryRun) {
                SetupUserEventSubSubscriptions::dispatch($user);
            }
        }

        if ($dryRun) {
            $this->comment('Dry run - no jobs dispatched. Re-run without --dry-run to apply.');
        } else {
            $this->info("Dispatched setup jobs for {$users->count()} users. Monitor the queue for progress.");
        }

        return self::SUCCESS;
    }
}
