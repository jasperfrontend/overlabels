<?php

namespace App\Http\Controllers;

use App\Events\AlertTriggered;
use App\Jobs\SynthesizeAlertTts;
use App\Models\BotChatOutbox;
use App\Models\ExternalEvent;
use App\Models\ExternalEventTemplateMapping;
use App\Services\AlertMuteService;
use App\Services\Expressions\AlertExpressionRenderer;
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
        $user = $request->user();

        if ($externalEvent->user_id !== $user->id) {
            return back()->with('message', 'You do not own this event.')->with('type', 'error');
        }

        if (app(AlertMuteService::class)->isMuted($user)) {
            return back()->with('message', 'Alerts are muted. Unmute alerts to replay events.')->with('type', 'warning');
        }

        $data = $externalEvent->normalized_payload ?? [];

        $mapping = ExternalEventTemplateMapping::resolveForEvent(
            $user->id,
            $externalEvent->service,
            $externalEvent->event_type,
            $data['event.amount'] ?? null,
        );

        if (! $mapping || ! $mapping->template) {
            return back()->with('message', 'No active alert mapping found for this event type.')->with('type', 'error');
        }

        $template = $mapping->template;
        $template->loadMissing('targetStaticOverlays');

        $targetSlugs = $template->targetStaticOverlays->isNotEmpty()
            ? $template->targetStaticOverlays->pluck('slug')->all()
            : null;

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

        $label = ucfirst($externalEvent->event_type).' ('.ucfirst($externalEvent->service).')';

        return back()->with('message', "Replayed {$label} alert.")->with('type', 'success');
    }
}
