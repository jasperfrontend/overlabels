<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameJoiner extends Model
{
    public const string STATUS_PENDING = 'pending';

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'game_id',
        'twitch_user_id',
        'username',
        'status',
        'joined_round',
        'current_vote',
        'last_vote_round',
        'blocks_remaining',
        'hp_contributed',
    ];

    protected $casts = [
        'joined_round' => 'integer',
        'last_vote_round' => 'integer',
        'blocks_remaining' => 'integer',
        'hp_contributed' => 'boolean',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function canVoteThisRound(int $currentRound): bool
    {
        return $this->status === self::STATUS_ACTIVE
            || ($this->status === self::STATUS_PENDING && $this->joined_round < $currentRound);
    }
}
