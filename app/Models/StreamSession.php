<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @mixin IdeHelperStreamSession
 */
class StreamSession extends Model
{
    protected $fillable = [
        'user_id',
        'started_at',
        'ended_at',
        'helix_stream_id',
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

    public function streamState(): HasOne
    {
        return $this->hasOne(StreamState::class, 'current_session_id');
    }

    public function twitchEvents(): HasMany
    {
        return $this->hasMany(TwitchEvent::class, 'stream_session_id');
    }

    public function externalEvents(): HasMany
    {
        return $this->hasMany(ExternalEvent::class, 'stream_session_id');
    }
}
