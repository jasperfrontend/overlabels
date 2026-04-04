<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $admin_id
 * @property string $action
 * @property string|null $target_type
 * @property int|null $target_id
 * @property array<array-key, mixed>|null $metadata
 * @property string|null $ip_address
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\User|null $admin
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog whereAdminId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog whereTargetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog whereTargetType($value)
 * @mixin \Eloquent
 */
class AdminAuditLog extends Model
{
    const UPDATED_AT = null;

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
