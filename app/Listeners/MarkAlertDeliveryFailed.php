<?php

namespace App\Listeners;

use App\Events\AlertTriggered;
use App\Services\DeliveryLedger;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Queue\Events\JobFailed;

/**
 * Closes a ledger row as `failed` when its AlertTriggered broadcast job has
 * exhausted its retries. This cannot live in the broadcaster: with
 * `queue:work --tries=3` a bad broadcast hits it three times, it cannot see
 * the attempt number, and a `failed` written on attempt one would be
 * overwritten by a retry that succeeds. JobFailed fires once, after the
 * last attempt, with the serialised job in hand.
 *
 * Auto-discovered by the typed parameter. Every other job class is ignored.
 */
class MarkAlertDeliveryFailed
{
    public function __construct(private DeliveryLedger $ledger) {}

    public function handle(JobFailed $event): void
    {
        $alertId = self::alertIdFrom($event->job->payload());

        if ($alertId !== null) {
            $this->ledger->fail($alertId);
        }
    }

    /**
     * The alert id inside a queued BroadcastEvent(AlertTriggered) payload, or
     * null for anything else. The command is PHP-serialised by the queue
     * (data.command), so this is the same unserialize the worker does.
     */
    public static function alertIdFrom(array $payload): ?string
    {
        $command = $payload['data']['command'] ?? null;
        if (! is_string($command) || ! str_contains($command, AlertTriggered::class)) {
            return null;
        }

        try {
            $job = unserialize($command);
        } catch (\Throwable) {
            return null;
        }

        if (! $job instanceof BroadcastEvent || ! $job->event instanceof AlertTriggered) {
            return null;
        }

        return $job->event->alertId;
    }
}
