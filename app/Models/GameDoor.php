<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameDoor extends Model
{
    public const string STATE_CLOSED = 'closed';

    public const string STATE_OPENING = 'opening';

    public const string STATE_OPEN = 'open';

    protected $fillable = [
        'game_id',
        'room',
        'x',
        'y',
        'state',
        'turns_remaining',
    ];

    protected $casts = [
        'room' => 'integer',
        'x' => 'integer',
        'y' => 'integer',
        'turns_remaining' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
