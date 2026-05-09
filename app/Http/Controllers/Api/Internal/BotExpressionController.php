<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\BotExpression;
use App\Models\User;
use App\Services\Bot\BotExpressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotExpressionController extends Controller
{
    public function __construct(
        private readonly BotExpressionService $service,
    ) {}

    /**
     * Bot POSTs here when a chatter fires a command flagged as type=expression
     * in the synced commandMap. We gate (enabled / permission / cooldown),
     * resolve the template against the user's controls + Helix + bot context,
     * and queue the result into bot_chat_outbox. Failures are 200 OK with no
     * outbox row so the bot stays silent (silent-on-block policy).
     */
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
            return response()->json(['queued' => false, 'reason' => 'channel_not_found']);
        }

        $expression = BotExpression::where('user_id', $user->id)
            ->where('command', $command)
            ->first();

        if (! $expression) {
            return response()->json(['queued' => false, 'reason' => 'expression_not_found']);
        }

        $badges = array_map('strtolower', $data['badges'] ?? []);

        if (! $this->service->canFire($expression, $badges)) {
            return response()->json(['queued' => false, 'reason' => 'gate']);
        }

        $context = $this->service->buildBotContext($command, $data);
        $message = $this->service->fire($expression, $context);

        return response()->json([
            'queued' => $message !== '',
            'message' => $message,
        ]);
    }
}
