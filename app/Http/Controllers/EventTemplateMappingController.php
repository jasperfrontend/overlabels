<?php

namespace App\Http\Controllers;

use App\Models\EventTemplateMapping;
use App\Models\ExternalEventTemplateMapping;
use App\Models\ExternalIntegration;
use App\Models\OverlayTemplate;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class EventTemplateMappingController extends Controller
{
    /**
     * Read-only overview of every event the user has assigned to an alert
     * template. Per-template editing now lives on the template edit page
     * (Triggers tab).
     */
    public function index()
    {
        $user = Auth::user();

        $twitchMappings = EventTemplateMapping::with('template:id,name,slug')
            ->where('user_id', $user->id)
            ->where('enabled', true)
            ->whereNotNull('template_id')
            ->get()
            ->map(fn (EventTemplateMapping $m) => [
                'event_type' => $m->event_type,
                'event_label' => EventTemplateMapping::EVENT_TYPES[$m->event_type] ?? $m->event_type,
                'duration_ms' => $m->duration_ms,
                'template' => $m->template ? [
                    'id' => $m->template->id,
                    'name' => $m->template->name,
                    'slug' => $m->template->slug,
                ] : null,
            ])
            ->values();

        $connectedServices = ExternalIntegration::where('user_id', $user->id)
            ->where('enabled', true)
            ->pluck('service')
            ->toArray();

        $externalMappings = ExternalEventTemplateMapping::with('template:id,name,slug')
            ->where('user_id', $user->id)
            ->where('enabled', true)
            ->whereNotNull('overlay_template_id')
            ->get()
            ->map(fn (ExternalEventTemplateMapping $m) => [
                'service' => $m->service,
                'event_type' => $m->event_type,
                'event_label' => ExternalEventTemplateMapping::SERVICE_EVENT_TYPES[$m->service][$m->event_type]
                    ?? $m->event_type,
                'duration_ms' => $m->duration_ms,
                'template' => $m->template ? [
                    'id' => $m->template->id,
                    'name' => $m->template->name,
                    'slug' => $m->template->slug,
                ] : null,
            ])
            ->values();

        $unassignedEventTypes = collect(EventTemplateMapping::EVENT_TYPES)
            ->reject(fn ($_, string $eventType) => $twitchMappings->contains('event_type', $eventType))
            ->map(fn (string $label, string $eventType) => [
                'event_type' => $eventType,
                'event_label' => $label,
            ])
            ->values();

        return Inertia::render('events/index', [
            'twitchMappings' => $twitchMappings,
            'externalMappings' => $externalMappings,
            'connectedServices' => $connectedServices,
            'unassignedEventTypes' => $unassignedEventTypes,
        ]);
    }

    /**
     * Get mapping for a specific event type and user
     */
    public function getMapping(string $userId, string $eventType): ?EventTemplateMapping
    {
        return EventTemplateMapping::with('template')
            ->where('user_id', $userId)
            ->where('event_type', $eventType)
            ->where('enabled', true)
            ->first();
    }

    /**
     * Get all enabled mappings for a user
     */
    public function getUserMappings(string $userId): array
    {
        return EventTemplateMapping::with('template')
            ->where('user_id', $userId)
            ->where('enabled', true)
            ->get()
            ->keyBy('event_type')
            ->toArray();
    }
}
