<?php

namespace App\Models;

use Database\Factories\OverlayAccessTokenFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Random\RandomException;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $token_hash
 * @property string $token_prefix
 * @property bool $is_active
 * @property Carbon|null $expires_at
 * @property int $access_count
 * @property Carbon|null $last_used_at
 * @property array<array-key, mixed>|null $allowed_ips
 * @property array<array-key, mixed>|null $metadata
 * @property string|null $abilities
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, OverlayAccessLog> $accessLogs
 * @property-read int|null $access_logs_count
 * @property-read User|null $user
 * @method static OverlayAccessTokenFactory factory($count = null, $state = [])
 * @method static Builder<static>|OverlayAccessToken newModelQuery()
 * @method static Builder<static>|OverlayAccessToken newQuery()
 * @method static Builder<static>|OverlayAccessToken query()
 * @method static Builder<static>|OverlayAccessToken whereAbilities($value)
 * @method static Builder<static>|OverlayAccessToken whereAccessCount($value)
 * @method static Builder<static>|OverlayAccessToken whereAllowedIps($value)
 * @method static Builder<static>|OverlayAccessToken whereCreatedAt($value)
 * @method static Builder<static>|OverlayAccessToken whereExpiresAt($value)
 * @method static Builder<static>|OverlayAccessToken whereId($value)
 * @method static Builder<static>|OverlayAccessToken whereIsActive($value)
 * @method static Builder<static>|OverlayAccessToken whereLastUsedAt($value)
 * @method static Builder<static>|OverlayAccessToken whereMetadata($value)
 * @method static Builder<static>|OverlayAccessToken whereName($value)
 * @method static Builder<static>|OverlayAccessToken whereTokenHash($value)
 * @method static Builder<static>|OverlayAccessToken whereTokenPrefix($value)
 * @method static Builder<static>|OverlayAccessToken whereUpdatedAt($value)
 * @method static Builder<static>|OverlayAccessToken whereUserId($value)
 * @mixin Eloquent
 * @mixin IdeHelperOverlayAccessToken
 */
class OverlayAccessToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'token_hash',
        'token_prefix',
        'is_active',
        'expires_at',
        'access_count',
        'last_used_at',
        'allowed_ips',
        'metadata',
        'abilities',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'allowed_ips' => 'array',
        'metadata' => 'array',
        'access_count' => 'integer',
    ];

    protected $hidden = [
        'token_hash',
    ];

    /**
     * Generate a new secure token
     * Returns array with plain token (to show user once) and hashed version (to store)
     *
     * @throws RandomException
     */
    public static function generateToken(): array
    {
        // Generate 32 bytes of random data (256 bits)
        $randomBytes = random_bytes(32);

        // Convert to hex for URL-safe string (64 characters)
        $plainToken = bin2hex($randomBytes);

        // Add a prefix for easy identification (first 8 chars)
        $prefix = substr($plainToken, 0, 8);

        // Hash the token for storage using SHA-256
        $hashedToken = hash('sha256', $plainToken);

        return [
            'plain' => $plainToken,
            'hash' => $hashedToken,
            'prefix' => $prefix,
        ];
    }

    /**
     * Find a token by plain text and validate
     */
    public static function findByToken(string $plainToken, ?string $clientIp = null): ?self
    {
        $hashedToken = hash('sha256', $plainToken);

        $token = self::where('token_hash', $hashedToken)
            ->where('is_active', true)
            ->first();

        if (! $token || ! $token->isValid($clientIp)) {
            return null;
        }

        return $token;
    }

    /**
     * Check if the token is valid
     */
    public function isValid(?string $clientIp = null): bool
    {
        // Check if active
        if (! $this->is_active) {
            return false;
        }

        // Check expiration
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        // Check IP restrictions if configured
        if ($this->allowed_ips && count($this->allowed_ips) > 0 && $clientIp) {
            if (! in_array($clientIp, $this->allowed_ips)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Record access
     */
    public function recordAccess(?string $clientIp = null, ?string $userAgent = null, ?string $templateSlug = null): void
    {
        $this->increment('access_count');
        $this->update(['last_used_at' => now()]);

        // Log access if the table exists
        if (Schema::hasTable('overlay_access_logs')) {
            OverlayAccessLog::create([
                'token_id' => $this->id,
                'template_slug' => $templateSlug,
                'ip_address' => $clientIp,
                'user_agent' => $userAgent,
                'accessed_at' => now(),
            ]);
        }
    }

    /**
     * Check if token has specific ability
     */
    public function hasAbility(string $ability): bool
    {
        if (! $this->abilities) {
            return true; // No restrictions
        }

        $abilities = explode(',', $this->abilities);

        return in_array($ability, $abilities) || in_array('*', $abilities);
    }

    /**
     * Relationships
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(OverlayAccessLog::class, 'token_id');
    }
}
