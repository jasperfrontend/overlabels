<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Mchev\Banhammer\Models\Ban;
use Mchev\Banhammer\Traits\Bannable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $twitch_id
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $avatar
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property array<array-key, mixed>|null $twitch_data
 * @property Carbon|null $eventsub_connected_at
 * @property bool $eventsub_auto_connect
 * @property Carbon|null $onboarded_at
 * @property string|null $webhook_secret
 * @property string $role
 * @property bool $is_system_user
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, AdminAuditLog> $adminAuditLogs
 * @property-read int|null $admin_audit_logs_count
 * @property-read Collection<int, Ban> $bans
 * @property-read int|null $bans_count
 * @property-read Collection<int, UserEventsubSubscription> $eventsubSubscriptions
 * @property-read int|null $eventsub_subscriptions_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, OverlayAccessToken> $overlayAccessTokens
 * @property-read int|null $overlay_access_tokens_count
 * @property-read Collection<int, OverlayTemplate> $overlayTemplates
 * @property-read int|null $overlay_templates_count
 *
 * @method static Builder<static>|User banned(bool $banned = true)
 * @method static Builder<static>|User bannedByType(string $className)
 * @method static UserFactory factory($count = null, $state = [])
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User notBanned()
 * @method static Builder<static>|User onlyTrashed()
 * @method static Builder<static>|User query()
 * @method static Builder<static>|User whereAccessToken($value)
 * @method static Builder<static>|User whereAvatar($value)
 * @method static Builder<static>|User whereBansMeta(string $key, $value)
 * @method static Builder<static>|User whereCreatedAt($value)
 * @method static Builder<static>|User whereDeletedAt($value)
 * @method static Builder<static>|User whereEmail($value)
 * @method static Builder<static>|User whereEmailVerifiedAt($value)
 * @method static Builder<static>|User whereEventsubAutoConnect($value)
 * @method static Builder<static>|User whereEventsubConnectedAt($value)
 * @method static Builder<static>|User whereId($value)
 * @method static Builder<static>|User whereIsSystemUser($value)
 * @method static Builder<static>|User whereName($value)
 * @method static Builder<static>|User whereOnboardedAt($value)
 * @method static Builder<static>|User wherePassword($value)
 * @method static Builder<static>|User whereRefreshToken($value)
 * @method static Builder<static>|User whereRememberToken($value)
 * @method static Builder<static>|User whereRole($value)
 * @method static Builder<static>|User whereTokenExpiresAt($value)
 * @method static Builder<static>|User whereTwitchData($value)
 * @method static Builder<static>|User whereTwitchId($value)
 * @method static Builder<static>|User whereUpdatedAt($value)
 * @method static Builder<static>|User whereWebhookSecret($value)
 * @method static Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|User withoutTrashed()
 *
 * @mixin Eloquent
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Bannable, HasFactory, Notifiable, SoftDeletes;

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
        'locale',
        'is_system_user',
        'bot_enabled',
        'bot_settings',
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
            'bot_settings' => 'array',
            'password' => 'hashed',
            'is_system_user' => 'boolean',
        ];
    }

    public function getBotSetting(string $key, mixed $default = null): mixed
    {
        return $this->bot_settings[$key] ?? $default;
    }

    public function setBotSetting(string $key, mixed $value): void
    {
        $settings = $this->bot_settings ?? [];
        $settings[$key] = $value;
        $this->bot_settings = $settings;
        $this->save();
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

    public function eventsubSubscriptions(): User|HasMany
    {
        return $this->hasMany(UserEventsubSubscription::class);
    }

    public function adminAuditLogs(): HasMany
    {
        return $this->hasMany(AdminAuditLog::class, 'admin_id');
    }

    public function streamState(): HasOne
    {
        return $this->hasOne(StreamState::class);
    }

    public function botCommands(): HasMany
    {
        return $this->hasMany(BotCommand::class);
    }

    public function streamSessions(): HasMany
    {
        return $this->hasMany(StreamSession::class);
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
