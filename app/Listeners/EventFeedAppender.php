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

    public function handleTwitchEvent(TwitchEventReceived $event): void
    {
        $user = User::where('twitch_id', $event->broadcasterId)->first();
        if (! $user) {
            return;
        }

        $this->feeds->handleEvent($user, 'twitch', $event->eventType, $event->eventData, null);
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
