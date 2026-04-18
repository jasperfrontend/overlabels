<?php

namespace App\Jobs;

use App\Events\GameStateChanged;
use App\Models\Game;
use App\Models\GameJoiner;
use App\Services\Gamejam\ActionApplier;
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
        $game = Game::with(['joiners', 'hiddenTiles', 'doors', 'hidingSpots'])->find($this->gameId);
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

        $slackers = $activeJoiners->filter(
            fn (GameJoiner $j) => $j->last_vote_round === null
                || $j->last_vote_round < $game->current_round,
        );

        $gameEnded = DB::transaction(function () use ($game, $tally, $winner, $slackers) {
            app(ActionApplier::class)->apply($game, $winner);

            $hpLoss = 0;
            foreach ($slackers as $joiner) {
                $newBlocks = $joiner->blocks_remaining - 1;
                if ($newBlocks <= 0) {
                    $joiner->update([
                        'blocks_remaining' => 0,
                        'status' => GameJoiner::STATUS_INACTIVE,
                        'hp_contributed' => false,
                    ]);
                    $hpLoss++;
                } else {
                    $joiner->update(['blocks_remaining' => $newBlocks]);
                }
            }

            GameJoiner::where('game_id', $game->id)
                ->update(['current_vote' => null]);

            GameJoiner::where('game_id', $game->id)
                ->where('status', GameJoiner::STATUS_PENDING)
                ->where('joined_round', '<=', $game->current_round)
                ->update(['status' => GameJoiner::STATUS_ACTIVE]);

            $ended = $game->status !== Game::STATUS_RUNNING;
            $newPlayerHp = $ended
                ? $game->player_hp
                : max(1, $game->player_hp - $hpLoss);

            $update = [
                'player_hp' => $newPlayerHp,
                'last_resolved_action' => $winner,
                'last_resolved_tally' => $tally,
                'last_resolved_at' => now(),
            ];

            if (! $ended) {
                $update['current_round'] = $game->current_round + 1;
                $update['round_started_at'] = now();
                $update['player_hiding_this_round'] = false;
            }

            $game->update($update);

            return $ended;
        });

        $game->refresh();
        GameStateChanged::dispatch($game);

        if (! $gameEnded) {
            self::dispatch($game->id, $game->current_round)
                ->delay(now()->addSeconds($game->round_duration_seconds));
        }
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
