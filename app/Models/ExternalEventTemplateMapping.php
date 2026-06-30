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
 * @property string $service
 * @property string $event_type
 * @property string|null $condition_type
 * @property int|null $condition_value
 * @property int|null $overlay_template_id
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $duration_ms
 * @property array<array-key, mixed>|null $settings
 * @property-read OverlayTemplate|null $template
 * @property-read User|null $user
 *
 * @method static Builder<static>|ExternalEventTemplateMapping newModelQuery()
 * @method static Builder<static>|ExternalEventTemplateMapping newQuery()
 * @method static Builder<static>|ExternalEventTemplateMapping query()
 * @method static Builder<static>|ExternalEventTemplateMapping whereCreatedAt($value)
 * @method static Builder<static>|ExternalEventTemplateMapping whereDurationMs($value)
 * @method static Builder<static>|ExternalEventTemplateMapping whereEnabled($value)
 * @method static Builder<static>|ExternalEventTemplateMapping whereEventType($value)
 * @method static Builder<static>|ExternalEventTemplateMapping whereId($value)
 * @method static Builder<static>|ExternalEventTemplateMapping whereOverlayTemplateId($value)
 * @method static Builder<static>|ExternalEventTemplateMapping whereService($value)
 * @method static Builder<static>|ExternalEventTemplateMapping whereSettings($value)
 * @method static Builder<static>|ExternalEventTemplateMapping whereUpdatedAt($value)
 * @method static Builder<static>|ExternalEventTemplateMapping whereUserId($value)
 *
 * @mixin Eloquent
 * @mixin IdeHelperExternalEventTemplateMapping
 */
class ExternalEventTemplateMapping extends Model
{
    protected $fillable = [
        'user_id',
        'service',
        'event_type',
        'condition_type',
        'condition_value',
        'overlay_template_id',
        'enabled',
        'duration_ms',
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
     * External (service => event types) that carry a monetary amount a variant
     * condition can compare against. Scoped to the donation event of each
     * donation service - the headline "alerts by amount" case. Other monetary
     * types (Ko-fi shop orders, BMAC extras) can be added here later; events
     * absent here always resolve to their base row.
     *
     * The threshold is a whole currency unit and is currency-naive (no FX) -
     * it compares the raw numeric amount, matching the no-exchange-rate stance
     * the Stream Sessions income view already takes.
     *
     * @var array<string, array<int, string>>
     */
    public const array AMOUNT_EVENT_TYPES = [
        'bmac' => ['donation'],
        'fourthwall' => ['donation'],
        'kofi' => ['donation'],
        'streamelements' => ['donation'],
        'streamlabs' => ['donation'],
        'throne' => ['donation'],
    ];

    /**
     * Whether a (service, event_type) pair supports a variant condition.
     */
    public static function supportsCondition(string $service, string $eventType): bool
    {
        return in_array($eventType, self::AMOUNT_EVENT_TYPES[$service] ?? [], true);
    }

    /**
     * Event types per external service (service key => [event_type => display label]).
     *
     * Each entry must mirror the driver's getSupportedEventTypes(). Keys
     * here are what the TriggerManager UI shows under each external
     * service group and what gets stored on this table. Keep the
     * service key in sync with ExternalServiceDriver::getServiceKey()
     * and with SERVICE_LABELS in resources/js/utils/services.ts.
     */
    public const array SERVICE_EVENT_TYPES = [
        'bmac' => [
            'donation' => 'Buy Me a Coffee Donation',
            'recurring' => 'Buy Me a Coffee Subscription',
            'extra' => 'Buy Me a Coffee Extra',
            'membership' => 'Buy Me a Coffee Membership',
            'wishlist' => 'Buy Me a Coffee Wishlist',
            'commission' => 'Buy Me a Coffee Commission',
        ],
        'fourthwall' => [
            'donation' => 'Fourthwall Donation',
        ],
        'gps' => [
            'location_update' => 'Overlabels GPS Location Update',
            'session_start' => 'Overlabels GPS Session Started',
            'session_end' => 'Overlabels GPS Session Ended',
            'settings_sync' => 'Overlabels GPS Settings Synced',
        ],
        'kofi' => [
            'donation' => 'Ko-fi Donation',
            'subscription' => 'Ko-fi Subscription',
            'shop_order' => 'Ko-fi Shop Order',
            'commission' => 'Ko-fi Commission',
        ],
        'streamelements' => [
            'donation' => 'StreamElements Tip',
        ],
        'streamlabs' => [
            'donation' => 'Streamlabs Donation',
        ],
        'throne' => [
            'donation' => 'Throne Gift or Contribution',
        ],
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(OverlayTemplate::class, 'overlay_template_id');
    }

    /**
     * Pick the winning mapping for an incoming external event, honoring variant
     * conditions on the donated amount. Replaces the bare `->first()` lookup at
     * both firing sites (live webhook dispatch, replay) so selection can't drift.
     *
     * Ladder (most specific first):
     *   1. `exactly N`  where amount === N
     *   2. `at_least N` where amount >= N - highest N wins
     *   3. base row (no condition) - the catch-all fallback
     *
     * Ties at the same threshold break on lowest overlay_template_id. Event
     * types without a monetary amount skip the ladder and resolve to their base
     * row, which is exactly the original behavior. A null/blank amount is read
     * as 0, so a conditioned-only set with no base falls through to null.
     */
    public static function resolveForEvent(int $userId, string $service, string $eventType, ?string $amount): ?self
    {
        $mappings = self::with('template')
            ->where('user_id', $userId)
            ->where('service', $service)
            ->where('event_type', $eventType)
            ->where('enabled', true)
            ->get()
            ->filter(fn (self $m) => $m->template !== null);

        if ($mappings->isEmpty()) {
            return null;
        }

        // No monetary field to condition on - there is only ever a base row.
        if (! self::supportsCondition($service, $eventType)) {
            return $mappings->sortBy('overlay_template_id')->first();
        }

        $value = (float) ($amount ?? 0);

        $exact = $mappings
            ->filter(fn (self $m) => $m->condition_type === self::CONDITION_EXACTLY
                && $m->condition_value !== null
                && abs($value - $m->condition_value) < 0.001)
            ->sortBy('overlay_template_id')
            ->first();
        if ($exact !== null) {
            return $exact;
        }

        $atLeast = $mappings
            ->filter(fn (self $m) => $m->condition_type === self::CONDITION_AT_LEAST
                && $m->condition_value !== null
                && $value >= $m->condition_value)
            // Highest threshold first; lowest template id breaks ties.
            ->sort(fn (self $a, self $b) => [$b->condition_value, $a->overlay_template_id]
                <=> [$a->condition_value, $b->overlay_template_id])
            ->first();
        if ($atLeast !== null) {
            return $atLeast;
        }

        return $mappings
            ->filter(fn (self $m) => $m->condition_type === null)
            ->sortBy('overlay_template_id')
            ->first();
    }
}
