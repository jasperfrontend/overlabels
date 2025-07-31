<?php

namespace App\Models;

use App\Services\FunSlugGenerationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Log;

class OverlayHash extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'hash_key',
        'overlay_name',
        'slug',
        'description',
        'is_active',
        'last_accessed_at',
        'access_count',
        'expires_at',
        'allowed_ips',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_accessed_at' => 'datetime',
        'expires_at' => 'datetime',
        'allowed_ips' => 'array',
        'metadata' => 'array',
        'access_count' => 'integer',
    ];

    /**
     * Get the user who owns this overlay hash
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new secure hash key
     * Uses Laravel's Hash facade for cryptographic security
     */
    public static function generateSecureHash(): string
    {
        // Generate a random string, then hash it for extra security
        $randomString = Str::random(32) . time() . Str::random(32);

        // Use Laravel's Hash facade to create a secure hash
        // We'll use a combination approach for maximum security
        $hashedString = Hash::make($randomString);

        // Clean the hash to make it URL-safe (remove special characters)
        $cleanHash = preg_replace('/[^a-zA-Z0-9]/', '', $hashedString);

        // Ensure it's exactly 64 characters by padding or truncating
        $finalHash = substr(str_pad($cleanHash, 64, '0'), 0, 64);

        // Make sure this hash doesn't already exist (extremely unlikely, but good practice)
        while (self::where('hash_key', $finalHash)->exists()) {
            $randomString = Str::random(32) . time() . Str::random(32);
            $hashedString = Hash::make($randomString);
            $cleanHash = preg_replace('/[^a-zA-Z0-9]/', '', $hashedString);
            $finalHash = substr(str_pad($cleanHash, 64, '0'), 0, 64);
        }

        return $finalHash;
    }

    /**
     * Generate a fun, unique slug using the FunSlugGenerationService
     */
    public static function generateSlug(): string
    {
        $slugService = app(FunSlugGenerationService::class);
        return $slugService->generateUniqueSlug();
    }

    /**
     * Create a new overlay hash for a user
     */
    public static function createForUser(
        int $userId,
        string $overlayName,
        ?string $description = null,
        ?Carbon $expiresAt = null,
        ?array $allowedIps = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'hash_key' => self::generateSecureHash(),
            'overlay_name' => $overlayName,
            'slug' => self::generateSlug(), // Now uses fun slug generation!
            'description' => $description,
            'expires_at' => $expiresAt,
            'allowed_ips' => $allowedIps,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Check if this hash is currently valid
     */
    public function isValid(?string $clientIp = null): bool
    {
        // Check if hash is active
        if (!$this->is_active) {
            return false;
        }

        // Check if hash has expired
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        // Check the IP allowlist if configured
        if ($this->allowed_ips && $clientIp) {
            if (!in_array($clientIp, $this->allowed_ips)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Record access to this hash
     */
    public function recordAccess(?string $clientIp = null): void
    {
        $this->increment('access_count');
        $this->update([
            'last_accessed_at' => now(),
        ]);

        // Optionally log access for security monitoring
        Log::info('Overlay hash accessed', [
            'hash_id' => $this->id,
            'user_id' => $this->user_id,
            'overlay_name' => $this->overlay_name,
            'client_ip' => $clientIp,
            'access_count' => $this->access_count,
        ]);
    }

    /**
     * Revoke this hash (disable it)
     */
    public function revoke(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Regenerate the hash key (useful for refreshing security)
     */
    public function regenerateHash(): string
    {
        $newHash = self::generateSecureHash();
        $this->update(['hash_key' => $newHash]);

        return $newHash;
    }

    /**
     * Get the full overlay URL for this hash (with slug!)
     */
    public function getOverlayUrl(): string
    {
        return config('app.url') . "/overlay/$this->slug/$this->hash_key";
    }

    /**
     * Get the shareable overlay URL template (without the user's hash)
     */
    public function getShareableUrl(): string
    {
        return config('app.url') . "/overlay/$this->slug/YOUR_HASH_HERE";
    }

    /**
     * Find a hash by key and validate it
     */
    public static function findValidHash(string $hashKey, ?string $clientIp = null): ?self
    {
        $hash = self::where('hash_key', $hashKey)->first();

        if (!$hash || !$hash->isValid($clientIp)) {
            return null;
        }

        // Record the access
        $hash->recordAccess($clientIp);

        return $hash;
    }
}
