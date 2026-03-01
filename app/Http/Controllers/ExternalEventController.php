<?php

namespace App\Http\Controllers;

use App\Events\AlertTriggered;
use App\Models\ExternalEvent;
use App\Models\ExternalEventTemplateMapping;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

        $mapping = ExternalEventTemplateMapping::with('template')
            ->where('user_id', $user->id)
            ->where('service', $externalEvent->service)
            ->where('event_type', $externalEvent->event_type)
            ->where('enabled', true)
            ->first();

        if (! $mapping || ! $mapping->template) {
            return back()->with('message', 'No active alert mapping found for this event type.')->with('type', 'error');
        }

        $template = $mapping->template;
        $data = $externalEvent->normalized_payload ?? [];

        broadcast(new AlertTriggered(
            html: $template->html ?? '',
            css: $template->css ?? '',
            data: $data,
            duration: $mapping->duration_ms ?? 5000,
            transitionIn: $mapping->transition_in ?? 'fade',
            transitionOut: $mapping->transition_out ?? 'fade',
            broadcasterId: $user->twitch_id,
        ));

        $label = ucfirst($externalEvent->event_type) . ' (' . ucfirst($externalEvent->service) . ')';

        return back()->with('message', "Replayed {$label} alert.")->with('type', 'success');
    }
}
