<?php

namespace App\Events;

use App\Models\OverlayTemplate;
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

    public string $head;

    /**
     * The event that fired the alert (`channel.poll.progress`), Twitch only.
     * The overlay queues alerts, and updates a `*.progress` alert already on
     * screen in place instead of queueing it again. It cannot read this from
     * `data`, which is pruned to the tags the alert's template uses.
     */
    public ?string $eventType;

    /**
     * Reverb refuses an events request over 10 000 bytes (`max_request_size`,
     * the whole wrapped request, with the payload JSON-encoded a second time
     * inside it). The alert's own source rides along only while the escaped
     * payload stays under this, leaving room for the request envelope. Past
     * it the overlay renders from its mount-time copy or fetches the source,
     * so an alert of any size fires. See OverlayTemplate::sourceVersion().
     */
    public const INLINE_SOURCE_BUDGET = 9000;

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
        ?string $alertSoundUrl = null,
        ?string $head = null,
        ?string $eventType = null,
    ) {
        $this->eventType = $eventType;
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
        $this->head = $head ?? '';
    }

    /**
     * Whether an overlay has anything to do with this alert. An alert is a
     * cocktail - HTML, a sound, TTS, a chat message, any mix - and only the
     * first three are the overlay's. A chat-only alert (the bot announces,
     * nothing shows) must not be broadcast: it would occupy the overlay's
     * alert slot for duration_ms showing an empty box.
     */
    public function hasOverlayWork(): bool
    {
        return trim($this->html) !== ''
            || $this->alertSoundUrl !== null
            || $this->ttsText !== null;
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
        $alert = [
            'alert_id' => $this->alertId,
            'data' => $this->data,
            'duration' => $this->duration,
            'timestamp' => now()->timestamp,
            'target_overlay_slugs' => $this->targetOverlaySlugs,
            // The alert template this fired. The overlay resolves its source
            // (html, css, head) and compiled utility CSS by this slug from the
            // maps it loaded at mount, so neither has to ride every broadcast.
            'alert_template_slug' => $this->alertTemplateSlug,
            // Content hash of the source as it is NOW. The overlay's mount-time
            // copy is used only when it matches; otherwise the source inlined
            // below wins, or the overlay fetches it. Edits made after OBS
            // loaded the overlay therefore show on the next fire.
            'alert_template_version' => OverlayTemplate::sourceVersion($this->head, $this->html, $this->css),
            // Milliseconds to wait after the alert fires before playing TTS
            // audio, so alert sounds/animations can play first. The overlay
            // schedules playback relative to this delay when the matching
            // TtsAudioReady broadcast arrives. 0 = play as soon as audio is
            // ready. The resolved TTS string itself is NOT broadcast - it's
            // pre-synthesized server-side by SynthesizeAlertTts.
            'tts_delay_ms' => $this->ttsDelayMs,
            // The canonical sound URL at fire time. The mount-time sound map
            // only pre-warms the HTTP cache; this is what plays.
            'alert_sound_url' => $this->alertSoundUrl,
            'event_type' => $this->eventType,
        ];

        // Inline the source while it fits: it saves the overlay a lookup, and
        // an overlay still running the JS from before alert_sources existed
        // can only render what arrives here. Without a slug there is nothing
        // to look the source up by, so it always rides.
        $withSource = $alert + ['html' => $this->html, 'css' => $this->css, 'head' => $this->head, 'source_omitted' => false];
        $fits = strlen(json_encode(json_encode(['alert' => $withSource]))) <= self::INLINE_SOURCE_BUDGET;

        // Left out: empty strings, not nulls, because the pre-alert_sources
        // renderer runs the html through processTemplate() and would throw on
        // null. `source_omitted` is what tells the current renderer to look the
        // source up instead of rendering the empty string.
        return [
            'alert' => $fits || $this->alertTemplateSlug === null
                ? $withSource
                : $alert + ['html' => '', 'css' => '', 'head' => '', 'source_omitted' => true],
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
