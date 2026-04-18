<?php

namespace App\Console\Commands;

use App\Events\GameStateChanged;
use App\Jobs\ResolveGameRound;
use App\Models\Game;
use App\Models\User;
use App\Services\Gamejam\RoomSeeder;
use Illuminate\Console\Command;

class GamejamStart extends Command
{
    protected $signature = 'gamejam:start {login? : Twitch login (defaults to first bot-enabled user)} {--hp=10 : Starting HP pool} {--round-duration=30 : Seconds per round}';

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

        $duration = max(5, (int) $this->option('round-duration'));

        $game = Game::create([
            'user_id' => $user->id,
            'status' => Game::STATUS_RUNNING,
            'current_round' => 1,
            'player_hp' => (int) $this->option('hp'),
            'round_duration_seconds' => $duration,
            'round_started_at' => now(),
        ]);

        app(RoomSeeder::class)->seedRoom1($game);
        $game->refresh();

        GameStateChanged::dispatch($game);

        ResolveGameRound::dispatch($game->id, $game->current_round)
            ->delay(now()->addSeconds($game->round_duration_seconds));

        $this->info("Started game id={$game->id} for {$user->name} with HP pool {$game->player_hp}, rounds of {$game->round_duration_seconds}s.");

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
