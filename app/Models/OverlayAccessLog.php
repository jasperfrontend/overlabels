<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $token_id
 * @property string|null $template_slug
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<array-key, mixed>|null $metadata
 * @property Carbon $accessed_at
 * @property-read OverlayAccessToken $token
 *
 * @method static Builder<static>|OverlayAccessLog newModelQuery()
 * @method static Builder<static>|OverlayAccessLog newQuery()
 * @method static Builder<static>|OverlayAccessLog query()
 * @method static Builder<static>|OverlayAccessLog whereAccessedAt($value)
 * @method static Builder<static>|OverlayAccessLog whereId($value)
 * @method static Builder<static>|OverlayAccessLog whereIpAddress($value)
 * @method static Builder<static>|OverlayAccessLog whereMetadata($value)
 * @method static Builder<static>|OverlayAccessLog whereTemplateSlug($value)
 * @method static Builder<static>|OverlayAccessLog whereTokenId($value)
 * @method static Builder<static>|OverlayAccessLog whereUserAgent($value)
 *
 * @mixin Eloquent
 */
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
