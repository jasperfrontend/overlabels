<?php

namespace App\Services\Bot;

use App\Events\BotChannelsChanged;
use App\Events\BotOutboxPending;
use App\Models\User;

/**
 * Nudges the bot when something it polls for has changed, so it can act now
 * instead of on its next tick.
 *
 * The bot has two poll loops, and both exist because a push can always fail:
 *   - the command map, every 60s   -> nudged by commandMapChanged()
 *   - the chat outbox, every 2s    -> nudged by outboxPending()
 * Neither poll is being removed. A push makes the common case instant; the
 * poll is what makes a missed push cost a delay rather than a lost message.
 * That mattered more than it should have: REVERB_HOST spent months pointed at
 * the wrong host, and the polls are the only reason nobody noticed.
 *
 * COALESCED PER REQUEST. Both events are ShouldBroadcastNow and broadcasts are
 * the metered resource here, while one user action can touch many rows -
 * opting into the bot seeds seventeen BotBuiltin rows, and one gamejam round
 * writes several outbox messages. The bot re-reads everything pending either
 * way, so a second broadcast in the same request carries no information the
 * first did not.
 *
 * Bound with scoped() so the dedupe bag lives exactly as long as one request
 * or queued job. Not singleton(): a queue worker boots the container once and
 * holds plain singletons for the life of the process, so the bag would never
 * empty and every job after the first would silently skip announcing. Not
 * static either - that leaks between tests in the same process.
 */
class BotPushAnnouncer
{
    /** @var array<string,true> Nudges already sent during this request. */
    private array $announced = [];

    /**
     * Announce that $user's set of chat commands changed. Safe to call
     * repeatedly.
     */
    public function commandMapChanged(?User $user): void
    {
        $login = strtolower((string) ($user?->twitch_data['login'] ?? ''));

        if ($login === '') {
            return;
        }

        // BotCommandMapController::index() only lists users with bot_enabled, so
        // a user who has not opted in contributes nothing to the map and there
        // is nothing for the bot to re-read. Signing up seeds the default
        // command set while the bot is still off, and broadcasts are metered -
        // announcing there would spend one on every registration for no effect.
        // Turning the bot ON is announced by BotSettingsController, which is
        // the moment the channel actually enters the map.
        if (! $user->bot_enabled) {
            return;
        }

        if ($this->firstTime("map:$login")) {
            BotChannelsChanged::dispatch($login, true);
        }
    }

    /**
     * Announce that the outbox has something in it. Safe to call repeatedly.
     *
     * Not keyed by user: the bot drains every pending row in one claim, so one
     * nudge covers messages for any number of channels.
     */
    public function outboxPending(): void
    {
        if ($this->firstTime('outbox')) {
            BotOutboxPending::dispatch();
        }
    }

    /**
     * True the first time $key is seen this request, false every time after.
     */
    private function firstTime(string $key): bool
    {
        if (isset($this->announced[$key])) {
            return false;
        }

        return $this->announced[$key] = true;
    }
}
