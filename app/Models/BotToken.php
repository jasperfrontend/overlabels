<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotToken extends Model
{
    protected $fillable = [
        'account',
        'access_token',
        'refresh_token',
        'expires_at',
        'obtained_at',
        'scopes',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'scopes' => 'array',
        'expires_at' => 'integer',
        'obtained_at' => 'integer',
    ];
}
