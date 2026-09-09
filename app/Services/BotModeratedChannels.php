<?php

namespace App\Services;

use App\Models\BotToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The channels where the @overlabels bot account is a moderator, from the
 * bot's own side of Twitch: GET helix/moderation/channels with the bot
 * account's user token. One call answers for every streamer at once, and
 * no streamer grants anything - the scope (`user:read:moderated_channels`)
 * sits on the bot token minted on the admin Twitch Bot page.
 *
 * Bot replies work by mod status, not by the channel:bot scope (see
 * CLAUDE.md), so this is the check that says whether "/mod overlabels"
 * was ever typed.
 *
 * Unknown is a real answer here: no token, a token without the scope, or
 * Twitch not answering all return null, and the wire reads NOT_APPLICABLE
 * rather than accusing a streamer of a step they may have done.
 */
class BotModeratedChannels
{
    public const TTL_SECONDS = 300;

    public const SCOPE = 'user:read:moderated_channels';

    private const CACHE_KEY = 'bot:moderated_channels';

    /**
     * Broadcaster ids the bot moderates, or null when that cannot be known
     * right now. Cached for TTL_SECONDS; the unknown answer is cached too,
     * so a dead token does not cost a Helix round trip per page view.
     *
     * @return list<string>|null
     */
    public function broadcasterIds(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if ($cached !== null) {
            return $cached === 'unknown' ? null : $cached;
        }

        $ids = $this->fetch();
        Cache::put(self::CACHE_KEY, $ids ?? 'unknown', now()->addSeconds(self::TTL_SECONDS));

        return $ids;
    }

    public function moderates(string $broadcasterId): ?bool
    {
        $ids = $this->broadcasterIds();

        return $ids === null ? null : in_array($broadcasterId, $ids, true);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return list<string>|null
     */
    private function fetch(): ?array
    {
        $token = BotToken::where('account', 'overlabels')->first();
        $botId = (string) config('services.twitchbot.user_id');
        $clientId = (string) config('services.twitchbot.client_id');

        if (! $token || $botId === '' || $clientId === '') {
            return null;
        }

        if (! in_array(self::SCOPE, $token->scopes ?? [], true)) {
            return null;
        }

        $ids = [];
        $cursor = null;

        do {
            $response = Http::withToken($token->access_token)
                ->withHeaders(['Client-Id' => $clientId])
                ->timeout(5)
                ->get('https://api.twitch.tv/helix/moderation/channels', array_filter([
                    'user_id' => $botId,
                    'first' => 100,
                    'after' => $cursor,
                ]));

            if (! $response->successful()) {
                Log::warning('BotModeratedChannels: Helix refused the moderated channels lookup', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            foreach ($response->json('data') ?? [] as $row) {
                $ids[] = (string) ($row['broadcaster_id'] ?? '');
            }

            $cursor = $response->json('pagination.cursor');
        } while (is_string($cursor) && $cursor !== '');

        return array_values(array_filter($ids, fn (string $id) => $id !== ''));
    }
}
