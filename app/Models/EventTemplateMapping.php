<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTemplateMapping extends Model
{
    protected $fillable = [
        'user_id',
        'event_type',
        'template_id',
        'duration_ms',
        'transition_in',
        'transition_out',
        'enabled',
        'settings',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'duration_ms' => 'integer',
        'settings' => 'array',
    ];

    /**
     * Available EventSub event types
     */
    public const EVENT_TYPES = [
        'channel.follow' => 'New Follower',
        'channel.subscribe' => 'New Subscription',
        'channel.subscription.gift' => 'Gift Subscription',
        'channel.subscription.message' => 'Resubscription',
        'channel.cheer' => 'Bits Cheer',
        'channel.raid' => 'Raid',
        'channel.channel_points_custom_reward_redemption.add' => 'Channel Points Redemption',
        'stream.online' => 'Stream Online',
        'stream.offline' => 'Stream Offline',
    ];

    /**
     * Available transition types
     */
    public const TRANSITION_TYPES = [
        'fade'         => 'Fade',
        'scale'        => 'Scale',
        'slide-bottom' => 'Slide ↓ from bottom',
        'slide-top'    => 'Slide ↑ from top',
        'slide-left'   => 'Slide ← from left',
        'slide-right'  => 'Slide → from right',
        'none'         => 'None',
    ];

    /**
     * Get the user that owns this mapping
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the template for this event
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(OverlayTemplate::class, 'template_id');
    }

    /**
     * Get the display name for the event type
     */
    public function getEventTypeDisplayAttribute(): string
    {
        return self::EVENT_TYPES[$this->event_type] ?? $this->event_type;
    }
}
