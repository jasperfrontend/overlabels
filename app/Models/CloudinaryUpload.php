<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudinaryUpload extends Model
{
    public const string KIND_TEMPLATE_SCREENSHOT = 'template_screenshot';

    public const string KIND_KIT_THUMBNAIL = 'kit_thumbnail';

    protected $fillable = [
        'user_id',
        'public_id',
        'secure_url',
        'kind',
        'bytes',
        'width',
        'height',
        'format',
        'claimed_at',
    ];

    protected $casts = [
        'bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'claimed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
