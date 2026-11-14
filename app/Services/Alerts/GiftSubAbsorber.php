<?php

namespace App\Services\Alerts;

use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * One gift bomb, one alert.
 *
 * When someone gifts 25 subs, Twitch sends one `channel.subscription.gift`
 * carrying `total: 25`, then one `channel.subscribe` with `is_gift: true` per
 * recipient. A recipient's message names only the recipient - there is no
 * gifter field - and they trickle in over seconds (13 s for 25 on prod,
 * 2026-11-14). Every one of them used to fire its own New Subscriber alert;
 * nobody noticed while alerts replaced each other, but the overlay queues
 * them now, and 25 sub alerts would play for minutes.
 *
 * So the gift alert, which already knows the final number, announces the
 * batch, and the next `total` gifted subs on that channel are absorbed: no
 * alert, no TTS, no chat line. They still count everywhere else (counters,
 * sub lists, tags). Counting down, not a timer, decides when the batch is
 * done; the countdown expires WINDOW_SECONDS after the last gift or
 * recipient, so a recipient Twitch never delivers cannot swallow an
 * unrelated gifted sub much later. A gifted sub with no countdown running
 * alerts as it always did.
 */
final class GiftSubAbsorber
{
    public const int WINDOW_SECONDS = 120;

    /** A gift alert for `$total` subs fired: the next `$total` gifted subs are part of it. */
    public function expect(User $user, int $total): void
    {
        if ($total <= 0) {
            return;
        }

        $this->locked($user, function () use ($user, $total) {
            $left = max(0, (int) Cache::get(self::key($user), 0));
            Cache::put(self::key($user), $left + $total, self::WINDOW_SECONDS);

            return true;
        });
    }

    /** Whether this gifted sub belongs to a gift alert that already announced it. */
    public function absorb(User $user, array $event): bool
    {
        if (($event['is_gift'] ?? false) !== true) {
            return false;
        }

        return $this->locked($user, function () use ($user) {
            $left = (int) Cache::get(self::key($user), 0);
            if ($left <= 0) {
                return false;
            }
            Cache::put(self::key($user), $left - 1, self::WINDOW_SECONDS);

            return true;
        });
    }

    /**
     * Gift and recipient webhooks arrive concurrently, so the read-modify-write
     * is serialised. If the lock cannot be had, the sub simply alerts: an extra
     * alert is the safe failure, a swallowed one is not.
     */
    private function locked(User $user, callable $fn): bool
    {
        try {
            return (bool) Cache::lock(self::key($user).':lock', 5)->block(2, $fn);
        } catch (LockTimeoutException) {
            return false;
        }
    }

    private static function key(User $user): string
    {
        return 'gift_absorb:'.$user->id;
    }
}
