<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Appends a one-off "here is what just got stored about you" line to the bot's
 * reply, the first time a viewer triggers anything.
 *
 * WHY THIS AND NOT A CONSENT GATE. Asking a viewer to type `!accept` before
 * anything works would mean keeping an acceptance record for every viewer who
 * ever typed in an Overlabels channel - including everyone who ignored it -
 * which is a bigger, more permanent viewer database than the one it exists to
 * justify. It would also replace a strong "they explicitly asked to be on
 * screen" basis with consent, which is a higher bar and cannot be walked back
 * once claimed. So: notice, not gate. The reply still works the first time.
 *
 * WHY A CACHE KEY AND NOT A TABLE. A table of who has been told would be that
 * same permanent registry by another name. This expires on roughly the cadence
 * the underlying data does, so a viewer who comes back next year is told again,
 * which is the right outcome anyway. Losing the cache costs one repeated line.
 */
class ViewerNoticeService
{
    /**
     * Matches the 90-day retention on check-ins and events. A viewer whose data
     * has aged out entirely is a new viewer again, and should be told again.
     */
    private const int SEEN_TTL_SECONDS = 90 * 24 * 60 * 60;

    /**
     * Twitch cuts a chat message at 500. Leave room for the notice rather than
     * appending it and having the interesting half of the reply truncated.
     */
    private const int MAX_REPLY_LENGTH = 480;

    private const string NOTICE = ' · your data: overlabels.com/viewers';

    /**
     * Return the reply with the notice appended, if this viewer has not been
     * told recently and it fits. Marks them as told either way it fits.
     */
    public function decorate(string $reply, ?string $twitchId): string
    {
        if ($twitchId === null || $twitchId === '' || $reply === '') {
            return $reply;
        }

        if (mb_strlen($reply) + mb_strlen(self::NOTICE) > self::MAX_REPLY_LENGTH) {
            return $reply;
        }

        $key = $this->cacheKey($twitchId);

        // add() is atomic and returns false when the key already exists, so two
        // commands arriving together cannot both decide they are the first.
        if (! Cache::add($key, true, self::SEEN_TTL_SECONDS)) {
            return $reply;
        }

        return $reply.self::NOTICE;
    }

    /**
     * Used by the erasure path: someone who has just asked to be forgotten
     * should be told again if they ever come back, not treated as informed.
     */
    public function forget(string $twitchId): void
    {
        Cache::forget($this->cacheKey($twitchId));
    }

    private function cacheKey(string $twitchId): string
    {
        return 'viewer:notified:'.$twitchId;
    }
}
