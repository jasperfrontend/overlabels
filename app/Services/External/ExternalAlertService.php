<?php

namespace App\Services\External;

use App\Events\AlertTriggered;
use App\Jobs\SynthesizeAlertTts;
use App\Models\BotChatOutbox;
use App\Models\ExternalEventTemplateMapping;
use App\Models\User;
use App\Services\AlertMuteService;
use App\Services\Expressions\AlertExpressionRenderer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExternalAlertService
{
    /**
     * Find the alert mapping for this user+service+event_type and dispatch AlertTriggered.
     * Returns true if an alert was dispatched, false if no mapping exists or it's disabled.
     */
    public function dispatch(NormalizedExternalEvent $event, User $user): bool
    {
        // Global mute: muted is muted - no broadcast, no TTS synthesis, no
        // bot message. Control updates still flow; only alert output stops.
        if (app(AlertMuteService::class)->isMuted($user)) {
            return false;
        }

        // Pick the alert template, honoring variant conditions on the donated
        // amount (e.g. a louder alert for a bigger Ko-fi donation).
        $mapping = ExternalEventTemplateMapping::resolveForEvent(
            $user->id,
            $event->getService(),
            $event->getEventType(),
            $event->getAmount(),
        );

        if (! $mapping || ! $mapping->template) {
            return false;
        }

        $template = $mapping->template;

        // Build data map: event tags only (no Twitch static data for external events)
        $data = $event->getTemplateTags();

        // Resolve target static overlay slugs (null = fire on all)
        $template->loadMissing('targetStaticOverlays');
        $targetSlugs = $template->targetStaticOverlays->isNotEmpty()
            ? $template->targetStaticOverlays->pluck('slug')->all()
            : null;

        try {
            $ttsText = app(AlertExpressionRenderer::class)->render(
                $user,
                $template->tts_expression,
                $data,
            );

            $alertId = (string) Str::uuid();

            broadcast(new AlertTriggered(
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
            ));

            if ($ttsText !== null) {
                SynthesizeAlertTts::dispatch($alertId, (string) $user->twitch_id, $ttsText, $targetSlugs);
            }

            // Optional bot chat message - queued for the bot to post. Gated on
            // bot_enabled so we never enqueue a message the bot can't deliver.
            if ($user->bot_enabled) {
                $botMessage = app(AlertExpressionRenderer::class)->renderMessage(
                    $user,
                    $template->bot_message_expression,
                    $data,
                );
                if ($botMessage !== null) {
                    BotChatOutbox::create([
                        'user_id' => $user->id,
                        'message' => $botMessage,
                    ]);
                }
            }

            Log::info("External alert dispatched for user {$user->id}", [
                'service' => $event->getService(),
                'event_type' => $event->getEventType(),
                'template_id' => $template->id,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to dispatch external alert: {$e->getMessage()}", [
                'user_id' => $user->id,
                'service' => $event->getService(),
                'event_type' => $event->getEventType(),
            ]);

            return false;
        }
    }
}
