<?php

namespace App\Jobs;

use App\Http\Controllers\TwitchEventSubController;
use App\Models\TwitchEvent;
use App\Support\Phase2Laps;
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
 * Runs on one of LANES queues, each with exactly ONE worker (the `events`
 * roles in config/deploy.yml). A streamer always lands on the same lane, so
 * their events are handled in the order Twitch delivered them, which
 * GiftSubAbsorber and AlertBursts depend on (a gift before its recipients, a
 * burst's flush after the events queued before it). Different streamers run
 * side by side on different lanes, so a raid on one channel no longer holds
 * up every other channel's events (#343); only the channels that share its
 * lane wait. Two workers on one lane would break the order. One try: a retry
 * after a half-done run would fire the alert twice.
 */
class ProcessTwitchEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Lane 0's queue, and the only queue before lanes: jobs queued then still drain here. */
    public const string QUEUE = 'twitch-events';

    /** How many lanes. Each needs exactly one worker role in config/deploy.yml (EventLanesTest). */
    public const int LANES = 4;

    public int $tries = 1;

    public int $timeout = 120;

    /**
     * @param  array  $data  the scrubbed notification body, `subscription` + `event`
     * @param  string|null  $sentAt  Twitch-Eventsub-Message-Timestamp, so a notification
     *                               that reaches us late (an outage backlog) fires no alert.
     *                               Null on jobs queued before it existed: never late.
     */
    public function __construct(
        public readonly int $twitchEventId,
        public readonly array $data,
        public readonly ?string $sentAt = null,
    ) {
        $this->onQueue(self::queueFor(self::broadcasterOf($data)));
    }

    /**
     * The lane a streamer's events run on, by their Twitch id. Stable: the
     * same id always gets the same lane. No id (a payload without a
     * broadcaster) goes to lane 0.
     */
    public static function queueFor(?string $broadcasterTwitchId): string
    {
        if ($broadcasterTwitchId === null || $broadcasterTwitchId === '') {
            return self::QUEUE;
        }

        $lane = crc32($broadcasterTwitchId) % self::LANES;

        return $lane === 0 ? self::QUEUE : self::QUEUE.'-'.$lane;
    }

    /** @return list<string> every lane's queue, lane 0 first */
    public static function queues(): array
    {
        return array_map(fn (int $lane) => $lane === 0 ? self::QUEUE : self::QUEUE.'-'.$lane, range(0, self::LANES - 1));
    }

    /** The channel a notification is for: a raid's receiving end, else the broadcaster. */
    public static function broadcasterOf(array $data): ?string
    {
        $event = $data['event'] ?? [];
        $id = ($data['subscription']['type'] ?? null) === 'channel.raid'
            ? ($event['to_broadcaster_user_id'] ?? null)
            : ($event['broadcaster_user_id'] ?? null);

        return $id === null ? null : (string) $id;
    }

    public function handle(TwitchEventSubController $controller): void
    {
        Phase2Laps::start();
        $row = TwitchEvent::find($this->twitchEventId);
        Phase2Laps::lap('find_row');

        // Deleted in the meantime (viewer erasure, a test event's cleanup).
        if ($row === null) {
            return;
        }

        $controller->processStoredEvent($row, $this->data, $this->sentAt);
    }
}
