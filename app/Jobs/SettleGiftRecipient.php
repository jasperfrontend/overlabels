<?php

namespace App\Jobs;

use App\Enums\DeliveryOutcome;
use App\Http\Controllers\TwitchEventSubController;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\Alerts\GiftSubAbsorber;
use App\Services\DeliveryLedger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * A gifted sub that found no gift credit, given a second chance (#343).
 *
 * The front desk hands Laravel several batches at once, so a recipient can be
 * stored before the gift that announced it: in the chaos run an anonymous
 * gift sent 0.4 s before its recipient was stored after it, and the recipient
 * played its own sub alert. Such a recipient now waits GiftSubAbsorber::
 * WAIT_SECONDS and asks the absorber once more; only then does it alert as a
 * normal sub.
 *
 * Runs on the streamer's own lane, so the gift - queued before this job comes
 * due - has been handled by the time it runs.
 */
class SettleGiftRecipient implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public readonly int $userId,
        public readonly int $twitchEventId,
        public readonly array $data,
    ) {
        $this->onQueue(ProcessTwitchEvent::queueFor(ProcessTwitchEvent::broadcasterOf($data)));
    }

    public function handle(GiftSubAbsorber $absorber, DeliveryLedger $ledger, TwitchEventSubController $controller): void
    {
        $user = User::find($this->userId);
        $row = TwitchEvent::find($this->twitchEventId);
        if ($user === null || $row === null) {
            return;
        }

        if ($absorber->absorb($user, $this->data['event'] ?? [])) {
            $ledger->record($row, DeliveryOutcome::GiftAbsorbed);

            return;
        }

        $controller->fireAlert($user, $this->data, $row);
    }
}
