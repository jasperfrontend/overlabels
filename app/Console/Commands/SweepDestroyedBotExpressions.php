<?php

namespace App\Console\Commands;

use App\Models\BotAlias;
use App\Models\BotExpression;
use Illuminate\Console\Command;

/**
 * Deletes Bot Expressions whose self-destruct timer (destroy_at) has passed.
 * The timer is set from chat via `!ol cmd options <name> destroy <hours>`.
 *
 * Scheduled in routes/console.php to fire every minute. DB-backed rather than
 * a delayed queue job so a timer parked for hours survives queue/Redis
 * restarts. Idempotent and safe to run manually.
 *
 * When an expression is destroyed, any of that user's aliases that forward to
 * it (first token of target_template == the command) are deleted too, so we
 * don't leave dangling aliases pointing at a command that no longer exists.
 */
class SweepDestroyedBotExpressions extends Command
{
    protected $signature = 'bot:sweep-destroyed';

    protected $description = 'Delete Bot Expressions whose self-destruct timer has elapsed (and their dependent aliases)';

    public function handle(): int
    {
        $expired = BotExpression::whereNotNull('destroy_at')
            ->where('destroy_at', '<=', now())
            ->get();

        $expressionsDeleted = 0;
        $aliasesDeleted = 0;

        foreach ($expired as $expression) {
            $aliasesDeleted += $this->deleteDependentAliases($expression);
            $expression->delete();
            $expressionsDeleted++;
        }

        $this->line(sprintf(
            'bot expressions destroyed: %d | dependent aliases removed: %d',
            $expressionsDeleted,
            $aliasesDeleted,
        ));

        return self::SUCCESS;
    }

    /**
     * Delete aliases owned by the same user that forward to this command.
     * Matched in PHP via BotAlias::targetCommand() so the comparison stays
     * DB-agnostic and identical to the validator's link extraction.
     */
    private function deleteDependentAliases(BotExpression $expression): int
    {
        $deleted = 0;

        $aliases = BotAlias::where('user_id', $expression->user_id)->get();

        foreach ($aliases as $alias) {
            if ($alias->targetCommand() === $expression->command) {
                $alias->delete();
                $deleted++;
            }
        }

        return $deleted;
    }
}
