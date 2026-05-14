<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotAlias extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'command',
        'target_template',
        'permission_level',
        'cooldown_seconds',
        'enabled',
        'hidden_from_commands',
        'last_fired_at',
    ];

    protected $casts = [
        'cooldown_seconds' => 'integer',
        'enabled' => 'boolean',
        'hidden_from_commands' => 'boolean',
        'last_fired_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
