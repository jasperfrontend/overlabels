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
 * @property-read string $event_type_display
 * @property-read OverlayTemplate|null $template
 * @property-read User|null $user
 *
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
 * @method static Builder<static>|EventTemplateMapping whereUpdatedAt($value)
 * @method static Builder<static>|EventTemplateMapping whereUserId($value)
 *
 * @mixin Eloquent
 */
class EventTemplateMapping extends Model
{
    protected $fillable = [
        'user_id',
        'event_type',
        'template_id',
        'duration_ms',
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
        'channel.update' => 'Stream Info Updated',
        // Hype train
        'channel.hype_train.begin' => 'Hype Train Started',
        'channel.hype_train.progress' => 'Hype Train Progress',
        'channel.hype_train.end' => 'Hype Train Ended',
        // Charity campaigns
        'channel.charity_campaign.donate' => 'Charity Donation',
        'channel.charity_campaign.start' => 'Charity Campaign Started',
        'channel.charity_campaign.progress' => 'Charity Campaign Progress',
        'channel.charity_campaign.stop' => 'Charity Campaign Ended',
        // Goals
        'channel.goal.begin' => 'Channel Goal Started',
        'channel.goal.progress' => 'Channel Goal Progress',
        'channel.goal.end' => 'Channel Goal Ended',
        // Polls
        'channel.poll.begin' => 'Poll Started',
        'channel.poll.progress' => 'Poll Progress',
        'channel.poll.end' => 'Poll Ended',
        // Predictions
        'channel.prediction.begin' => 'Prediction Started',
        'channel.prediction.progress' => 'Prediction Progress',
        'channel.prediction.lock' => 'Prediction Locked',
        'channel.prediction.end' => 'Prediction Ended',
        // Ko-fi
        'channel.kofi.donation' => 'Ko-fi Donation',
        'channel.kofi.subscription' => 'Ko-fi Subscription',
        'channel.kofi.shop_order' => 'Ko-fi Shop Order',
        'channel.kofi.commission' => 'Ko-fi Commission',
        // Streamlabs
        'streamlabs.donation' => 'Streamlabs Donation',
        'streamlabs.subscription' => 'Streamlabs Subscription',
        'streamlabs.tip' => 'Streamlabs Tip',
        // Fourthwall
        'fourthwall.donation' => 'Fourthwall Donation',
        'fourthwall.subscription' => 'Fourthwall Subscription',
        'fourthwall.shop_order' => 'Fourthwall Shop Order',
        'fourthwall.commission' => 'Fourthwall Commission',
        // Buy Me A Coffee (bmac)
        'bmac.donation' => 'Buy Me A Coffee Donation',
        'bmac.recurring' => 'Buy Me A Coffee Subscription',
        'bmac.extra' => 'Buy Me A Coffee Extra',
        'bmac.membership' => 'Buy Me A Coffee Membership',
        'bmac.wishlist' => 'Buy Me A Coffee Wishlist',
        'bmac.commission' => 'Buy Me A Coffee Commission',
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
