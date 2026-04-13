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
 * @property string $event_type
 * @property int|null $template_id
 * @property int $duration_ms
 * @property bool $enabled
 * @property array<array-key, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $transition_in
 * @property string $transition_out
 * @property-read string $event_type_display
 * @property-read OverlayTemplate|null $template
 * @property-read User|null $user
 * @method static Builder<static>|EventTemplateMapping newModelQuery()
 * @method static Builder<static>|EventTemplateMapping newQuery()
 * @method static Builder<static>|EventTemplateMapping query()
 * @method static Builder<static>|EventTemplateMapping whereCreatedAt($value)
 * @method static Builder<static>|EventTemplateMapping whereDurationMs($value)
 * @method static Builder<static>|EventTemplateMapping whereEnabled($value)
 * @method static Builder<static>|EventTemplateMapping whereEventType($value)
 * @method static Builder<static>|EventTemplateMapping whereId($value)
 * @method static Builder<static>|EventTemplateMapping whereSettings($value)
 * @method static Builder<static>|EventTemplateMapping whereTemplateId($value)
 * @method static Builder<static>|EventTemplateMapping whereTransitionIn($value)
 * @method static Builder<static>|EventTemplateMapping whereTransitionOut($value)
 * @method static Builder<static>|EventTemplateMapping whereUpdatedAt($value)
 * @method static Builder<static>|EventTemplateMapping whereUserId($value)
 * @mixin Eloquent
 * @mixin IdeHelperEventTemplateMapping
 */
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
    public const array EVENT_TYPES = [
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
     * Available transition types for enter animations
     */
    public const array TRANSITION_IN_TYPES = [
        'fade' => 'Fade in',
        'scale' => 'Scale in',
        'slide-bottom' => 'Slide up from bottom',
        'slide-top' => 'Slide down from top',
        'slide-left' => 'Slide in from left',
        'slide-right' => 'Slide in from right',
        'none' => 'None (instant)',
    ];

    /**
     * Available transition types for exit animations
     */
    public const array TRANSITION_OUT_TYPES = [
        'fade' => 'Fade out',
        'scale' => 'Scale out',
        'slide-bottom' => 'Slide down to bottom',
        'slide-top' => 'Slide up to top',
        'slide-left' => 'Slide out to left',
        'slide-right' => 'Slide out to right',
        'none' => 'None (instant)',
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
