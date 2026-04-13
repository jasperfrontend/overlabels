<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $admin_id
 * @property string $action
 * @property string|null $target_type
 * @property int|null $target_id
 * @property array<array-key, mixed>|null $metadata
 * @property string|null $ip_address
 * @property Carbon $created_at
 * @property-read User|null $admin
 * @method static Builder<static>|AdminAuditLog newModelQuery()
 * @method static Builder<static>|AdminAuditLog newQuery()
 * @method static Builder<static>|AdminAuditLog query()
 * @method static Builder<static>|AdminAuditLog whereAction($value)
 * @method static Builder<static>|AdminAuditLog whereAdminId($value)
 * @method static Builder<static>|AdminAuditLog whereCreatedAt($value)
 * @method static Builder<static>|AdminAuditLog whereId($value)
 * @method static Builder<static>|AdminAuditLog whereIpAddress($value)
 * @method static Builder<static>|AdminAuditLog whereMetadata($value)
 * @method static Builder<static>|AdminAuditLog whereTargetId($value)
 * @method static Builder<static>|AdminAuditLog whereTargetType($value)
 * @mixin Eloquent
 * @mixin IdeHelperAdminAuditLog
 */
class AdminAuditLog extends Model
{
    const null UPDATED_AT = null;

    protected $fillable = [
        'admin_id',
        'action',
        'target_type',
        'target_id',
        'metadata',
        'ip_address',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
