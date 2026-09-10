<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotBuiltin extends Model
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
     * Who a builtin belongs to. A streamer owns most of them and may switch
     * them off or move their tier; the other two kinds are frozen, and the
     * distinction mirrors `source_managed` on OverlayControl - the write path
     * refuses them rather than the UI merely hiding the knob.
     *
     * OWNER_PRODUCT  a product installed the command and reads its own
     *                settings (Chat Tower's !stack takes its cooldown from the
     *                integration). Letting the streamer disable one breaks the
     *                product they just installed, in a place that looks
     *                unrelated to it.
     * OWNER_PLATFORM the platform's own escape hatches. !enablecontrols and
     *                !disablecontrols flip `controls_enabled`, the master
     *                switch for every chat control command, so disabling the
     *                pair would strand that switch with no chat route back.
     */
    public const string OWNER_USER = 'user';

    public const string OWNER_PRODUCT = 'product';

    public const string OWNER_PLATFORM = 'overlabels';

    /**
     * Default commands seeded when a user opts into the bot.
     * Bot-side has the response templates; we only store which commands exist
     * and the minimum permission tier required to invoke them.
     *
     * `owner` (and `service`, for a product's commands) is declared HERE and
     * never stored: it is a property of the verb, identical for every account,
     * so there is no column and adding a builtin stays two edits - this list
     * and a backfill migration for accounts that already exist.
     */
    public const array DEFAULTS = [
        ['command' => 'control', 'permission_level' => 'everyone', 'owner' => self::OWNER_USER],
        ['command' => 'set', 'permission_level' => 'moderator', 'owner' => self::OWNER_USER],
        ['command' => 'increment', 'permission_level' => 'moderator', 'owner' => self::OWNER_USER],
        ['command' => 'decrement', 'permission_level' => 'moderator', 'owner' => self::OWNER_USER],
        ['command' => 'reset', 'permission_level' => 'broadcaster', 'owner' => self::OWNER_USER],
        ['command' => 'enable', 'permission_level' => 'moderator', 'owner' => self::OWNER_USER],
        ['command' => 'disable', 'permission_level' => 'moderator', 'owner' => self::OWNER_USER],
        ['command' => 'toggle', 'permission_level' => 'moderator', 'owner' => self::OWNER_USER],
        ['command' => 'enablecontrols', 'permission_level' => 'broadcaster', 'owner' => self::OWNER_PLATFORM],
        ['command' => 'disablecontrols', 'permission_level' => 'broadcaster', 'owner' => self::OWNER_PLATFORM],
        ['command' => 'ol', 'permission_level' => 'moderator', 'owner' => self::OWNER_USER],
        ['command' => 'followage', 'permission_level' => 'everyone', 'owner' => self::OWNER_USER],
        ['command' => 'accountage', 'permission_level' => 'everyone', 'owner' => self::OWNER_USER],
        ['command' => 'ping', 'permission_level' => 'moderator', 'owner' => self::OWNER_USER],
        ['command' => 'checkin', 'permission_level' => 'everyone', 'owner' => self::OWNER_PRODUCT, 'service' => 'checkin'],
        ['command' => 'stack', 'permission_level' => 'everyone', 'owner' => self::OWNER_PRODUCT, 'service' => 'tower'],
        ['command' => 'tower', 'permission_level' => 'everyone', 'owner' => self::OWNER_PRODUCT, 'service' => 'tower'],
    ];

    /**
     * Seed the default command set for a user. Idempotent - pre-existing
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

    /** The DEFAULTS entry for a command, or null for a verb we no longer ship. */
    public static function declaration(string $command): ?array
    {
        foreach (self::DEFAULTS as $def) {
            if ($def['command'] === $command) {
                return $def;
            }
        }

        return null;
    }

    /**
     * A row whose command is no longer declared is a leftover from a removed
     * verb (the six Chat Castle ones, say). It belongs to the streamer: the
     * bot already drops it, and nothing is protecting it.
     */
    public static function ownerOf(string $command): string
    {
        return self::declaration($command)['owner'] ?? self::OWNER_USER;
    }

    /** The integration a product's command belongs to, null for anything else. */
    public static function serviceOf(string $command): ?string
    {
        return self::declaration($command)['service'] ?? null;
    }

    public static function isEditable(string $command): bool
    {
        return self::ownerOf($command) === self::OWNER_USER;
    }

    public function owner(): string
    {
        return self::ownerOf($this->command);
    }

    public function service(): ?string
    {
        return self::serviceOf($this->command);
    }

    public function editable(): bool
    {
        return self::isEditable($this->command);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
