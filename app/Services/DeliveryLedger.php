<?php

namespace App\Services;

use App\Enums\DeliveryOutcome;
use App\Models\ExternalEvent;
use App\Models\TwitchEvent;
use Illuminate\Database\Eloquent\Model;

/**
 * The four ledger columns on twitch_events / external_events, written from
 * three places: the request (a no_target outcome, or the alert_id of the
 * broadcast it just queued), the queue worker (delivered / no_listener with
 * the connection count Reverb reported), and the JobFailed listener (failed,
 * once, after the last retry).
 *
 * Every write is best-effort: the ledger must never break the path it
 * observes. A failure is reported and swallowed.
 *
 * See docs/design/event-delivery-ledger-2026-08.md.
 */
class DeliveryLedger
{
    /**
     * The request already knows the outcome: nothing for the overlay
     * (a no_target reason), or the alert could not be built (token_invalid,
     * render_failed). Record it.
     */
    public function record(?Model $row, DeliveryOutcome $outcome): void
    {
        if ($row === null) {
            return;
        }

        $this->write(fn () => $row->update(['outcome' => $outcome->value]));
    }

    /**
     * The request queued an AlertTriggered for this row; the worker will
     * close it by this id.
     */
    public function stamp(?Model $row, string $alertId): void
    {
        if ($row === null) {
            return;
        }

        $this->write(fn () => $row->update(['alert_id' => $alertId]));
    }

    /**
     * Reverb accepted the broadcast. Zero rows matched is normal: replays and
     * test cheers mint alert ids with no row behind them.
     */
    public function close(string $alertId, int $connections): void
    {
        $values = [
            'outcome' => DeliveryOutcome::forConnections($connections)->value,
            'delivered_at' => now(),
            'connections' => $connections,
        ];

        $this->write(function () use ($alertId, $values) {
            TwitchEvent::where('alert_id', $alertId)->update($values);
            ExternalEvent::where('alert_id', $alertId)->update($values);
        });
    }

    /**
     * The broadcast job exhausted its retries.
     */
    public function fail(string $alertId): void
    {
        $values = ['outcome' => DeliveryOutcome::Failed->value];

        $this->write(function () use ($alertId, $values) {
            TwitchEvent::where('alert_id', $alertId)->update($values);
            ExternalEvent::where('alert_id', $alertId)->update($values);
        });
    }

    private function write(callable $op): void
    {
        try {
            $op();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
