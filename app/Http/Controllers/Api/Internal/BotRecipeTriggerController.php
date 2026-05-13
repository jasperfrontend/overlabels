<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\RecipeChatTrigger;
use App\Models\User;
use App\Services\Recipes\RecipeChatTriggerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sibling of BotExpressionController. The bot POSTs here when a chatter
 * fires a command flagged in the synced commandMap as type=recipe_trigger.
 * We gate (enabled / permission / cooldown), then fire the picker. No
 * outbox row is written - recipe triggers are deliberately silent in chat,
 * per the producer/consumer split (announcements live in Bot Expressions).
 */
class BotRecipeTriggerController extends Controller
{
    public function __construct(
        private readonly RecipeChatTriggerService $service,
    ) {}

    public function fire(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel_login' => 'required|string',
            'command' => 'required|string|max:30',
            'chatter_id' => 'required|string',
            'chatter_login' => 'required|string',
            'chatter_display_name' => 'nullable|string',
            'badges' => 'nullable|array',
            'badges.*' => 'string',
            'args' => 'nullable|string',
        ]);

        $login = strtolower($data['channel_login']);
        $command = ltrim($data['command'], '!');

        $user = User::where('bot_enabled', true)
            ->whereNotNull('twitch_data')
            ->get()
            ->first(fn (User $u) => strtolower($u->twitch_data['login'] ?? '') === $login);

        if (! $user) {
            return response()->json(['fired' => false, 'reason' => 'channel_not_found']);
        }

        $trigger = RecipeChatTrigger::with('picker')
            ->where('user_id', $user->id)
            ->where('command', $command)
            ->first();

        if (! $trigger) {
            return response()->json(['fired' => false, 'reason' => 'trigger_not_found']);
        }

        $badges = array_map('strtolower', $data['badges'] ?? []);

        if (! $this->service->canFire($trigger, $badges)) {
            return response()->json(['fired' => false, 'reason' => 'gate']);
        }

        $result = $this->service->fire($trigger);

        return response()->json([
            'fired' => $result !== null,
            'result' => $result,
        ]);
    }
}
