<?php

namespace App\Jobs;

use App\Events\GameStateChanged;
use App\Models\Game;
use App\Models\GameJoiner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ResolveGameRound implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [5, 15, 30];

    /**
     * @param  int  $expectedRound  round this job was scheduled for; acts as an
     *                              idempotency key so duplicate dispatches no-op.
     */
    public function __construct(
        public int $gameId,
        public int $expectedRound,
    ) {}

    public function handle(): void
    {
        $game = Game::with('joiners')->find($this->gameId);
        if (! $game || $game->status !== Game::STATUS_RUNNING) {
            return;
        }

        if ($game->current_round !== $this->expectedRound) {
            return;
        }

        $activeJoiners = $game->joiners
            ->where('status', GameJoiner::STATUS_ACTIVE);

        $tally = $activeJoiners
            ->whereNotNull('current_vote')
            ->groupBy('current_vote')
            ->map->count()
            ->sortDesc()
            ->toArray();

        $winner = $this->pickWinner($tally);

        DB::transaction(function () use ($game, $tally, $winner) {
            GameJoiner::where('game_id', $game->id)
                ->update(['current_vote' => null]);

            GameJoiner::where('game_id', $game->id)
                ->where('status', GameJoiner::STATUS_PENDING)
                ->where('joined_round', '<=', $game->current_round)
                ->update(['status' => GameJoiner::STATUS_ACTIVE]);

            $game->update([
                'current_round' => $game->current_round + 1,
                'round_started_at' => now(),
                'last_resolved_action' => $winner,
                'last_resolved_tally' => $tally,
                'last_resolved_at' => now(),
            ]);
        });

        $game->refresh();
        GameStateChanged::dispatch($game);

        self::dispatch($game->id, $game->current_round)
            ->delay(now()->addSeconds($game->round_duration_seconds));
    }

    /**
     * Plurality winner. Ties broken by hash order (already deterministic via
     * $tally insertion order from groupBy + sortDesc). Null if nobody voted.
     */
    private function pickWinner(array $tally): ?string
    {
        if (empty($tally)) {
            return null;
        }

        return array_key_first($tally);
    }
}
