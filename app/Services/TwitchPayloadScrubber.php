<?php

namespace App\Services;

/**
 * Removes third-party personal data from a Twitch EventSub payload before
 * Overlabels persists, enriches, renders or broadcasts it.
 *
 * The sibling of App\Services\External\PayloadScrubber, for the other side of
 * the platform. It is deliberately narrow: almost everything an EventSub event
 * carries about a viewer is their public display name, login, id and avatar,
 * which is exactly what a follower alert is for. Two things are not.
 *
 * 1. `outcomes[].top_predictors[]` on the prediction events. Twitch names every
 *    viewer who bet, with how many channel points they staked and won. Nothing
 *    in Overlabels reads it - TemplateDataMapperService surfaces only the
 *    aggregate `outcomes.N.users` and `outcomes.N.channel_points` - so it was
 *    being stored for 90 days, having avatars fetched for it, broadcast to
 *    overlays and printed in full on an admin page, purely because it arrived.
 *    Per-viewer wagering behaviour attached to a name is not ours to keep.
 *
 * 2. Anonymous cheers. Twitch nulls the user fields itself when `is_anonymous`
 *    is set, so in practice there is nothing to remove - but the platform was
 *    relying entirely on Twitch to do that, with no enforcement of its own. A
 *    replayed, hand-built or test event carrying both `is_anonymous: true` and
 *    a name would have been stored and put on stream. Anonymity a viewer chose
 *    should not depend on an upstream implementation detail.
 */
class TwitchPayloadScrubber
{
    /**
     * Keys removed wherever they appear, at any depth.
     */
    public const DENIED_KEYS = [
        'top_predictors',
    ];

    /**
     * Identity fields nulled when the payload declares itself anonymous.
     */
    private const ANONYMOUS_FIELDS = [
        'user_id',
        'user_login',
        'user_name',
        'user_avatar',
    ];

    /**
     * @param  array<array-key, mixed>  $event
     * @param  bool  $forceAnonymous  True when the acting viewer has asked to be
     *                                forgotten. Their identity is removed from
     *                                the payload exactly as an anonymous cheer's
     *                                is, so the streamer still gets the event and
     *                                the counter while we store nobody. Without
     *                                this, the next follow from an erased viewer
     *                                writes them straight back into twitch_events.
     * @return array<array-key, mixed>
     */
    public static function scrub(array $event, bool $forceAnonymous = false): array
    {
        $clean = self::stripDeniedKeys($event);

        if ($forceAnonymous || ! empty($clean['is_anonymous'])) {
            foreach (self::ANONYMOUS_FIELDS as $field) {
                if (array_key_exists($field, $clean)) {
                    $clean[$field] = null;
                }
            }
        }

        return $clean;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private static function stripDeniedKeys(array $payload): array
    {
        $denied = array_flip(array_map('strtolower', self::DENIED_KEYS));
        $clean = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && isset($denied[strtolower($key)])) {
                continue;
            }

            $clean[$key] = is_array($value) ? self::stripDeniedKeys($value) : $value;
        }

        return $clean;
    }
}
