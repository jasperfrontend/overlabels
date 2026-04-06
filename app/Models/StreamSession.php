<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @method static Builder<static>|StreamSession newModelQuery()
 * @method static Builder<static>|StreamSession newQuery()
 * @method static Builder<static>|StreamSession query()
 * @method static Builder<static>|StreamSession whereCreatedAt($value)
 * @method static Builder<static>|StreamSession whereEndedAt($value)
 * @method static Builder<static>|StreamSession whereId($value)
 * @method static Builder<static>|StreamSession whereStartedAt($value)
 * @method static Builder<static>|StreamSession whereUpdatedAt($value)
 * @method static Builder<static>|StreamSession whereUserId($value)
 * @mixin Eloquent
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
