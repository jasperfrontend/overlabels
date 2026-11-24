<?php

namespace App\Jobs;

use App\Http\Controllers\TwitchEventSubController;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\Alerts\AlertBursts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Plays the alert a held event was waiting for: its own when nothing joined
 * it, one burst alert when others did. See AlertBursts.
 *
 * On the streamer's own events lane, so it runs on the same single worker as
 * the events that join the burst and after every event queued before it. One try, like
 * ProcessTwitchEvent: a retry after a half-done run would fire the alert twice.
 */
class FlushAlertBurst implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    /**
     * @param  array  $data  the first event's notification body, as ProcessTwitchEvent had it
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $kind,
        public readonly int $twitchEventId,
        public readonly array $data,
    ) {
        // The streamer's own lane, so it runs after the events queued before it.
        $this->onQueue(ProcessTwitchEvent::queueFor(ProcessTwitchEvent::broadcasterOf($data)));
    }

    public function handle(AlertBursts $bursts, TwitchEventSubController $controller): void
    {
        $user = User::find($this->userId);
        if ($user === null) {
            return;
        }

        $burst = $bursts->flush($user, $this->kind);

        // Deleted in the meantime (viewer erasure, a test event's cleanup):
        // nothing to play, but the burst above was still decided and closed.
        $row = TwitchEvent::find($this->twitchEventId);
        if ($row === null) {
            return;
        }

        $data = $this->data;
        if ($burst !== null) {
            $data['event']['burst_count'] = $burst['count'];
            $data['event']['burst_started_at'] = $burst['started_at'];
        }

        $controller->fireAlert($user, $data, $row);
    }

    public function failed(?Throwable $e): void
    {
        $user = User::find($this->userId);
        if ($user !== null) {
            app(AlertBursts::class)->forget($user, $this->kind);
        }
    }
}
