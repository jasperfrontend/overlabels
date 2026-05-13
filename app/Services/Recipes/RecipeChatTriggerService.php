<?php

namespace App\Services\Recipes;

use App\Models\RecipeChatTrigger;
use App\Support\BotChatGate;
use Illuminate\Support\Carbon;

/**
 * Mirrors the shape of BotExpressionService: a canFire() gate followed by
 * a fire() that does the side-effect. Differs in that fire() invokes a
 * Picker rather than queueing a chat message - the recipe-trigger layer
 * is deliberately silent on chat per the doc's producer/consumer split.
 *
 * Announcing the picked value in chat is a Bot Expression's job; Kits
 * are expected to bundle a default Bot Expression alongside the recipe
 * for that purpose.
 */
class RecipeChatTriggerService
{
    /**
     * @param  array<int,string>  $badges  Lowercased IRC badge names
     */
    public function canFire(RecipeChatTrigger $trigger, array $badges): bool
    {
        if (! $trigger->enabled) {
            return false;
        }

        if (! BotChatGate::hasPermission($trigger->permission_level, $badges)) {
            return false;
        }

        if (BotChatGate::isBroadcaster($badges)) {
            return true;
        }

        return BotChatGate::isOffCooldown($trigger->last_fired_at, $trigger->cooldown_seconds);
    }

    /**
     * Fires the linked picker and stamps last_fired_at. Caller is expected
     * to have gated via canFire() first. Returns the picker result (or
     * null if the picker rejected the fire - busy / consumed-out).
     */
    public function fire(RecipeChatTrigger $trigger): ?string
    {
        $picker = $trigger->picker;
        if (! $picker) {
            return null;
        }

        $result = $picker->fire();

        $trigger->forceFill(['last_fired_at' => Carbon::now()])->save();

        return $result;
    }
}
