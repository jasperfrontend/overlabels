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
 * @property string|null $twitch_id
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
 * @property array<string, mixed> $preferences
 * @property-read string $locale
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
 * @method static Builder<static>|User whereEventsubAutoConnect($value)
 * @method static Builder<static>|User whereEventsubConnectedAt($value)
 * @method static Builder<static>|User whereId($value)
 * @method static Builder<static>|User whereIsSystemUser($value)
 * @method static Builder<static>|User whereName($value)
 * @method static Builder<static>|User whereOnboardedAt($value)
 * @method static Builder<static>|User whereRefreshToken($value)
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
        'twitch_id',
        'avatar',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'twitch_data',
        'twitch_scopes',
        'eventsub_connected_at',
        'eventsub_auto_connect',
        'onboarded_at',
        'webhook_secret',
        'role',
        'preferences',
        'is_system_user',
        'bot_enabled',
        'bot_settings',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['locale', 'foreach_caps'];

    /**
     * Default values for preference keys, used by preference() as the fallback
     * when a key is missing from the jsonb column. Keep this as the single
     * source of truth for defaults.
     */
    public const array PREFERENCE_DEFAULTS = [
        'locale' => 'en-US',
        'foreach_caps' => [
            'subscribers' => 10,
            'goals' => 3,
            'followers' => 5,
            'followed' => 5,
        ],
    ];

    public const int FOREACH_CAP_MAX = 50;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
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
            'token_expires_at' => 'datetime',
            'eventsub_connected_at' => 'datetime',
            'eventsub_auto_connect' => 'boolean',
            'onboarded_at' => 'datetime',
            'twitch_data' => 'array',
            'twitch_scopes' => 'array',
            'bot_settings' => 'array',
            'preferences' => 'array',
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

    /**
     * Read a preference by dot-notated key. Falls back to PREFERENCE_DEFAULTS,
     * then to the explicit $default.
     */
    public function preference(string $key, mixed $default = null): mixed
    {
        $value = data_get($this->preferences ?? [], $key);

        if ($value !== null) {
            return $value;
        }

        $fallback = data_get(self::PREFERENCE_DEFAULTS, $key);

        return $fallback ?? $default;
    }

    /**
     * Write a preference by dot-notated key. Does not persist — caller saves.
     */
    public function setPreference(string $key, mixed $value): self
    {
        $preferences = $this->preferences ?? [];
        data_set($preferences, $key, $value);
        $this->preferences = $preferences;

        return $this;
    }

    /**
     * Locale accessor — reads from preferences->locale, falls back to en-US.
     * Kept as a top-level attribute (via $appends) so frontend callers like
     * page.props.auth.user.locale keep working unchanged after the migration.
     */
    public function getLocaleAttribute(): string
    {
        return (string) $this->preference('locale', 'en-US');
    }

    /**
     * Per-user foreach caps merged with defaults and clamped to FOREACH_CAP_MAX.
     */
    public function foreachCaps(): array
    {
        $defaults = self::PREFERENCE_DEFAULTS['foreach_caps'];
        $stored = (array) ($this->preference('foreach_caps') ?? []);
        $merged = array_merge($defaults, array_intersect_key($stored, $defaults));

        foreach ($merged as $key => $value) {
            $merged[$key] = max(1, min(self::FOREACH_CAP_MAX, (int) $value));
        }

        return $merged;
    }

    /**
     * Accessor exposing foreachCaps() as a top-level attribute so Inertia shares
     * and $user->only(['foreach_caps']) pick it up without extra plumbing.
     */
    public function getForeachCapsAttribute(): array
    {
        return $this->foreachCaps();
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
