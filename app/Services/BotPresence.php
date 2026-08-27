<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * What the bot last reported about which chats it is actually in.
 *
 * The bot subscribes to each channel's chat over EventSub WebSocket and
 * reconciles that set against the app's channel list every REFRESH_MS
 * (60 s) and on every push. After each reconcile it POSTs the logins it is
 * subscribed to right now (/internal/bot/presence). That is the only honest
 * source for "the bot is in your chat": the toggle is intent, and chat-stats
 * are skipped for idle channels, so silence there means a quiet chat far more
 * often than an absent bot.
 *
 * Two facts, both in the cache like B1's last delivery: when the bot last
 * reported at all, and when each login was last in a report. The wire reads
 * them; nothing else does. A missing report is the bot process being down or
 * restarting - a platform matter, not a streamer's loose end - so the wire
 * treats "no report" as not applicable, never as missing.
 */
class BotPresence
{
    /** A login absent from every report for this long is not in the chat. Five refreshes. */
    public const WINDOW_SECONDS = 300;

    private const TTL_SECONDS = 86400;

    /** @param  list<string>  $logins */
    public function record(array $logins): void
    {
        $now = now()->timestamp;
        $ttl = now()->addSeconds(self::TTL_SECONDS);

        Cache::put(self::reportKey(), $now, $ttl);
        foreach ($logins as $login) {
            Cache::put(self::loginKey($login), $now, $ttl);
        }
    }

    /** Unix seconds of the last report, or null if the bot has not reported (within a day). */
    public function lastReportAt(): ?int
    {
        $value = Cache::get(self::reportKey());

        return is_int($value) ? $value : null;
    }

    /** Unix seconds this login was last in a report, or null. */
    public function seenAt(string $login): ?int
    {
        $value = Cache::get(self::loginKey($login));

        return is_int($value) ? $value : null;
    }

    /** The bot has reported within the window. */
    public function reporting(): bool
    {
        $at = $this->lastReportAt();

        return $at !== null && $at >= now()->timestamp - self::WINDOW_SECONDS;
    }

    /** The bot has reported this login within the window. */
    public function present(string $login): bool
    {
        $at = $this->seenAt($login);

        return $at !== null && $at >= now()->timestamp - self::WINDOW_SECONDS;
    }

    private static function reportKey(): string
    {
        return 'bot:presence:reported_at';
    }

    private static function loginKey(string $login): string
    {
        return 'bot:presence:login:'.strtolower($login);
    }
}
