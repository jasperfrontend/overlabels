<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameZombie extends Model
{
    public const string KIND_REGULAR = 'regular';

    public const string KIND_WEAKLING = 'weakling';

    public const string KIND_BOSS = 'boss';

    public const string STATE_DRIFTING = 'drifting';

    public const string STATE_CHASING = 'chasing';

    public const string FACING_UP = 'up';

    public const string FACING_RIGHT = 'right';

    public const string FACING_DOWN = 'down';

    public const string FACING_LEFT = 'left';

    protected $fillable = [
        'game_id',
        'room',
        'x',
        'y',
        'prev_x',
        'prev_y',
        'facing',
        'hp',
        'max_hp',
        'damage',
        'kind',
        'brain_state',
        'active',
    ];

    protected $casts = [
        'room' => 'integer',
        'x' => 'integer',
        'y' => 'integer',
        'prev_x' => 'integer',
        'prev_y' => 'integer',
        'hp' => 'integer',
        'max_hp' => 'integer',
        'damage' => 'integer',
        'active' => 'boolean',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
