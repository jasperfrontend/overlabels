<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Services\TwitchApiService;
use App\Services\TwitchEventSubService;
use App\Support\HumanDuration;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs !accountage. The bot relays { chatter_id, chatter_login,
 * chatter_display_name, target_login? } here; we resolve the target user
 * (chatter or specified login) via Helix users with the app access token
 * - no broadcaster scope needed because user creation dates are public -
 * and return a chat-ready `reply` formatted by HumanDuration.
 *
 * Unlike !followage, this command does not need channel_login: account
 * age is independent of which channel the command fired in. We keep the
 * route under /api/internal/bot/ for symmetry with the rest of the bot
 * surface and to ride the same bot.internal middleware + throttle.
 */
class BotAccountageController extends Controller
{
    public function __construct(
        private readonly TwitchApiService $twitchApi,
        private readonly TwitchEventSubService $eventSub,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chatter_id' => 'required|string|max:50',
            'chatter_login' => 'required|string|max:50',
            'chatter_display_name' => 'nullable|string|max:100',
            'target_login' => 'nullable|string|max:50',
        ]);

        $appToken = $this->eventSub->getAppAccessToken();
        if (! $appToken) {
            return response()->json(['reply' => 'twitch lookup is offline, try again in a moment']);
        }

        // Self lookup goes through getUserInfo(id) - one Helix call, no
        // login-to-id round trip. Targeted lookup needs the by-login
        // variant because chat only knows the typed string.
        try {
            if (! empty($data['target_login'])) {
                $target = $this->twitchApi->getUserByLogin($appToken, $data['target_login']);
                $isSelfQuery = $target
                    && strtolower($target['login']) === strtolower($data['chatter_login']);
            } else {
                $target = $this->twitchApi->getUserInfo($appToken, $data['chatter_id']);
                $isSelfQuery = true;
            }
        } catch (Exception) {
            $target = null;
            $isSelfQuery = false;
        }

        if (! $target || empty($target['created_at'])) {
            $reply = ! empty($data['target_login'])
                ? "no twitch user named @{$data['target_login']}"
                : null;

            return response()->json(['reply' => $reply]);
        }

        $createdAt = Carbon::parse($target['created_at']);
        $duration = HumanDuration::between($createdAt, Carbon::now());

        $name = $target['display_name'] ?? $target['login'];
        $subject = $isSelfQuery ? 'your account' : "@$name's account";

        return response()->json([
            'reply' => "$subject was created $duration ago",
        ]);
    }
}
