<?php

namespace App\Console\Commands;

use App\Events\GameStateChanged;
use App\Models\Game;
use App\Models\GameJoiner;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GamejamAdvance extends Command
{
    protected $signature = 'gamejam:advance {login? : Twitch login (defaults to first bot-enabled user)}';

    protected $description = 'Advance the active game by one round and promote eligible pending joiners to active.';

    public function handle(): int
    {
        $user = $this->resolveUser();
        if (! $user) {
            $this->error('No matching user found.');

            return self::FAILURE;
        }

        $game = Game::activeFor($user);
        if (! $game) {
            $this->warn("No active game for {$user->name}.");

            return self::FAILURE;
        }

        $promoted = DB::transaction(function () use ($game) {
            $game->increment('current_round');
            $game->update(['round_started_at' => now()]);

            return GameJoiner::where('game_id', $game->id)
                ->where('status', GameJoiner::STATUS_PENDING)
                ->where('joined_round', '<', $game->current_round)
                ->update(['status' => GameJoiner::STATUS_ACTIVE]);
        });

        $game->refresh();
        GameStateChanged::dispatch($game);

        $this->info("Advanced game id={$game->id} to round {$game->current_round}. Promoted {$promoted} pending joiner(s) to active.");

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $login = $this->argument('login');

        if ($login) {
            $login = strtolower($login);

            return User::where('bot_enabled', true)
                ->whereNotNull('twitch_data')
                ->get()
                ->first(fn (User $u) => strtolower($u->twitch_data['login'] ?? '') === $login);
        }

        return User::where('bot_enabled', true)->first();
    }
}
