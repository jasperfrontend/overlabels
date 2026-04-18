<?php

namespace App\Console\Commands;

use App\Events\GameStateChanged;
use App\Models\Game;
use App\Models\User;
use Illuminate\Console\Command;

class GamejamStart extends Command
{
    protected $signature = 'gamejam:start {login? : Twitch login (defaults to first bot-enabled user)} {--hp=10 : Starting HP pool}';

    protected $description = 'Start a Chat Castle game for a streamer (or abort if one is already active).';

    public function handle(): int
    {
        $user = $this->resolveUser();
        if (! $user) {
            $this->error('No matching user found.');

            return self::FAILURE;
        }

        if ($existing = Game::activeFor($user)) {
            $this->warn("{$user->name} already has an active game (id={$existing->id}, status={$existing->status}). Use gamejam:end first.");

            return self::FAILURE;
        }

        $game = Game::create([
            'user_id' => $user->id,
            'status' => Game::STATUS_RUNNING,
            'current_round' => 1,
            'player_hp' => (int) $this->option('hp'),
            'round_started_at' => now(),
        ]);

        GameStateChanged::dispatch($game);

        $this->info("Started game id={$game->id} for {$user->name} with HP pool {$game->player_hp}.");

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
