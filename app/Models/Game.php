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

    protected $fillable = [
        'user_id',
        'status',
        'current_round',
        'player_hp',
        'round_duration_seconds',
        'round_started_at',
        'last_resolved_action',
        'last_resolved_tally',
        'last_resolved_at',
    ];

    protected $casts = [
        'current_round' => 'integer',
        'player_hp' => 'integer',
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
