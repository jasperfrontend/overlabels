<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    public const string STATUS_WAITING = 'waiting';

    public const string STATUS_RUNNING = 'running';

    public const string STATUS_WON = 'won';

    public const string STATUS_LOST = 'lost';

    public const string WEAPON_FISTS = 'fists';

    public const string WEAPON_REGULAR_SWORD = 'regular_sword';

    public const string WEAPON_DE_SWORD = 'de_sword';

    protected $fillable = [
        'user_id',
        'status',
        'current_round',
        'current_room',
        'player_hp',
        'player_x',
        'player_y',
        'player_hiding_this_round',
        'weapon_slot_1',
        'weapon_slot_2',
        'weapon_slot_1_uses',
        'wears_iron_fists',
        'round_duration_seconds',
        'round_started_at',
        'last_resolved_action',
        'last_resolved_tally',
        'last_resolved_at',
    ];

    protected $casts = [
        'current_round' => 'integer',
        'current_room' => 'integer',
        'player_hp' => 'integer',
        'player_x' => 'integer',
        'player_y' => 'integer',
        'player_hiding_this_round' => 'boolean',
        'weapon_slot_1_uses' => 'integer',
        'wears_iron_fists' => 'boolean',
        'round_duration_seconds' => 'integer',
        'round_started_at' => 'datetime',
        'last_resolved_tally' => 'array',
        'last_resolved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function joiners(): HasMany
    {
        return $this->hasMany(GameJoiner::class);
    }

    public function hiddenTiles(): HasMany
    {
        return $this->hasMany(GameHiddenTile::class);
    }

    public function doors(): HasMany
    {
        return $this->hasMany(GameDoor::class);
    }

    public function hidingSpots(): HasMany
    {
        return $this->hasMany(GameHidingSpot::class);
    }

    public function blockers(): HasMany
    {
        return $this->hasMany(GameBlocker::class);
    }

    public function zombies(): HasMany
    {
        return $this->hasMany(GameZombie::class);
    }

    public static function activeFor(User $user): ?self
    {
        return static::where('user_id', $user->id)
            ->whereIn('status', [self::STATUS_WAITING, self::STATUS_RUNNING])
            ->first();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_WAITING, self::STATUS_RUNNING]);
    }
}
