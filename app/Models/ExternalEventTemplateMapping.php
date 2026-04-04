<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $service
 * @property string $event_type
 * @property int|null $overlay_template_id
 * @property bool $enabled
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $duration_ms
 * @property string $transition_in
 * @property string $transition_out
 * @property array<array-key, mixed>|null $settings
 * @property-read \App\Models\OverlayTemplate|null $template
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEventTemplateMapping newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEventTemplateMapping newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEventTemplateMapping query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEventTemplateMapping whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEventTemplateMapping whereDurationMs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEventTemplateMapping whereEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEventTemplateMapping whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEventTemplateMapping whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEventTemplateMapping whereOverlayTemplateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEventTemplateMapping whereService($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEventTemplateMapping whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEventTemplateMapping whereTransitionIn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEventTemplateMapping whereTransitionOut($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEventTemplateMapping whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEventTemplateMapping whereUserId($value)
 * @mixin \Eloquent
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
        'transition_in',
        'transition_out',
        'settings',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'duration_ms' => 'integer',
        'settings' => 'array',
    ];

    /**
     * Available transition types for enter animations (mirrored from EventTemplateMapping)
     */
    public const TRANSITION_IN_TYPES = [
        'fade' => 'Fade in',
        'scale' => 'Scale in',
        'slide-bottom' => 'Slide up from bottom',
        'slide-top' => 'Slide down from top',
        'slide-left' => 'Slide in from left',
        'slide-right' => 'Slide in from right',
        'none' => 'None (instant)',
    ];

    /**
     * Available transition types for exit animations (mirrored from EventTemplateMapping)
     */
    public const TRANSITION_OUT_TYPES = [
        'fade' => 'Fade out',
        'scale' => 'Scale out',
        'slide-bottom' => 'Slide down to bottom',
        'slide-top' => 'Slide up to top',
        'slide-left' => 'Slide out to left',
        'slide-right' => 'Slide out to right',
        'none' => 'None (instant)',
    ];

    /**
     * Event types per service (service key => [event_type => display label])
     */
    public const SERVICE_EVENT_TYPES = [
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
