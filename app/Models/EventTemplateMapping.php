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
 * @property string|null $condition_type
 * @property int|null $condition_value
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
        'condition_type',
        'condition_value',
        'template_id',
        'duration_ms',
        'enabled',
        'settings',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'duration_ms' => 'integer',
        'condition_value' => 'integer',
        'settings' => 'array',
    ];

    /** Variant condition: fire only when amount >= condition_value. */
    public const string CONDITION_AT_LEAST = 'at_least';

    /** Variant condition: fire only when amount === condition_value. */
    public const string CONDITION_EXACTLY = 'exactly';

    /**
     * Event types whose payload carries a numeric field a variant condition
     * can compare against. The `path` is the (flat) key on the EventSub event
     * payload; `unit` labels the value in the trigger UI. Event types absent
     * here cannot carry a condition - they always resolve to their base row,
     * preserving the original one-template-per-event behavior.
     */
    public const array AMOUNT_FIELDS = [
        'channel.cheer' => ['path' => 'bits', 'unit' => 'bits'],
        'channel.subscription.gift' => ['path' => 'total', 'unit' => 'gifts'],
        'channel.raid' => ['path' => 'viewers', 'unit' => 'viewers'],
    ];

    /**
     * Available Twitch EventSub event types. External integrations
     * (Ko-fi, Streamlabs, BMAC, etc.) live in
     * {@see ExternalEventTemplateMapping::SERVICE_EVENT_TYPES} - they
     * use a different broadcast pipeline (service:event_type keys) and
     * must never be mixed into this catalogue.
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

    /**
     * Pick the winning mapping for an incoming event, honoring variant
     * conditions. Replaces the bare `->first()` lookup at every firing site
     * (live webhook, replay, test cheer) so selection can never drift.
     *
     * Ladder (most specific first):
     *   1. `exactly N`  where amount === N
     *   2. `at_least N` where amount >= N - highest N wins
     *   3. base row (no condition) - the catch-all fallback
     *
     * Ties at the same threshold break on lowest template_id (deterministic).
     * Non-amount event types skip the ladder and resolve to their base row,
     * which is exactly the original behavior.
     *
     * @param  array<string, mixed>  $event  the EventSub `event` payload
     */
    public static function resolveForEvent(int $userId, string $eventType, array $event): ?self
    {
        $mappings = self::with('template')
            ->where('user_id', $userId)
            ->where('event_type', $eventType)
            ->where('enabled', true)
            ->get()
            ->filter(fn (self $m) => $m->template !== null);

        if ($mappings->isEmpty()) {
            return null;
        }

        $field = self::AMOUNT_FIELDS[$eventType] ?? null;

        // No numeric field to condition on - there is only ever a base row.
        if ($field === null) {
            return $mappings->sortBy('template_id')->first();
        }

        $amount = (int) ($event[$field['path']] ?? 0);

        $exact = $mappings
            ->filter(fn (self $m) => $m->condition_type === self::CONDITION_EXACTLY
                && $m->condition_value === $amount)
            ->sortBy('template_id')
            ->first();
        if ($exact !== null) {
            return $exact;
        }

        $atLeast = $mappings
            ->filter(fn (self $m) => $m->condition_type === self::CONDITION_AT_LEAST
                && $m->condition_value !== null
                && $amount >= $m->condition_value)
            // Highest threshold first; lowest template_id breaks ties.
            ->sort(fn (self $a, self $b) => [$b->condition_value, $a->template_id]
                <=> [$a->condition_value, $b->template_id])
            ->first();
        if ($atLeast !== null) {
            return $atLeast;
        }

        return $mappings
            ->filter(fn (self $m) => $m->condition_type === null)
            ->sortBy('template_id')
            ->first();
    }
}
