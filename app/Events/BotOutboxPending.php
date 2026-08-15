<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * "There is at least one unsent message waiting." Tells the bot to drain the
 * outbox now instead of waiting for its next poll.
 *
 * DELIBERATELY CARRIES NO PAYLOAD. The bot claims everything pending in one
 * atomic transaction (BotOutboxController::index), so the only thing it needs
 * to know is that something is there. Shipping the message itself would put
 * chat text on a public channel and would need per-user routing, while buying
 * nothing - the fetch happens either way.
 *
 * Rides the same `bot-channels` public channel the bot is already subscribed
 * to for bot.channels.changed, so this needs no new subscription, no new auth,
 * and no second connection.
 */
class BotOutboxPending implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function broadcastOn(): array
    {
        return [
            new Channel('bot-channels'),
        ];
    }

    public function broadcastWith(): array
    {
        return [];
    }

    public function broadcastAs(): string
    {
        return 'bot.outbox.pending';
    }
}
