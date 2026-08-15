<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotChatOutbox extends Model
{
    protected $table = 'bot_chat_outbox';

    public const null UPDATED_AT = null;

    /**
     * How long a queued message stays worth posting.
     *
     * Chat replies are perishable in a way almost nothing else here is: a
     * `!wins` answer, a sub thank-you or a gamejam round result means nothing
     * once the conversation has moved on, and posting one late is worse than
     * not posting it - it reads as the bot malfunctioning. So the claim path
     * drops anything older than this instead of delivering it.
     *
     * 60 seconds is 30x the normal 2s poll (and the push usually delivers
     * inside a second), so ordinary jitter can never trip it. It is also long
     * enough to cover a bot restart, which is the common case for a message
     * sitting unclaimed - a deploy should not silently eat replies queued
     * while the container swaps.
     *
     * Tune with the discard rate in mind: a steady trickle of discarded rows
     * means the bot is flapping, not that this number is wrong.
     */
    public const int STALE_AFTER_SECONDS = 60;

    protected $fillable = [
        'user_id',
        'message',
        'sent_at',
        'discarded_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'discarded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
