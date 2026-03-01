<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'fade'         => 'Fade in',
        'scale'        => 'Scale in',
        'slide-bottom' => 'Slide up from bottom',
        'slide-top'    => 'Slide down from top',
        'slide-left'   => 'Slide in from left',
        'slide-right'  => 'Slide in from right',
        'none'         => 'None (instant)',
    ];

    /**
     * Available transition types for exit animations (mirrored from EventTemplateMapping)
     */
    public const TRANSITION_OUT_TYPES = [
        'fade'         => 'Fade out',
        'scale'        => 'Scale out',
        'slide-bottom' => 'Slide down to bottom',
        'slide-top'    => 'Slide up to top',
        'slide-left'   => 'Slide out to left',
        'slide-right'  => 'Slide out to right',
        'none'         => 'None (instant)',
    ];

    /**
     * Event types per service (service key => [event_type => display label])
     */
    public const SERVICE_EVENT_TYPES = [
        'kofi' => [
            'donation'     => 'Ko-fi Donation',
            'subscription' => 'Ko-fi Subscription',
            'shop_order'   => 'Ko-fi Shop Order',
            'commission'   => 'Ko-fi Commission',
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
