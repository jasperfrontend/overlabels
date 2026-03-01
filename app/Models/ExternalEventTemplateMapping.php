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
    ];

    protected $casts = [
        'enabled' => 'boolean',
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
