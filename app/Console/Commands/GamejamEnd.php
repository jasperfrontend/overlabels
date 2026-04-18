<?php

namespace App\Console\Commands;

use App\Events\GameStateChanged;
use App\Models\Game;
use App\Models\User;
use Illuminate\Console\Command;

class GamejamEnd extends Command
{
    protected $signature = 'gamejam:end {login? : Twitch login (defaults to first bot-enabled user)} {--status=won : Final status (won|lost)}';

    protected $description = 'End the active Chat Castle game for a streamer.';

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

        $status = in_array($this->option('status'), ['won', 'lost'], true)
            ? $this->option('status')
            : Game::STATUS_WON;

        $game->update(['status' => $status]);
        GameStateChanged::dispatch($game);

        $this->info("Ended game id={$game->id} for {$user->name} with status {$status}.");

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
