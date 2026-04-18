<?php

namespace App\Http\Controllers\Api\Internal;

use App\Events\GameStateChanged;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameJoiner;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BotGamejamActionController extends Controller
{
    public function handle(Request $request, string $login): JsonResponse
    {
        $data = $request->validate([
            'twitch_user_id' => 'required|string',
            'username' => 'required|string',
            'action' => 'required|string|in:join,vote_move,vote_hide,vote_attack',
            'direction' => 'nullable|string|in:up,down,left,right',
            'weapon_slot' => 'nullable|integer|in:1,2',
        ]);

        if ($data['action'] === 'vote_move' && empty($data['direction'])) {
            return response()->json(['reply' => 'direction required'], 422);
        }

        $user = $this->resolveUser($login);
        if (! $user) {
            return response()->json(['reply' => null], 404);
        }

        $game = Game::activeFor($user);
        if (! $game) {
            return response()->json(['reply' => null], 404);
        }

        return match ($data['action']) {
            'join' => $this->handleJoin($game, $data),
            'vote_move' => $this->handleVote($game, $data, "p:{$data['direction']}"),
            'vote_hide' => $this->handleVote($game, $data, 'h'),
            'vote_attack' => $this->handleVote(
                $game,
                $data,
                isset($data['weapon_slot']) ? "a:{$data['weapon_slot']}" : 'a',
            ),
        };
    }

    private function handleJoin(Game $game, array $data): JsonResponse
    {
        $accepted = DB::transaction(function () use ($game, $data) {
            $joiner = GameJoiner::where('game_id', $game->id)
                ->where('twitch_user_id', $data['twitch_user_id'])
                ->lockForUpdate()
                ->first();

            if ($joiner && in_array($joiner->status, [GameJoiner::STATUS_PENDING, GameJoiner::STATUS_ACTIVE], true)) {
                return false;
            }

            if ($joiner && $joiner->status === GameJoiner::STATUS_INACTIVE) {
                $joiner->update([
                    'status' => GameJoiner::STATUS_PENDING,
                    'joined_round' => $game->current_round,
                    'blocks_remaining' => 3,
                    'hp_contributed' => true,
                    'username' => $data['username'],
                    'current_vote' => null,
                    'last_vote_round' => null,
                ]);
            } else {
                GameJoiner::create([
                    'game_id' => $game->id,
                    'twitch_user_id' => $data['twitch_user_id'],
                    'username' => $data['username'],
                    'status' => GameJoiner::STATUS_PENDING,
                    'joined_round' => $game->current_round,
                    'blocks_remaining' => 3,
                    'hp_contributed' => true,
                ]);
            }

            $game->increment('player_hp');

            return true;
        });

        if (! $accepted) {
            return response()->json([
                'accepted' => false,
                'reply' => "you're already in - HP pool at {$game->player_hp}",
            ], 200);
        }

        $game->refresh();
        GameStateChanged::dispatch($game);

        return response()->json([
            'accepted' => true,
            'reply' => "joined the raid - HP pool now {$game->player_hp}",
        ], 200);
    }

    private function handleVote(Game $game, array $data, string $vote): JsonResponse
    {
        $joiner = GameJoiner::where('game_id', $game->id)
            ->where('twitch_user_id', $data['twitch_user_id'])
            ->first();

        if (! $joiner) {
            return response()->json([
                'accepted' => false,
                'reply' => 'type !join first',
            ], 409);
        }

        if ($joiner->status === GameJoiner::STATUS_INACTIVE) {
            return response()->json([
                'accepted' => false,
                'reply' => 'you went inactive - type !join to rejoin',
            ], 409);
        }

        if (! $joiner->canVoteThisRound($game->current_round)) {
            return response()->json([
                'accepted' => false,
                'reply' => 'you can vote from next round',
            ], 409);
        }

        $joiner->update([
            'current_vote' => $vote,
            'last_vote_round' => $game->current_round,
            'blocks_remaining' => 3,
        ]);

        GameStateChanged::dispatch($game);

        return response()->json([
            'accepted' => true,
            'reply' => 'ok',
        ], 200);
    }

    private function resolveUser(string $login): ?User
    {
        $login = strtolower($login);

        return User::where('bot_enabled', true)
            ->whereNotNull('twitch_data')
            ->get()
            ->first(fn (User $u) => strtolower($u->twitch_data['login'] ?? '') === $login);
    }
}
