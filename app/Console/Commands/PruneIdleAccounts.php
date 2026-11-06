<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\IdleAccountSweep;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hard-deletes accounts that signed up and did nothing within 48 hours.
 * See IdleAccountSweep for what "nothing" means.
 *
 * Scheduled hourly in routes/console.php. Each run erases at most LIMIT
 * accounts, because every erasure talks to Twitch; a backlog drains over
 * a few runs. --dry-run lists who would go without touching anything.
 */
class PruneIdleAccounts extends Command
{
    private const LIMIT = 100;

    protected $signature = 'users:prune-idle {--dry-run : List the accounts that would be deleted, delete nothing}';

    protected $description = 'Hard-delete accounts that did nothing in the 48 hours after signing up';

    public function handle(IdleAccountSweep $sweep): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = $sweep->idle()
            ->orderBy('created_at')
            ->when(! $dryRun, fn ($query) => $query->limit(self::LIMIT))
            ->get(['id', 'name', 'created_at']);

        if ($dryRun) {
            foreach ($candidates as $user) {
                $this->line(sprintf('#%d %s (signed up %s)', $user->id, $user->name, $user->created_at->toDateString()));
            }
            $this->line(sprintf('idle accounts: %d (dry run, nothing deleted)', $candidates->count()));

            return self::SUCCESS;
        }

        $deleted = 0;
        $failed = 0;

        foreach ($candidates as $candidate) {
            $user = User::find($candidate->id);
            if ($user === null) {
                continue;
            }

            try {
                if ($sweep->erase($user)) {
                    $deleted++;
                    Log::info('Idle account erased', ['user_id' => $candidate->id]);
                }
            } catch (Throwable $e) {
                $failed++;
                Log::warning('Idle account erasure failed', [
                    'user_id' => $candidate->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->line(sprintf('idle accounts deleted: %d | failed: %d', $deleted, $failed));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
