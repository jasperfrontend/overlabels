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
        $game->loadMissing('user', 'joiners', 'hiddenTiles', 'doors', 'hidingSpots', 'blockers');

        $this->broadcasterId = (string) $game->user->twitch_id;
        $this->snapshot = self::snapshotFor($game);
    }

    public static function snapshotFor(Game $game): array
    {
        $game->loadMissing('joiners', 'hiddenTiles', 'doors', 'hidingSpots', 'blockers');

        return [
            'game' => [
                'id' => $game->id,
                'status' => $game->status,
                'current_round' => $game->current_round,
                'current_room' => $game->current_room,
                'player_hp' => $game->player_hp,
                'player_x' => $game->player_x,
                'player_y' => $game->player_y,
                'player_hiding_this_round' => $game->player_hiding_this_round,
                'weapon_slot_1' => $game->weapon_slot_1,
                'weapon_slot_2' => $game->weapon_slot_2,
                'weapon_slot_1_uses' => $game->weapon_slot_1_uses,
                'wears_iron_fists' => $game->wears_iron_fists,
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
            'world' => [
                'hidden_tiles' => $game->hiddenTiles
                    ->where('room', $game->current_room)
                    ->values()
                    ->map(fn ($t) => [
                        'x' => $t->x,
                        'y' => $t->y,
                        'content' => $t->revealed_at_round !== null ? $t->content : null,
                        'revealed_at_round' => $t->revealed_at_round,
                    ])
                    ->all(),
                'doors' => $game->doors
                    ->where('room', $game->current_room)
                    ->values()
                    ->map(fn ($d) => [
                        'x' => $d->x,
                        'y' => $d->y,
                        'state' => $d->state,
                        'turns_remaining' => $d->turns_remaining,
                        'is_exit' => $d->is_exit,
                    ])
                    ->all(),
                'hiding_spots' => $game->hidingSpots
                    ->where('room', $game->current_room)
                    ->values()
                    ->map(fn ($s) => [
                        'x' => $s->x,
                        'y' => $s->y,
                    ])
                    ->all(),
                'blockers' => $game->blockers
                    ->where('room', $game->current_room)
                    ->values()
                    ->map(fn ($b) => [
                        'x' => $b->x,
                        'y' => $b->y,
                    ])
                    ->all(),
            ],
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
