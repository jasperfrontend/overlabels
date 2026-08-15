<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotCommand extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'command',
        'permission_level',
        'cooldown_seconds',
        'reply',
        'enabled',
        'hidden',
        'last_fired_at',
        'destroy_at',
    ];

    protected $casts = [
        'cooldown_seconds' => 'integer',
        'enabled' => 'boolean',
        'hidden' => 'boolean',
        'last_fired_at' => 'datetime',
        'destroy_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
