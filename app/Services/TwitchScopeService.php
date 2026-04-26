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
