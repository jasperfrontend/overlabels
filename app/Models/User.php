<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'twitch_id',
        'avatar',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'twitch_data',
        'email_verified_at',
        'eventsub_connected_at',
        'eventsub_auto_connect',
        'onboarded_at',
        'webhook_secret',
        'role',
        'is_system_user',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'webhook_secret',
        'access_token',
        'refresh_token',
        'token_expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'eventsub_connected_at' => 'datetime',
            'eventsub_auto_connect' => 'boolean',
            'onboarded_at' => 'datetime',
            'twitch_data' => 'array',
            'password' => 'hashed',
            'is_system_user' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isGhostUser(): bool
    {
        return $this->is_system_user === true;
    }

    public static function ghostUser(): self
    {
        return static::where('twitch_id', 'GHOST_USER')->firstOrFail();
    }

    public function overlayAccessTokens(): User|HasMany
    {
        return $this->hasMany(OverlayAccessToken::class);
    }

    public function overlayTemplates(): User|HasMany
    {
        return $this->hasMany(OverlayTemplate::class, 'owner_id');
    }

    public function storageAccounts(): User|HasMany
    {
        return $this->hasMany(StorageAccount::class);
    }

    public function eventsubSubscriptions(): User|HasMany
    {
        return $this->hasMany(UserEventsubSubscription::class);
    }

    public function adminAuditLogs(): HasMany
    {
        return $this->hasMany(AdminAuditLog::class, 'admin_id');
    }

    public function isEventSubConnected(): bool
    {
        return $this->eventsub_connected_at !== null;
    }

    public function hasActiveEventSubSubscriptions(): bool
    {
        return $this->eventsubSubscriptions()
            ->where('status', 'enabled')
            ->exists();
    }

    public function isOnboarded(): bool
    {
        return $this->onboarded_at !== null;
    }

    public function hasAlertMappings(): bool
    {
        return EventTemplateMapping::where('user_id', $this->id)->exists();
    }
}
