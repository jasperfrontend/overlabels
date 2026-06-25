<?php

namespace App\Jobs;

use App\Events\TtsAudioReady;
use App\Services\Tts\TtsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Calls ElevenLabs to synthesize the resolved TTS sentence for an alert, then
 * broadcasts TtsAudioReady so the overlay can play it. Runs after the alert
 * has already fired (AlertTriggered was broadcast synchronously from the
 * caller); the overlay handles late-arriving audio by scheduling playback
 * against the alert's tts_delay_ms.
 *
 * TTS is best-effort - failure logs and stops, never retries to avoid burning
 * credits on permanently-malformed input. The alert itself is unaffected.
 */
class SynthesizeAlertTts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * @param  array<int,string>|null  $targetSlugs  Static overlay slugs this alert
     *                                               targets; null = fire on all.
     */
    public function __construct(
        public readonly string $alertId,
        public readonly string $broadcasterId,
        public readonly string $text,
        public readonly ?array $targetSlugs = null,
    ) {}

    public function handle(TtsService $tts): void
    {
        $audioUrl = $tts->synthesize($this->text);

        if ($audioUrl === null) {
            Log::info('SynthesizeAlertTts: synthesis returned null, skipping broadcast', [
                'alert_id' => $this->alertId,
                'broadcaster_id' => $this->broadcasterId,
            ]);

            return;
        }

        broadcast(new TtsAudioReady(
            alertId: $this->alertId,
            broadcasterId: $this->broadcasterId,
            audioUrl: $audioUrl,
            targetSlugs: $this->targetSlugs,
        ));
    }
}
