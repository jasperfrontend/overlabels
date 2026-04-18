<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameStateChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $broadcasterId;

    public array $snapshot;

    public function __construct(Game $game)
    {
        $game->loadMissing('user', 'joiners');

        $this->broadcasterId = (string) $game->user->twitch_id;
        $this->snapshot = [
            'game' => [
                'id' => $game->id,
                'status' => $game->status,
                'current_round' => $game->current_round,
                'player_hp' => $game->player_hp,
                'round_duration_seconds' => $game->round_duration_seconds,
                'round_started_at' => $game->round_started_at?->toISOString(),
                'last_resolved_action' => $game->last_resolved_action,
                'last_resolved_tally' => $game->last_resolved_tally,
                'last_resolved_at' => $game->last_resolved_at?->toISOString(),
            ],
            'joiners' => $game->joiners
                ->sortBy('joined_round')
                ->values()
                ->map(fn ($j) => [
                    'twitch_user_id' => $j->twitch_user_id,
                    'username' => $j->username,
                    'status' => $j->status,
                    'joined_round' => $j->joined_round,
                    'current_vote' => $j->current_vote,
                    'last_vote_round' => $j->last_vote_round,
                    'blocks_remaining' => $j->blocks_remaining,
                ])
                ->all(),
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('gamejam.'.$this->broadcasterId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            ...$this->snapshot,
            'updated_at' => now()->timestamp,
        ];
    }

    public function broadcastAs(): string
    {
        return 'gamejam.state';
    }
}
