<?php

namespace App\Services\Bot;

use App\Events\BotChannelsChanged;
use App\Models\User;

/**
 * Tells the bot its command map is stale.
 *
 * The bot refreshes that map on a 60 second poll (REFRESH_MS in the bot's
 * src/index.js) and also, instantly, whenever `bot.channels.changed` arrives
 * over Reverb. Until this existed the app only ever raised that event when a
 * user toggled the bot on or off, so a command created any other way - the
 * dashboard, or `!ol cmd add` in chat - was invisible to the bot for up to a
 * minute. The bot silently ignores a command it has never heard of, so the
 * streamer's brand new `!wins` did nothing at all and said nothing about why.
 *
 * That is worst exactly where it is most likely: `!ol cmd add` invites you to
 * try the command a second later.
 *
 * COALESCED PER REQUEST. BotChannelsChanged is ShouldBroadcastNow, and one
 * user action can touch many rows - opting into the bot seeds seventeen
 * BotCommand rows in a loop. Announcing per row would put seventeen synchronous
 * broadcasts on the wire for one click, and broadcasts are the metered resource
 * here. A login already announced during this request is dropped; the bot
 * re-reads the whole map either way, so the second broadcast would carry no
 * information the first did not.
 *
 * Bound with scoped() so the dedupe list lives exactly as long as one request
 * or queued job does. Not singleton(): a queue worker boots the container once
 * and holds plain singletons for the life of the process, so the list would
 * never empty and every job after the first would skip announcing. Not static
 * either - that leaks between tests in the same process.
 */
class BotCommandMapAnnouncer
{
    /** @var array<string,true> Logins already announced during this request. */
    private array $announced = [];

    /**
     * Announce that $user's command set changed. Safe to call repeatedly.
     */
    public function announce(?User $user): void
    {
        $login = strtolower((string) ($user?->twitch_data['login'] ?? ''));

        if ($login === '') {
            return;
        }

        // BotCommandController::index() only lists users with bot_enabled, so
        // a user who has not opted in contributes nothing to the map and there
        // is nothing for the bot to re-read. Signing up seeds the default
        // command set while the bot is still off, and broadcasts are metered -
        // announcing there would spend one on every registration for no effect.
        // Turning the bot ON is announced by BotSettingsController, which is
        // the moment the channel actually enters the map.
        if (! $user->bot_enabled) {
            return;
        }

        if (isset($this->announced[$login])) {
            return;
        }

        $this->announced[$login] = true;

        BotChannelsChanged::dispatch($login, true);
    }
}
