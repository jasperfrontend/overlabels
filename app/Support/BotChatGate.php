<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Stateless permission + cooldown gate shared between BotExpressionService
 * and RecipeChatTriggerService. Pulled out because both layers run the
 * same gate before they fire, with the same broadcaster-bypass semantics,
 * against the same BotCommand::PERMISSION_LEVELS vocabulary.
 */
final class BotChatGate
{
    /**
     * Permission tiers in least-to-most-privileged order, matching
     * BotCommand::PERMISSION_LEVELS exactly.
     */
    public const array TIER_ORDER = [
        'everyone' => 0,
        'subscriber' => 1,
        'vip' => 2,
        'moderator' => 3,
        'broadcaster' => 4,
    ];

    /**
     * Does the chatter (described by their IRC badges) meet the required
     * permission level?
     *
     * @param  array<int,string>  $badges  e.g. ['subscriber', 'vip']. Lowercased.
     */
    public static function hasPermission(string $required, array $badges): bool
    {
        $requiredTier = self::TIER_ORDER[$required] ?? 0;

        $highest = 0;
        foreach ($badges as $badge) {
            $tier = self::TIER_ORDER[$badge] ?? null;
            if ($tier !== null && $tier > $highest) {
                $highest = $tier;
            }
        }

        return $highest >= $requiredTier;
    }

    /**
     * Has the cooldown elapsed since the last fire?
     * Treats unset last_fired_at and zero cooldown as "always ready".
     */
    public static function isOffCooldown(?Carbon $lastFiredAt, int $cooldownSeconds): bool
    {
        if ($cooldownSeconds <= 0 || $lastFiredAt === null) {
            return true;
        }

        return Carbon::now()->greaterThanOrEqualTo(
            $lastFiredAt->copy()->addSeconds($cooldownSeconds)
        );
    }

    public static function isBroadcaster(array $badges): bool
    {
        return in_array('broadcaster', $badges, true);
    }
}
