<?php

namespace App\Console\Commands;

use App\Jobs\SetupUserEventSubSubscriptions;
use App\Models\User;
use App\Models\UserEventsubSubscription;
use App\Services\UserEventSubManager;
use Illuminate\Console\Command;

class MonitorEventSubHealth extends Command
{
    protected $signature = 'eventsub:monitor
                            {--fix : Automatically fix failed subscriptions}
                            {--force : Force renewal of all subscriptions}';

    protected $description = 'Monitor EventSub subscription health and optionally fix issues';

    private UserEventSubManager $manager;

    public function __construct(UserEventSubManager $manager)
    {
        parent::__construct();
        $this->manager = $manager;
    }

    public function handle(): int
    {
        $this->info('🔍 Monitoring EventSub subscription health...');

        $fix = $this->option('fix');
        $force = $this->option('force');

        // Get global stats
        $stats = $this->manager->getGlobalStats();

        $this->line('📊 Global Statistics:');
        $this->table([
            ['Metric', 'Count'],
        ], [
            ['Total Users', $stats['total_users']],
            ['Connected Users', $stats['connected_users']],
            ['Total Subscriptions', $stats['total_subscriptions']],
            ['Active Subscriptions', $stats['active_subscriptions']],
            ['Failed Subscriptions', $stats['failed_subscriptions']],
        ]);

        if (! empty($stats['subscriptions_by_type'])) {
            $this->newLine();
            $this->line('📋 Subscriptions by Type:');
            foreach ($stats['subscriptions_by_type'] as $type => $count) {
                $this->line("  $type: $count");
            }
        }

        // Check for users who should be connected but aren't
        $this->newLine();
        $this->line('🔍 Checking for users missing subscriptions...');

        // System users (the Ghost User) have no Twitch login, so a setup job
        // for them fails every attempt, every hour.
        $usersWhoShouldBeConnected = User::where('eventsub_auto_connect', true)
            ->where('is_system_user', false)
            ->whereNull('eventsub_connected_at')
            ->get();

        if ($usersWhoShouldBeConnected->count() > 0) {
            $this->warn("Found {$usersWhoShouldBeConnected->count()} users with auto-connect enabled but no active subscriptions:");

            foreach ($usersWhoShouldBeConnected as $user) {
                $this->line("  - {$user->name} (ID: {$user->id}, Twitch: {$user->twitch_id})");

                if ($fix) {
                    $this->info('    → Setting up subscriptions...');
                    SetupUserEventSubSubscriptions::dispatch($user);
                }
            }

            if (! $fix) {
                $this->comment('  Use --fix to automatically setup subscriptions for these users');
            }
        } else {
            $this->info('✅ All auto-connect users have subscriptions');
        }

        // Check for failed subscriptions
        $this->newLine();
        $this->line('🔍 Checking for failed subscriptions...');

        $failedSubscriptions = UserEventsubSubscription::whereIn('status', [
            'webhook_callback_verification_failed',
            'notification_failures_exceeded',
            'authorization_revoked',
            'user_removed',
        ])->with('user')->get();

        if ($failedSubscriptions->count() > 0) {
            $this->warn("Found {$failedSubscriptions->count()} failed subscriptions:");

            $failedByUser = $failedSubscriptions->groupBy('user_id');

            foreach ($failedByUser as $userId => $userFailedSubs) {
                $user = $userFailedSubs->first()->user;
                $this->line("  - {$user->name} (ID: {$user->id}): {$userFailedSubs->count()} failed");

                foreach ($userFailedSubs as $sub) {
                    $this->line("    • {$sub->event_type}: {$sub->status}");
                }

                if ($fix) {
                    $this->info('    → Recreating subscriptions...');
                    SetupUserEventSubSubscriptions::dispatch($user, true);
                }
            }

            if (! $fix) {
                $this->comment('  Use --fix to automatically recreate failed subscriptions');
            }
        } else {
            $this->info('✅ No failed subscriptions found');
        }

        // Rows Twitch no longer lists. Verify stamps them as checked, so the
        // stale pass below never sees them again and the event is silently
        // gone for good. The repair deliberately avoids the force-recreate
        // above: its Twitch-side cleanup deletes every subscription whose
        // condition names the user, which for the bot account means every
        // streamer's chat notification subscription. Dropping the dead rows
        // and running the ordinary setup creates only the missing types.
        $this->newLine();
        $this->line('🔍 Checking for subscriptions no longer on Twitch...');

        $missingByUser = UserEventsubSubscription::where('status', 'not_found_on_twitch')
            ->with('user')
            ->get()
            ->groupBy('user_id');

        if ($missingByUser->count() > 0) {
            foreach ($missingByUser as $userMissingSubs) {
                $user = $userMissingSubs->first()->user;
                if (! $user) {
                    continue;
                }

                $types = $userMissingSubs->pluck('event_type')->implode(', ');
                $this->warn("  - {$user->name} (ID: {$user->id}): no longer on Twitch: {$types}");

                if ($fix) {
                    $this->info('    → Recreating the missing subscriptions...');
                    UserEventsubSubscription::whereIn('id', $userMissingSubs->pluck('id'))->delete();
                    SetupUserEventSubSubscriptions::dispatch($user);
                }
            }

            if (! $fix) {
                $this->comment('  Use --fix to recreate them');
            }
        } else {
            $this->info('✅ Every stored subscription is on Twitch');
        }

        // Check for stale verification timestamps
        $this->newLine();
        $this->line('🔍 Checking for stale subscriptions...');

        $staleThreshold = now()->subHours(24);
        $staleSubscriptions = UserEventsubSubscription::where('last_verified_at', '<', $staleThreshold)
            ->orWhereNull('last_verified_at')
            ->with('user')
            ->get()
            ->groupBy('user_id');

        if ($staleSubscriptions->count() > 0) {
            $this->warn("Found subscriptions that haven't been verified in 24+ hours:");

            foreach ($staleSubscriptions as $userId => $userStaleSubs) {
                $user = $userStaleSubs->first()->user;
                if (! $user) {
                    continue;
                }

                $this->line("  - {$user->name} (ID: {$user->id}): {$userStaleSubs->count()} stale subscriptions");

                // Verifying runs with or without --fix: it only reads Twitch and
                // records each row's real status here, which is what lets a
                // report-only run name the dead subscriptions. Repairs are the
                // only thing --fix gates.
                $this->info('    → Verifying subscriptions...');
                try {
                    $status = $this->manager->verifyUserSubscriptions($user);
                    if (isset($status['error'])) {
                        $this->error("      Failed to verify: {$status['error']}");
                    } else {
                        $this->info("      Status: {$status['active']} active, {$status['failed']} failed, {$status['missing']} missing");
                    }
                } catch (\Exception $e) {
                    $this->error("      Failed to verify: {$e->getMessage()}");
                }
            }
        } else {
            $this->info('✅ All subscriptions are recently verified');
        }

        // Force renewal option
        if ($force) {
            $this->newLine();
            $this->warn('🔄 Force renewal requested - recreating ALL subscriptions...');

            if (! $this->confirm('This will delete and recreate all EventSub subscriptions. Continue?')) {
                return 0;
            }

            $connectedUsers = User::whereNotNull('eventsub_connected_at')->get();

            foreach ($connectedUsers as $user) {
                $this->line("  Recreating subscriptions for {$user->name}...");
                SetupUserEventSubSubscriptions::dispatch($user, true);
            }

            $this->info("✅ Force renewal jobs dispatched for {$connectedUsers->count()} users");
        }

        // Summary
        $this->newLine();
        $healthScore = $stats['total_subscriptions'] > 0
            ? round(($stats['active_subscriptions'] / $stats['total_subscriptions']) * 100, 1)
            : 100;

        $this->info('📊 Health Summary:');
        $this->line("  Overall Health Score: {$healthScore}%");

        if ($healthScore >= 95) {
            $this->info('  Status: ✅ Excellent');
        } elseif ($healthScore >= 80) {
            $this->comment('  Status: ⚠️ Good (some issues detected)');
        } else {
            $this->error('  Status: ❌ Poor (many issues detected)');
        }

        if ($fix) {
            $this->info("\n🔧 Automatic fixes have been dispatched. Check job queue status.");
        }

        return $healthScore >= 80 ? 0 : 1; // Exit code 1 if health is poor
    }
}
