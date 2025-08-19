<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'twitch_data' => 'array',
            'password' => 'hashed',
        ];
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
}
