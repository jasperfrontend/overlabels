<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameHiddenTile extends Model
{
    public const string CONTENT_BOMB = 'bomb';

    public const string CONTENT_HP_RESTORE = 'hp_restore';

    public const string CONTENT_ZOMBIE_SPAWN = 'zombie_spawn';

    public const string CONTENT_REGULAR_SWORD = 'regular_sword';

    public const string CONTENT_DE_SWORD = 'de_sword';

    public const string CONTENT_IRON_FISTS = 'iron_fists';

    protected $fillable = [
        'game_id',
        'room',
        'x',
        'y',
        'content',
        'payload',
        'revealed_at_round',
    ];

    protected $casts = [
        'room' => 'integer',
        'x' => 'integer',
        'y' => 'integer',
        'payload' => 'array',
        'revealed_at_round' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
