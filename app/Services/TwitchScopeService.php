<?php

namespace App\Services;

use App\Models\User;

/**
 * Scope bookkeeping for the Twitch OAuth grant.
 *
 * Users authenticated before the platform required a given scope still carry
 * their original grant until they reauthorize. This service reconciles a
 * user's stored scopes against REQUIRED_SCOPES and exposes the delta so the
 * UI can nudge them to re-login when new capabilities (hype train, polls,
 * predictions, charity) are needed.
 */
class TwitchScopeService
{
    /**
     * Full list of scopes the platform currently asks for at /auth/redirect/twitch.
     * Keep this in sync with the scopes() call in routes/web.php - this is the
     * authoritative "should have" set that drives stale detection.
     */
    public const array REQUIRED_SCOPES = [
        'user:read:follows',
        'user:read:subscriptions',
        'channel:read:subscriptions',
        'channel:read:redemptions',
        'channel:read:goals',
        'channel:moderate',
        'moderator:read:followers',
        'channel:read:hype_train',
        'channel:read:charity',
        'channel:read:polls',
        'channel:read:predictions',
        // Required for channel.cheer. Without it Twitch will not create the
        // subscription, so no cheer ever reaches the platform: the Bits Cheer
        // trigger, the bits amount variants, cheers_this_stream and the
        // all-time bits_received pair were all reachable only from the
        // integrations test button until this was requested.
        'bits:read',
        // Authorizes the shared @overlabels chatbot to send messages in the
        // streamer's channel via the Send Chat Message API using an app
        // access token. Without this scope (or moderator status for the bot
        // account), Twitch returns 401 on bot replies and the Chat Bot
        // Badge does not render.
        'channel:bot',
        // The living title (LivingTitleService) writes the stream title and
        // category through PATCH helix/channels. This is the only write scope
        // the platform holds; everything above is read.
        'channel:manage:broadcast',
    ];

    /**
     * Extra scopes the BOT ACCOUNT grants to this app, on top of REQUIRED_SCOPES,
     * by logging in through /auth/redirect/twitch?scopes=bot. Never asked of a
     * streamer.
     *
     * channel.chat.notification is created with this app's app access token
     * and names the bot as the chatting user in its condition. Twitch checks
     * scopes per client id: the bot's user:bot on its own Twitch app (the
     * bot_tokens grant) counts for nothing here, so without these on THIS
     * client every create is a 403 "subscription missing proper authorization".
     */
    public const array BOT_ACCOUNT_SCOPES = [
        'user:read:chat',
        'user:bot',
    ];

    /**
     * Scopes granted by every user authenticated before twitch_scopes existed.
     * Used as the fallback when users.twitch_scopes is null so we don't
     * falsely flag every legacy user's currently-working scopes as missing.
     */
    public const array LEGACY_SCOPES = [
        'user:read:follows',
        'user:read:subscriptions',
        'channel:read:subscriptions',
        'channel:read:redemptions',
        'channel:read:goals',
        'channel:moderate',
        'moderator:read:followers',
    ];

    /**
     * EventSub type -> required scope map. Used by UserEventSubManager to skip
     * subscription creation for events whose scope isn't granted (rather than
     * letting Twitch reject and pollute the failed bucket).
     */
    public const array EVENT_TYPE_TO_SCOPE = [
        'channel.cheer' => 'bits:read',
        'channel.hype_train.begin' => 'channel:read:hype_train',
        'channel.hype_train.progress' => 'channel:read:hype_train',
        'channel.hype_train.end' => 'channel:read:hype_train',
        'channel.charity_campaign.donate' => 'channel:read:charity',
        'channel.charity_campaign.start' => 'channel:read:charity',
        'channel.charity_campaign.progress' => 'channel:read:charity',
        'channel.charity_campaign.stop' => 'channel:read:charity',
        'channel.goal.begin' => 'channel:read:goals',
        'channel.goal.progress' => 'channel:read:goals',
        'channel.goal.end' => 'channel:read:goals',
        'channel.poll.begin' => 'channel:read:polls',
        'channel.poll.progress' => 'channel:read:polls',
        'channel.poll.end' => 'channel:read:polls',
        'channel.prediction.begin' => 'channel:read:predictions',
        'channel.prediction.progress' => 'channel:read:predictions',
        'channel.prediction.lock' => 'channel:read:predictions',
        'channel.prediction.end' => 'channel:read:predictions',
        'channel.chat.notification' => 'channel:bot',
    ];

    /**
     * Get the user's granted scopes, falling back to the legacy set when
     * twitch_scopes is null (pre-existed this column).
     *
     * @return array<int, string>
     */
    public function getUserScopes(User $user): array
    {
        if ($user->twitch_scopes === null) {
            return self::LEGACY_SCOPES;
        }

        return array_values(array_filter($user->twitch_scopes, fn ($s) => is_string($s) && $s !== ''));
    }

    /**
     * Scopes the platform requires that the user has not granted.
     *
     * @return array<int, string>
     */
    public function getMissingScopes(User $user): array
    {
        return array_values(array_diff(self::REQUIRED_SCOPES, $this->getUserScopes($user)));
    }

    public function hasScope(User $user, string $scope): bool
    {
        return in_array($scope, $this->getUserScopes($user), true);
    }

    /**
     * Sanitize a scope list from Twitch/Socialite (can be array or space-
     * separated string; explode on empty yields ['']) into a clean list.
     *
     * @param  mixed  $scopes
     * @return array<int, string>
     */
    public static function sanitizeScopeList($scopes): array
    {
        if (is_string($scopes)) {
            $scopes = explode(' ', $scopes);
        }
        if (! is_array($scopes)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(fn ($s) => is_string($s) ? trim($s) : '', $scopes),
            fn ($s) => $s !== ''
        )));
    }
}
