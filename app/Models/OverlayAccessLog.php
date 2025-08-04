<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OverlayAccessLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'token_id',
        'template_slug',
        'ip_address',
        'user_agent',
        'metadata',
        'accessed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'accessed_at' => 'datetime',
    ];

    public function token(): BelongsTo
    {
        return $this->belongsTo(OverlayAccessToken::class, 'token_id');
    }
}
