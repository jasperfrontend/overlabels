<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlertTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $alertId;

    public string $html;

    public string $css;

    public array $data;

    public int $duration;

    public string $broadcasterId;

    public ?array $targetOverlaySlugs;

    public ?string $alertTemplateSlug;

    public ?string $ttsText;

    public int $ttsDelayMs;

    public ?string $alertSoundUrl;

    /**
     * Create a new event instance.
     *
     * $alertId is a server-generated UUID. The overlay correlates the matching
     * TtsAudioReady broadcast back to this alert so it can schedule audio
     * playback relative to the alert's tts_delay_ms (voice arrives after SFX).
     */
    public function __construct(
        string $alertId,
        string $html,
        string $css,
        array $data,
        int $duration,
        string $broadcasterId,
        ?array $targetOverlaySlugs = null,
        ?string $alertTemplateSlug = null,
        ?string $ttsText = null,
        int $ttsDelayMs = 0,
        ?string $alertSoundUrl = null
    ) {
        $this->alertId = $alertId;
        $this->html = $html;
        $this->css = $css;
        $this->data = $data;
        $this->duration = $duration;
        $this->broadcasterId = $broadcasterId;
        $this->targetOverlaySlugs = $targetOverlaySlugs;
        $this->alertTemplateSlug = $alertTemplateSlug;
        $this->ttsText = $ttsText;
        $this->ttsDelayMs = max(0, $ttsDelayMs);
        $this->alertSoundUrl = $alertSoundUrl;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('alerts.'.$this->broadcasterId),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'alert' => [
                'alert_id' => $this->alertId,
                'html' => $this->html,
                'css' => $this->css,
                'data' => $this->data,
                'duration' => $this->duration,
                'timestamp' => now()->timestamp,
                'target_overlay_slugs' => $this->targetOverlaySlugs,
                // Reference to the alert template's compiled utility CSS. The
                // overlay preloads a { slug: compiled_css } map on mount so we
                // keep this broadcast payload small even when the template
                // carries a large utility stylesheet.
                'alert_template_slug' => $this->alertTemplateSlug,
                // Milliseconds to wait after the alert fires before playing TTS
                // audio, so alert sounds/animations can play first. The overlay
                // schedules playback relative to this delay when the matching
                // TtsAudioReady broadcast arrives. 0 = play as soon as audio is
                // ready. The resolved TTS string itself is NOT broadcast - it's
                // pre-synthesized server-side by SynthesizeAlertTts.
                'tts_delay_ms' => $this->ttsDelayMs,
                // Fallback URL for the alert sound. Normally the overlay reads
                // the sound URL from the mount-time preload map (keyed by
                // alert_template_slug). This field covers the case where a
                // template was created mid-session and isn't in that map yet.
                'alert_sound_url' => $this->alertSoundUrl,
            ],
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'alert.triggered';
    }
}
