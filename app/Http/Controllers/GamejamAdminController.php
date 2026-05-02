<?php

namespace App\Http\Controllers;

use App\Console\Commands\GamejamDebug;
use App\Events\GameStateChanged;
use App\Events\GamejamDebugToggled;
use App\Jobs\ResolveGameRound;
use App\Models\BotChatOutbox;
use App\Models\Game;
use App\Models\User;
use App\Services\Bot\RateLimitLog as BotRateLimitLog;
use App\Services\Gamejam\RoomSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class GamejamAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->requireBotEnabledUser($request);
        $game = Game::activeFor($user);

        return Inertia::render('gamejam/admin', [
            'game' => $game ? [
                'id' => $game->id,
                'status' => $game->status,
                'current_round' => $game->current_round,
                'player_hp' => $game->player_hp,
                'round_duration_seconds' => $game->round_duration_seconds,
                'round_started_at' => $game->round_started_at?->toIso8601String(),
            ] : null,
            'debugEnabled' => GamejamDebug::isEnabledFor($user),
            'broadcasterLogin' => strtolower($user->twitch_data['login'] ?? ''),
            'recentRateLimits' => BotRateLimitLog::recent(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $user = $this->requireBotEnabledUser($request);

        $data = $request->validate([
            'hp' => ['nullable', 'integer', 'min:1', 'max:100'],
            'round_duration' => ['nullable', 'integer', 'min:5', 'max:600'],
        ]);

        if (Game::activeFor($user)) {
            return back()->with('message', 'A game is already active. End it first.')->with('type', 'error');
        }

        $game = Game::create([
            'user_id' => $user->id,
            'status' => Game::STATUS_RUNNING,
            'current_round' => 1,
            'player_hp' => $data['hp'] ?? 10,
            'round_duration_seconds' => $data['round_duration'] ?? 30,
            'round_started_at' => now(),
        ]);

        app(RoomSeeder::class)->seedRoom1($game);
        $game->refresh();

        GameStateChanged::dispatch($game);

        ResolveGameRound::dispatch($game->id, $game->current_round)
            ->delay(now()->addSeconds($game->round_duration_seconds));

        return back()->with('message', "Game #{$game->id} started.")->with('type', 'success');
    }

    public function end(Request $request): RedirectResponse
    {
        $user = $this->requireBotEnabledUser($request);

        $data = $request->validate([
            'status' => ['required', 'in:won,lost'],
        ]);

        $game = Game::activeFor($user);
        if (! $game) {
            return back()->with('message', 'No active game to end.')->with('type', 'error');
        }

        $game->update(['status' => $data['status']]);
        GameStateChanged::dispatch($game);

        BotChatOutbox::create([
            'user_id' => $user->id,
            'message' => "Game #{$game->id} ended! The result: {$data['status']}.",
        ]);

        return back()->with('message', "Game #{$game->id} ended as {$data['status']}.")->with('type', 'success');
    }

    public function toggleDebug(Request $request): RedirectResponse
    {
        $user = $this->requireBotEnabledUser($request);

        $key = GamejamDebug::cacheKey($user);
        $next = ! (bool) Cache::get($key, false);

        if ($next) {
            Cache::forever($key, true);
        } else {
            Cache::forget($key);
        }

        GamejamDebugToggled::dispatch((string) $user->twitch_id, $next);

        return back()->with('message', 'Debug panel '.($next ? 'on' : 'off').'.')->with('type', 'success');
    }

    private function requireBotEnabledUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && $user->bot_enabled, 403, 'Bot access required.');

        return $user;
    }
}
