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
     * Event types per service (service key => [event_type => display label])
     */
    public const array SERVICE_EVENT_TYPES = [
        'kofi' => [
            'donation' => 'Ko-fi Donation',
            'subscription' => 'Ko-fi Subscription',
            'shop_order' => 'Ko-fi Shop Order',
            'commission' => 'Ko-fi Commission',
        ],
        'gpslogger' => [
            'location_update' => 'GPS Location Update',
        ],
        'streamlabs' => [
            'donation' => 'StreamLabs Donation',
        ],
        'streamelements' => [
            'donation' => 'StreamElements Tip',
        ],
        'fourthwall' => [
            'donation' => 'Fourthwall Donation',
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
