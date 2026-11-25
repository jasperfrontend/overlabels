<?php

namespace App\Listeners;

use App\Events\ExternalEventStored;
use App\Events\TwitchEventReceived;
use App\Models\User;
use App\Services\Lists\EventFeedService;

/**
 * Appends incoming Twitch and external events to any List the user has turned
 * into a recent-events feed (StreamElements-style widget). Both handler
 * methods are auto-discovered by Laravel from their typed event parameter -
 * no explicit Event::listen registration, same as ListWriterAppend.
 *
 * Runs synchronously, mirroring ListWriterAppend: the work is a single
 * indexed lookup plus, only when a feed exists, one locked list write. When
 * the user has no feed lists it is one short query and out.
 */
class EventFeedAppender
{
    public function __construct(
        private readonly EventFeedService $feeds,
    ) {}

    // PHASE1-TIMING (#343): temporary, remove once the follow timings are read.
    public static ?float $phase1Ms = null;

    public static ?float $phase1LookupMs = null;

    public function handleTwitchEvent(TwitchEventReceived $event): void
    {
        $p1 = hrtime(true);
        $user = User::where('twitch_id', $event->broadcasterId)->first();
        self::$phase1LookupMs = round((hrtime(true) - $p1) / 1e6, 2);
        if (! $user) {
            self::$phase1Ms = (hrtime(true) - $p1) / 1e6;

            return;
        }

        $this->feeds->handleEvent($user, 'twitch', $event->eventType, $event->eventData, null);
        self::$phase1Ms = (hrtime(true) - $p1) / 1e6;
    }

    public function handleExternalEvent(ExternalEventStored $event): void
    {
        $user = User::find($event->userId);
        if (! $user) {
            return;
        }

        $this->feeds->handleEvent($user, $event->service, $event->eventType, null, $event->normalizedPayload);
    }
}
