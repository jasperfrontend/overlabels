<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class GamejamDebug extends Command
{
    protected $signature = 'gamejam:debug {state : on|off|toggle} {login? : Twitch login (defaults to the broadcaster with an active game)}';

    protected $description = 'Toggle the live-board debug panel for a streamer.';

    public function handle(): int
    {
        $state = strtolower((string) $this->argument('state'));
        if (! in_array($state, ['on', 'off', 'toggle'], true)) {
            $this->error('state must be one of: on, off, toggle');

            return self::FAILURE;
        }

        $user = $this->resolveUser();
        if (! $user) {
            $this->error('No matching user found. Pass a login or start a game first.');

            return self::FAILURE;
        }

        $key = self::cacheKey($user);
        $current = (bool) Cache::get($key, false);

        $next = match ($state) {
            'on' => true,
            'off' => false,
            'toggle' => ! $current,
        };

        if ($next) {
            Cache::forever($key, true);
        } else {
            Cache::forget($key);
        }

        $label = $next ? 'ON' : 'OFF';
        $this->info("Gamejam debug panel is now {$label} for {$user->name}.");

        return self::SUCCESS;
    }

    public static function cacheKey(User $user): string
    {
        return 'gamejam.debug.'.$user->twitch_id;
    }

    public static function isEnabledFor(User $user): bool
    {
        return (bool) Cache::get(self::cacheKey($user), false);
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

        $activeGame = Game::active()->latest('updated_at')->first();
        if ($activeGame) {
            return $activeGame->user;
        }

        return User::where('bot_enabled', true)->first();
    }
}
