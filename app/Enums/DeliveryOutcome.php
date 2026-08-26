<?php

namespace App\Enums;

/**
 * What became of an inbound event's alert. Two families.
 *
 * Scored - the row had an alert with overlay work and it was broadcast:
 *   delivered    Reverb accepted it and at least one connection was subscribed
 *   no_listener  Reverb accepted it and nobody was subscribed
 *   failed       the broadcast job exhausted its retries
 *
 * no_target - there never was an alert for the overlay. Reported, never scored:
 *   no_mapping   no enabled alert mapping for this event type
 *   muted        alerts:muted was on
 *   chat_only    the alert had no HTML, sound or TTS - the bot's alone
 *   unknown_user no account for the broadcaster (row has user_id = null)
 *
 * A row with alert_id set and outcome null is in flight: broadcast queued,
 * not yet closed by the worker. See docs/design/event-delivery-ledger-2026-08.md.
 */
enum DeliveryOutcome: string
{
    case Delivered = 'delivered';
    case NoListener = 'no_listener';
    case Failed = 'failed';

    case NoMapping = 'no_mapping';
    case Muted = 'muted';
    case ChatOnly = 'chat_only';
    case UnknownUser = 'unknown_user';

    /**
     * Whether this outcome counts toward a success rate. no_target outcomes
     * are context, never a mark against delivery.
     */
    public function isScored(): bool
    {
        return match ($this) {
            self::Delivered, self::NoListener, self::Failed => true,
            default => false,
        };
    }

    public static function forConnections(int $connections): self
    {
        return $connections >= 1 ? self::Delivered : self::NoListener;
    }
}
