<?php

namespace App\Console\Commands;

use App\Events\GameStateChanged;
use App\Models\Game;
use App\Models\GameJoiner;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GamejamActive extends Command
{
    protected $signature = 'gamejam:active
        {username : Chatter name to show as active joiner}
        {--login= : Broadcaster Twitch login (defaults to first bot-enabled user)}
        {--vote=a : Vote code (a, h, s, a:1, p:left, p:left:2, ...)}
        {--blocks=3 : Blocks remaining}
        {--no-tally : Skip updating game.last_resolved_action / tally}';

    protected $description = 'Dev-only: inject a fake active joiner into the live game so the live.vue page has data to style.';

    public function handle(): int
    {
        $user = $this->resolveUser();
        if (! $user) {
            $this->error('No matching broadcaster found. Pass --login or seed a bot-enabled user.');

            return self::FAILURE;
        }

        $game = Game::activeFor($user);
        if (! $game) {
            $this->warn("No active game for {$user->name}. Run: php artisan gamejam:start ".($user->twitch_data['login'] ?? ''));

            return self::FAILURE;
        }

        $username = (string) $this->argument('username');
        $vote = (string) $this->option('vote');
        $blocks = max(0, min(3, (int) $this->option('blocks')));
        $fakeTwitchId = 'fake-'.strtolower($username);

        $joiner = DB::transaction(function () use ($game, $username, $vote, $blocks, $fakeTwitchId) {
            $joiner = GameJoiner::where('game_id', $game->id)
                ->where('twitch_user_id', $fakeTwitchId)
                ->lockForUpdate()
                ->first();

            $attrs = [
                'status' => GameJoiner::STATUS_ACTIVE,
                'username' => $username,
                'current_vote' => $vote,
                'last_vote_round' => $game->current_round,
                'blocks_remaining' => $blocks,
            ];

            if ($joiner) {
                $joiner->update($attrs);

                return $joiner;
            }

            return GameJoiner::create(array_merge($attrs, [
                'game_id' => $game->id,
                'twitch_user_id' => $fakeTwitchId,
                'joined_round' => max(1, $game->current_round - 1),
                'hp_contributed' => true,
            ]));
        });

        if (! $this->option('no-tally')) {
            $game->update([
                'last_resolved_action' => $vote,
                'last_resolved_tally' => [$vote => 1],
                'last_resolved_at' => now(),
            ]);
        }

        $game->refresh();
        GameStateChanged::dispatch($game);

        $this->info("Fake active joiner '{$joiner->username}' (vote={$vote}) injected into game id={$game->id} round {$game->current_round}.");

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $login = $this->option('login');

        if ($login) {
            $login = strtolower((string) $login);

            return User::where('bot_enabled', true)
                ->whereNotNull('twitch_data')
                ->get()
                ->first(fn (User $u) => strtolower($u->twitch_data['login'] ?? '') === $login);
        }

        return User::where('bot_enabled', true)->first();
    }
}
