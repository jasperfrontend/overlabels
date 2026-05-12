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
     */
    public function __construct(
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
                // Pre-rendered TTS string from the alert template's tts_expression.
                // null when no expression is set or the user has gated TTS off via
                // their boolean `tts` control. Overlay speaks via SpeechSynthesisUtterance.
                'tts_text' => $this->ttsText,
                // Milliseconds to wait after the alert fires before speaking,
                // so alert sounds/animations can play first. 0 = speak immediately.
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
