<?php

namespace App\Http\Controllers;

use App\Events\AlertTriggered;
use App\Jobs\SynthesizeAlertTts;
use App\Models\BotChatOutbox;
use App\Models\ExternalEvent;
use App\Models\ExternalEventTemplateMapping;
use App\Models\User;
use App\Services\AlertMuteService;
use App\Services\Messages\AlertMessageRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExternalEventController extends Controller
{
    /**
     * Replay a stored external event as an alert.
     * Uses the saved normalized_payload (template tags) and the user's active mapping.
     */
    public function replay(Request $request, ExternalEvent $externalEvent): RedirectResponse
    {
        $result = $this->replayForUser($request->user(), $externalEvent);

        return back()->with('message', $result['message'])->with('type', $result['type']);
    }

    /**
     * Replay core, independent of how the request was authenticated (dashboard
     * session or overlay token via the events feed).
     *
     * @return array{message: string, type: string}
     */
    public function replayForUser(User $user, ExternalEvent $externalEvent): array
    {
        if ($externalEvent->user_id !== $user->id) {
            return ['message' => 'You do not own this event.', 'type' => 'error'];
        }

        if (app(AlertMuteService::class)->isMuted($user)) {
            return ['message' => 'Alerts are muted. Unmute alerts to replay events.', 'type' => 'warning'];
        }

        $data = $externalEvent->normalized_payload ?? [];

        $mapping = ExternalEventTemplateMapping::resolveForEvent(
            $user->id,
            $externalEvent->service,
            $externalEvent->event_type,
            $data['event.amount'] ?? null,
        );

        if (! $mapping || ! $mapping->template) {
            return ['message' => 'No active alert mapping found for this event type.', 'type' => 'error'];
        }

        $template = $mapping->template;
        $template->loadMissing('targetStaticOverlays');

        $targetSlugs = $template->targetStaticOverlays->isNotEmpty()
            ? $template->targetStaticOverlays->pluck('slug')->all()
            : null;

        $ttsText = app(AlertMessageRenderer::class)->render(
            $user,
            $template->tts_message,
            $data,
        );

        $alertId = (string) Str::uuid();

        // Only when the overlay has something to do (HTML, sound or TTS).
        // A chat-only alert is the bot's alone - see the outbox below.
        $alert = new AlertTriggered(
            alertId: $alertId,
            html: $template->html ?? '',
            css: $template->css ?? '',
            data: $data,
            duration: $mapping->duration_ms ?? 5000,
            broadcasterId: $user->twitch_id,
            targetOverlaySlugs: $targetSlugs,
            alertTemplateSlug: $template->slug,
            ttsText: $ttsText,
            ttsDelayMs: (int) ($template->tts_delay_ms ?? 0),
            alertSoundUrl: $template->alert_sound_url,
        );
        if ($alert->hasOverlayWork()) {
            broadcast($alert);
        }

        if ($ttsText !== null) {
            SynthesizeAlertTts::dispatch($alertId, (string) $user->twitch_id, $ttsText, $targetSlugs);
        }

        // Optional bot chat message - queued for the bot to post. Gated on
        // bot_enabled so we never enqueue a message the bot can't deliver.
        if ($user->bot_enabled) {
            $botMessage = app(AlertMessageRenderer::class)->renderMessage(
                $user,
                $template->chat_message,
                $data,
            );
            if ($botMessage !== null) {
                BotChatOutbox::create([
                    'user_id' => $user->id,
                    'message' => $botMessage,
                ]);
            }
        }

        $label = ucfirst($externalEvent->event_type).' ('.ucfirst($externalEvent->service).')';

        return ['message' => "Replayed {$label} alert.", 'type' => 'success'];
    }
}
