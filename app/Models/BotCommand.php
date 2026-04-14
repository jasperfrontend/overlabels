<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotCommand extends Model
{
    protected $fillable = [
        'user_id',
        'command',
        'permission_level',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    /** Valid Twitch chat permission tiers, least to most privileged. */
    public const array PERMISSION_LEVELS = [
        'everyone',
        'subscriber',
        'vip',
        'moderator',
        'broadcaster',
    ];

    /**
     * Default commands seeded when a user opts into the bot.
     * Bot-side has the response templates; we only store which commands exist
     * and the minimum permission tier required to invoke them.
     */
    public const array DEFAULTS = [
        ['command' => 'control', 'permission_level' => 'everyone'],
        ['command' => 'set', 'permission_level' => 'moderator'],
        ['command' => 'increment', 'permission_level' => 'moderator'],
        ['command' => 'decrement', 'permission_level' => 'moderator'],
        ['command' => 'reset', 'permission_level' => 'broadcaster'],
        ['command' => 'enable', 'permission_level' => 'moderator'],
        ['command' => 'disable', 'permission_level' => 'moderator'],
        ['command' => 'toggle', 'permission_level' => 'moderator'],
    ];

    /**
     * Seed the default command set for a user. Idempotent — pre-existing
     * rows (same user_id + command) are preserved as-is.
     */
    public static function seedDefaults(User $user): void
    {
        foreach (self::DEFAULTS as $def) {
            static::firstOrCreate(
                ['user_id' => $user->id, 'command' => $def['command']],
                ['permission_level' => $def['permission_level'], 'enabled' => true],
            );
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
