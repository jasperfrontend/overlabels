<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired right after an ExternalEvent row is persisted (Ko-fi, StreamLabs,
 * StreamElements, BMAC, Fourthwall, ...). The symmetric counterpart to
 * TwitchEventReceived, which the Twitch ingest path already dispatches.
 *
 * Unlike TwitchEventReceived this does NOT broadcast - external services
 * fan out to overlays through AlertTriggered / ControlValueUpdated, both of
 * which have already discarded the raw event_type by the time they fire.
 * This internal-only event preserves the raw service/type/payload so feed
 * appenders (EventFeedAppender) can format a recent-events line.
 */
class ExternalEventStored
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>|null  $normalizedPayload
     */
    public function __construct(
        public int $userId,
        public string $service,
        public string $eventType,
        public ?array $normalizedPayload,
    ) {}
}
