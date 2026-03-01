<?php

namespace App\Services\External;

use App\Events\AlertTriggered;
use App\Models\ExternalEventTemplateMapping;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ExternalAlertService
{
    /**
     * Find the alert mapping for this user+service+event_type and dispatch AlertTriggered.
     * Returns true if an alert was dispatched, false if no mapping exists or it's disabled.
     */
    public function dispatch(NormalizedExternalEvent $event, User $user): bool
    {
        $mapping = ExternalEventTemplateMapping::where('user_id', $user->id)
            ->where('service', $event->getService())
            ->where('event_type', $event->getEventType())
            ->where('enabled', true)
            ->with('template')
            ->first();

        if (! $mapping || ! $mapping->template) {
            return false;
        }

        $template = $mapping->template;

        // Build data map: event tags only (no Twitch static data for external events)
        $data = $event->getTemplateTags();

        try {
            broadcast(new AlertTriggered(
                html: $template->html ?? '',
                css: $template->css ?? '',
                data: $data,
                duration: $mapping->duration_ms ?? 5000,
                transitionIn: $mapping->transition_in ?? 'fade',
                transitionOut: $mapping->transition_out ?? 'fade',
                broadcasterId: $user->twitch_id,
            ));

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
