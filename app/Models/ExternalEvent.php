<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalEvent extends Model
{
    // Append-only — no updated_at
    public const UPDATED_AT = null;

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
