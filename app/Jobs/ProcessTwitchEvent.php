<?php

namespace App\Jobs;

use App\Http\Controllers\TwitchEventSubController;
use App\Models\TwitchEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Everything a Twitch notification sets off, after the webhook has answered.
 *
 * The webhook used to do all of it before answering: token refresh, avatar
 * enrichment, counters, alert render (with its own Helix calls), broadcasts.
 * Measured on prod 2026-11-14: about 1.4 s per follow and 0.7 s per raid of
 * server time, so 30 follows at once took 17 s to answer on the web role's few
 * PHP threads. Twitch revokes a subscription that keeps failing to answer
 * (notification_failures_exceeded). Now the webhook verifies, scrubs and
 * stores the row - the duplicate guard - dispatches this, and answers.
 *
 * Runs on its own queue (QUEUE) with its own single worker, the `events` role
 * in config/deploy.yml. One worker on purpose: events are handled in the order
 * Twitch delivered them, which GiftSubAbsorber depends on (a gift must be
 * handled before its recipients), and alerts never wait behind a TTS job on
 * the default worker. One try: a retry after a half-done run would fire the
 * alert twice.
 */
class ProcessTwitchEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const string QUEUE = 'twitch-events';

    public int $tries = 1;

    public int $timeout = 120;

    /** @param array $data the scrubbed notification body, `subscription` + `event` */
    public function __construct(
        public readonly int $twitchEventId,
        public readonly array $data,
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function handle(TwitchEventSubController $controller): void
    {
        $row = TwitchEvent::find($this->twitchEventId);

        // Deleted in the meantime (viewer erasure, a test event's cleanup).
        if ($row === null) {
            return;
        }

        $controller->processStoredEvent($row, $this->data);
    }
}
