<?php

namespace App\Observers;

use App\Services\Bot\BotPushAnnouncer;
use Illuminate\Database\Eloquent\Model;

/**
 * Pushes a refresh to the bot whenever anything the command map is built from
 * changes. Registered against all six of those models in AppServiceProvider.
 *
 * An observer rather than a dispatch() call at each save site, because those
 * sites are spread over the settings controllers, the `!ol` chat-admin service,
 * recipe installation and list management - and the next one nobody has written
 * yet. Missing one brings back exactly the bug this fixes, in one command type
 * only, which is the kind of thing that goes unnoticed for months.
 *
 * `saved` covers create and update: an expression that is renamed, disabled or
 * has its permission level changed moves the map just as much as a new one.
 */
class BotCommandMapObserver
{
    public function __construct(
        private readonly BotPushAnnouncer $announcer,
    ) {}

    public function saved(Model $model): void
    {
        $this->announce($model);
    }

    public function deleted(Model $model): void
    {
        $this->announce($model);
    }

    /**
     * All six observed models belong to a user; that user's Twitch login is
     * the key the bot's map is built on.
     */
    private function announce(Model $model): void
    {
        $this->announcer->commandMapChanged($model->user);
    }
}
