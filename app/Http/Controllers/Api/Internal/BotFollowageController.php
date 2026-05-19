<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwitchApiService;
use App\Services\TwitchEventSubService;
use App\Services\TwitchTokenService;
use App\Support\HumanDuration;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs !followage. The bot relays { channel_login, chatter_id,
 * chatter_login, chatter_display_name, target_login? } here; we resolve
 * the channel owner, optionally resolve a different target user, hit
 * Helix channels/followers with the broadcaster's user token + the
 * existing moderator:read:followers scope, and return a chat-ready
 * `reply` string. The bot speaks it inline - no outbox.
 *
 * Why broadcaster's token and not app token: Helix follower reads
 * require either the broadcaster (with channel:read:followers) or a
 * channel moderator (with moderator:read:followers). Overlabels already
 * requests moderator:read:followers for every opted-in streamer
 * (TwitchScopeService), so the existing token is sufficient.
 */
class BotFollowageController extends Controller
{
    public function __construct(
        private readonly TwitchApiService $twitchApi,
        private readonly TwitchTokenService $twitchTokens,
        private readonly TwitchEventSubService $eventSub,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel_login' => 'required|string|max:50',
            'chatter_id' => 'required|string|max:50',
            'chatter_login' => 'required|string|max:50',
            'chatter_display_name' => 'nullable|string|max:100',
            // When set, look up THIS login's follow age instead of the
            // chatter's own. Matches StreamElements' `!followage @user`
            // ergonomics. Leading '@' is stripped by the bot before send.
            'target_login' => 'nullable|string|max:50',
        ]);

        $owner = $this->resolveChannelOwner($data['channel_login']);
        if (! $owner) {
            return response()->json(['reply' => null]);
        }

        // Determine which Twitch user we're asking about. When target_login
        // is provided we need to resolve it via Helix users?login= because
        // the bot only knows the typed string, not the id. App token is
        // fine for that lookup - it's a public Twitch directory call.
        $targetId = $data['chatter_id'];
        $targetDisplay = $data['chatter_display_name'] ?? $data['chatter_login'];
        $isSelfQuery = true;

        if (! empty($data['target_login'])) {
            $appToken = $this->eventSub->getAppAccessToken();
            if (! $appToken) {
                return response()->json(['reply' => 'twitch lookup is offline, try again in a moment']);
            }

            try {
                $targetUser = $this->twitchApi->getUserByLogin($appToken, $data['target_login']);
            } catch (Exception) {
                $targetUser = null;
            }

            if (! $targetUser) {
                return response()->json(['reply' => "no twitch user named @{$data['target_login']}"]);
            }

            $targetId = $targetUser['id'];
            $targetDisplay = $targetUser['display_name'] ?? $targetUser['login'];
            $isSelfQuery = strtolower($targetUser['login']) === strtolower($data['chatter_login']);
        }

        // The broadcaster following their own channel is technically Twitch-
        // valid (creators auto-follow themselves on account creation) but
        // chat-wise it's the kind of trivia nobody wanted. Bounce it.
        if ($targetId === $owner->twitch_id) {
            $you = $isSelfQuery ? 'you' : "@$targetDisplay";
            $owns = $isSelfQuery ? 'own' : 'owns';

            return response()->json([
                'reply' => "$you $owns this channel, so the follow date isn't all that meaningful",
            ]);
        }

        if (! $this->twitchTokens->ensureValidToken($owner)) {
            return response()->json(['reply' => null]);
        }
        $owner->refresh();

        try {
            $follow = $this->twitchApi->getChannelFollower(
                $owner->access_token,
                $owner->twitch_id,
                $targetId,
            );
        } catch (Exception) {
            return response()->json(['reply' => null]);
        }

        if (! $follow || empty($follow['followed_at'])) {
            $reply = $isSelfQuery
                ? "you don't follow this channel yet"
                : "@$targetDisplay doesn't follow this channel yet";

            return response()->json(['reply' => $reply]);
        }

        $followedAt = Carbon::parse($follow['followed_at']);
        $duration = HumanDuration::between($followedAt, Carbon::now());

        $subject = $isSelfQuery ? 'you have' : "@$targetDisplay has";

        return response()->json([
            'reply' => "$subject been following for $duration",
        ]);
    }

    private function resolveChannelOwner(string $login): ?User
    {
        $login = strtolower($login);

        return User::where('bot_enabled', true)
            ->whereNotNull('twitch_data')
            ->get()
            ->first(fn (User $u) => strtolower($u->twitch_data['login'] ?? '') === $login);
    }
}
