<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameHidingSpot extends Model
{
    protected $fillable = [
        'game_id',
        'room',
        'x',
        'y',
    ];

    protected $casts = [
        'room' => 'integer',
        'x' => 'integer',
        'y' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
