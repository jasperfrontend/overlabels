<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class StorageAccount extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'email',
        'name',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'scopes' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = Crypt::encryptString($value);
    }

    public function getAccessTokenAttribute($value): string
    {
        return $value ? Crypt::decryptString($value) : '';
    }

    public function setRefreshTokenAttribute($value): void
    {
        if ($value) {
            $this->attributes['refresh_token'] = Crypt::encryptString($value);
        }
    }

    public function getRefreshTokenAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }
        
        return Carbon::now()->isAfter($this->token_expires_at);
    }

    public function needsTokenRefresh(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }
        
        return Carbon::now()->addMinutes(5)->isAfter($this->token_expires_at);
    }

    public function updateTokens(string $accessToken, ?string $refreshToken = null, ?Carbon $expiresAt = null): void
    {
        $this->access_token = $accessToken;
        
        if ($refreshToken !== null) {
            $this->refresh_token = $refreshToken;
        }
        
        if ($expiresAt !== null) {
            $this->token_expires_at = $expiresAt;
        }
        
        $this->save();
    }

    public function getProviderDisplayName(): string
    {
        return match($this->provider) {
            'google_drive' => 'Google Drive',
            'onedrive' => 'OneDrive',
            'dropbox' => 'Dropbox',
            default => ucfirst($this->provider),
        };
    }
}