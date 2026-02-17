<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
