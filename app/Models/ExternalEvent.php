<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $service
 * @property string $event_type
 * @property string $message_id
 * @property array<array-key, mixed> $raw_payload
 * @property array<array-key, mixed>|null $normalized_payload
 * @property bool $controls_updated
 * @property bool $alert_dispatched
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEvent whereAlertDispatched($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEvent whereControlsUpdated($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEvent whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEvent whereMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEvent whereNormalizedPayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEvent whereRawPayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEvent whereService($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalEvent whereUserId($value)
 * @mixin \Eloquent
 */
class ExternalEvent extends Model
{
    // Append-only — no updated_at
    public const null UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'service',
        'event_type',
        'message_id',
        'raw_payload',
        'normalized_payload',
        'controls_updated',
        'alert_dispatched',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'normalized_payload' => 'array',
        'controls_updated' => 'boolean',
        'alert_dispatched' => 'boolean',
    ];


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
