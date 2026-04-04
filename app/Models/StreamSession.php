<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamSession newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamSession newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamSession query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamSession whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamSession whereEndedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamSession whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamSession whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamSession whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreamSession whereUserId($value)
 * @mixin \Eloquent
 */
class StreamSession extends Model
{
    protected $fillable = [
        'user_id',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isLive(): bool
    {
        return $this->ended_at === null;
    }

    public static function activeFor(User $user): ?self
    {
        return static::where('user_id', $user->id)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();
    }
}
