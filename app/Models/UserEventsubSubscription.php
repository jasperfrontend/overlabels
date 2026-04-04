<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $twitch_subscription_id
 * @property string $event_type
 * @property string $version
 * @property string $status
 * @property array<array-key, mixed> $condition
 * @property string $callback_url
 * @property \Illuminate\Support\Carbon|null $twitch_created_at
 * @property \Illuminate\Support\Carbon|null $last_verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEventsubSubscription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEventsubSubscription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEventsubSubscription query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEventsubSubscription whereCallbackUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEventsubSubscription whereCondition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEventsubSubscription whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEventsubSubscription whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEventsubSubscription whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEventsubSubscription whereLastVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEventsubSubscription whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEventsubSubscription whereTwitchCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEventsubSubscription whereTwitchSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEventsubSubscription whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEventsubSubscription whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEventsubSubscription whereVersion($value)
 * @mixin \Eloquent
 */
class UserEventsubSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'twitch_subscription_id',
        'event_type',
        'version',
        'status',
        'condition',
        'callback_url',
        'twitch_created_at',
        'last_verified_at',
    ];

    protected $casts = [
        'condition' => 'array',
        'twitch_created_at' => 'datetime',
        'last_verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'enabled';
    }

    public function needsRenewal(): bool
    {
        return in_array($this->status, [
            'webhook_callback_verification_failed',
            'notification_failures_exceeded',
            'authorization_revoked',
            'user_removed',
        ]);
    }
}
