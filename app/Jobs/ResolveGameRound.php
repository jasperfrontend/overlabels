<?php

namespace App\Jobs;

use App\Events\GameStateChanged;
use App\Models\BotChatOutbox;
use App\Models\Game;
use App\Models\GameJoiner;
use App\Services\Gamejam\ActionApplier;
use App\Services\Gamejam\ZombieTurnResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $handlerStartMs = (int) (microtime(true) * 1000);

        $game = Game::with(['joiners', 'hiddenTiles', 'doors', 'hidingSpots', 'blockers', 'zombies'])->find($this->gameId);
        if (! $game || $game->status !== Game::STATUS_RUNNING) {
            return;
        }

        if ($game->current_round !== $this->expectedRound) {
            return;
        }

        $scheduledForMs = $game->round_started_at
            ? (int) ($game->round_started_at->timestamp * 1000) + ($game->round_duration_seconds * 1000)
            : null;

        Log::info('gamejam.resolve.started', [
            'game_id' => $game->id,
            'round' => $this->expectedRound,
            'scheduled_for_ms' => $scheduledForMs,
            'started_at_ms' => $handlerStartMs,
            'queue_lag_ms' => $scheduledForMs !== null ? $handlerStartMs - $scheduledForMs : null,
        ]);

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

        $txnStartMs = (int) (microtime(true) * 1000);

        $newlyInactiveUsernames = [];

        $gameEnded = DB::transaction(function () use ($game, $tally, $winner, $slackers, &$newlyInactiveUsernames) {
            $bumpedZombieIds = app(ActionApplier::class)->apply($game, $winner);

            if ($game->status === Game::STATUS_RUNNING) {
                // The player action may have advanced to a new room (via exit)
                // or killed zombies; reload world collections so the resolver
                // sees the current room's zombies/blockers/hiding spots.
                $game->load('zombies', 'blockers', 'hidingSpots');

                // Derive the hiding flag from the player's final position
                // rather than the vote. Standing on a hiding spot counts as
                // hidden even if the player didn't re-vote !h this tick;
                // otherwise zombies would re-acquire the player next tick
                // just because the old flag was reset.
                $onHidingSpot = $game->hidingSpots
                    ->where('room', $game->current_room)
                    ->where('x', $game->player_x)
                    ->where('y', $game->player_y)
                    ->isNotEmpty();

                if ($game->player_hiding_this_round !== $onHidingSpot) {
                    $game->update(['player_hiding_this_round' => $onHidingSpot]);
                }

                app(ZombieTurnResolver::class)->resolve($game, $bumpedZombieIds);
            }

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
                    $newlyInactiveUsernames[] = $joiner->username;
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
            }

            $game->update($update);

            return $ended;
        });

        $txnEndMs = (int) (microtime(true) * 1000);

        $game->refresh();

        if (! empty($newlyInactiveUsernames)) {
            $mentions = implode(', ', array_map(fn ($u) => '@'.$u, $newlyInactiveUsernames));
            BotChatOutbox::create([
                'user_id' => $game->user_id,
                'message' => $mentions.' you became inactive due to lack of input. Type !join if you want to play again next round!',
            ]);
        }

        $preDispatchMs = (int) (microtime(true) * 1000);

        GameStateChanged::dispatch($game);

        $postDispatchMs = (int) (microtime(true) * 1000);

        Log::info('gamejam.resolve.finished', [
            'game_id' => $game->id,
            'round' => $this->expectedRound,
            'txn_duration_ms' => $txnEndMs - $txnStartMs,
            'refresh_duration_ms' => $preDispatchMs - $txnEndMs,
            'dispatch_call_ms' => $postDispatchMs - $preDispatchMs,
            'total_handler_ms' => $postDispatchMs - $handlerStartMs,
            'game_ended' => $gameEnded,
        ]);

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
