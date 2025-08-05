<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TwitchEvent extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'event_type',
        'event_data',
        'twitch_timestamp',
        'processed',
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
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeUnprocessed(Builder $query): Builder
    {
        return $query->where('processed', false);
    }

    /**
     * Scope a query to only include events of a specific type.
     *
     * @param Builder $query
     * @param string $type
     * @return Builder
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('event_type', $type);
    }

    /**
     * Mark the event as processed.
     *
     * @return bool
     */
    public function markAsProcessed(): bool
    {
        return $this->update(['processed' => true]);
    }
}
