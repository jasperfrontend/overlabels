<?php

namespace App\Console\Commands;

use App\Services\Lists\ListExpirySweeper;
use Illuminate\Console\Command;

/**
 * Runs both list-related sweeps (entry-TTL age-out + whole-list expiry).
 * Scheduled in routes/console.php to fire every minute. Safe to run
 * manually for testing - the underlying service is idempotent.
 */
class SweepExpiredLists extends Command
{
    protected $signature = 'lists:sweep-expired';

    protected $description = 'Sweep aged-out list entries and finalize expired lists';

    public function handle(ListExpirySweeper $sweeper): int
    {
        $result = $sweeper->run();

        $this->line(sprintf(
            'lists swept: %d (items removed: %d) | lists expired: %d',
            $result['lists_swept'],
            $result['items_removed'],
            $result['lists_expired'],
        ));

        return self::SUCCESS;
    }
}
