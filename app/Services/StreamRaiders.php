<?php

namespace App\Services;

use App\Models\StreamSession;
use App\Models\TwitchEvent;
use App\Models\User;

/**
 * The channels that raided this stream, newest first: the `[[[raiders]]]` tag
 * (Living Title "Thank your raiders", Jasper 2026-11-26).
 *
 * "This stream" is the live session: no live session, no raiders, so last
 * week's raiders never linger. A channel that raided twice is listed once, at
 * its newest raid. Logins only, which are public and are the point of a
 * thank-you; a raider who asked to be forgotten has no login on the stored row
 * (TwitchPayloadScrubber) and is skipped.
 */
class StreamRaiders
{
    /** More than a 140-character title could ever hold. */
    private const int MAX_RAIDS = 50;

    /** @return list<string> logins, newest raid first */
    public function logins(User $user): array
    {
        $session = StreamSession::activeFor($user);
        if ($session === null) {
            return [];
        }

        $logins = [];
        $rows = TwitchEvent::where('user_id', $user->id)
            ->where('event_type', 'channel.raid')
            ->where('created_at', '>=', $session->started_at)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::MAX_RAIDS)
            ->pluck('event_data');

        foreach ($rows as $data) {
            $login = strtolower(trim((string) ($data['from_broadcaster_user_login'] ?? '')));
            if ($login !== '' && ! in_array($login, $logins, true)) {
                $logins[] = $login;
            }
        }

        return $logins;
    }

    /** The tag's value: `@newest @older @oldest`, or empty. */
    public static function format(array $logins): string
    {
        return implode(' ', array_map(fn (string $login) => '@'.$login, $logins));
    }
}
