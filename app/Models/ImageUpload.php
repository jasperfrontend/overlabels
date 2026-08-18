<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single image object stored on the `images` disk (Cloudflare R2).
 *
 * One row per uploaded object. `path` is the R2 object key and `url` is the
 * public delivery URL built from it - both are stored rather than derived so
 * that a change to the disk's configured base URL doesn't silently repoint
 * every historical row at a URL that was never written.
 */
class ImageUpload extends Model
{
    public const string KIND_TEMPLATE_SCREENSHOT = 'template_screenshot';

    public const string KIND_KIT_THUMBNAIL = 'kit_thumbnail';

    protected $fillable = [
        'user_id',
        'path',
        'url',
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
