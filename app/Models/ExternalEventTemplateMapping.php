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
 * @property int|null $overlay_template_id
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $duration_ms
 * @property array<array-key, mixed>|null $settings
 * @property-read OverlayTemplate|null $template
 * @property-read User|null $user
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
 * @mixin Eloquent
 * @mixin IdeHelperExternalEventTemplateMapping
 */
class ExternalEventTemplateMapping extends Model
{
    protected $fillable = [
        'user_id',
        'service',
        'event_type',
        'overlay_template_id',
        'enabled',
        'duration_ms',
        'settings',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'duration_ms' => 'integer',
        'settings' => 'array',
    ];

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
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(OverlayTemplate::class, 'overlay_template_id');
    }
}
