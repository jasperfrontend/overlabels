<?php

namespace App\Observers;

use App\Models\BotChatOutbox;
use App\Services\Bot\BotPushAnnouncer;

/**
 * Tells the bot to drain the outbox the moment a message is queued, instead of
 * waiting up to 2 seconds for its next poll.
 *
 * `created` only. An update to an existing row is the bot claiming it
 * (sent_at being stamped by BotOutboxController), and nudging the bot about
 * the message it just took would be a broadcast per delivery, forever.
 *
 * An observer rather than a dispatch beside each write, because outbox rows
 * are created from bot command fires, alert replies, gamejam rounds and
 * list actions - and the next feature that wants to say something in chat
 * should get instant delivery without knowing this exists.
 */
class BotChatOutboxObserver
{
    public function __construct(
        private readonly BotPushAnnouncer $announcer,
    ) {}

    public function created(BotChatOutbox $message): void
    {
        $this->announcer->outboxPending();
    }
}
