<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $event_type
 * @property array<array-key, mixed> $event_data
 * @property Carbon $twitch_timestamp
 * @property bool $processed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $user_id
 * @property-read User|null $user
 *
 * @method static Builder<static>|TwitchEvent newModelQuery()
 * @method static Builder<static>|TwitchEvent newQuery()
 * @method static Builder<static>|TwitchEvent ofType(string $type)
 * @method static Builder<static>|TwitchEvent query()
 * @method static Builder<static>|TwitchEvent unprocessed()
 * @method static Builder<static>|TwitchEvent whereCreatedAt($value)
 * @method static Builder<static>|TwitchEvent whereEventData($value)
 * @method static Builder<static>|TwitchEvent whereEventType($value)
 * @method static Builder<static>|TwitchEvent whereId($value)
 * @method static Builder<static>|TwitchEvent whereProcessed($value)
 * @method static Builder<static>|TwitchEvent whereTwitchTimestamp($value)
 * @method static Builder<static>|TwitchEvent whereUpdatedAt($value)
 * @method static Builder<static>|TwitchEvent whereUserId($value)
 *
 * @mixin Eloquent
 */
class TwitchEvent extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'event_type',
        'event_data',
        'twitch_timestamp',
        'processed',
        'stream_session_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'event_data' => 'array',
        'twitch_timestamp' => 'datetime',
        'processed' => 'boolean',
    ];

    /**
     * Scope a query to only include unprocessed events.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function streamSession(): BelongsTo
    {
        return $this->belongsTo(StreamSession::class);
    }

    public function scopeUnprocessed(Builder $query): Builder
    {
        return $query->where('processed', false);
    }

    /**
     * Scope a query to only include events of a specific type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('event_type', $type);
    }

    /**
     * Mark the event as processed.
     */
    public function markAsProcessed(): bool
    {
        return $this->update(['processed' => true]);
    }
}
