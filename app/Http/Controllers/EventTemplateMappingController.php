<?php

namespace App\Http\Controllers;

use App\Models\EventTemplateMapping;
use App\Models\ExternalEventTemplateMapping;
use App\Models\ExternalIntegration;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
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

        $twitchRows = EventTemplateMapping::with('template:id,name,slug')
            ->where('user_id', $user->id)
            ->where('enabled', true)
            ->whereNotNull('template_id')
            ->orderBy('event_type')
            ->orderBy('template_id')
            ->get();

        $twitchShadow = $this->shadowedBy($twitchRows, fn (EventTemplateMapping $m) => $m->event_type);

        $twitchMappings = $twitchRows
            ->map(fn (EventTemplateMapping $m) => [
                'event_type' => $m->event_type,
                'event_label' => EventTemplateMapping::EVENT_TYPES[$m->event_type] ?? $m->event_type,
                'condition_type' => $m->condition_type,
                'condition_value' => $m->condition_value,
                'condition_unit' => EventTemplateMapping::AMOUNT_FIELDS[$m->event_type]['unit'] ?? null,
                'duration_ms' => $m->duration_ms,
                'template' => $m->template ? [
                    'id' => $m->template->id,
                    'name' => $m->template->name,
                    'slug' => $m->template->slug,
                ] : null,
                'shadowed_by' => $twitchShadow[$m->id] ?? null,
            ])
            ->values();

        $connectedServices = ExternalIntegration::where('user_id', $user->id)
            ->where('enabled', true)
            ->pluck('service')
            ->toArray();

        $externalRows = ExternalEventTemplateMapping::with('template:id,name,slug')
            ->where('user_id', $user->id)
            ->where('enabled', true)
            ->whereNotNull('overlay_template_id')
            ->orderBy('service')
            ->orderBy('event_type')
            ->orderBy('overlay_template_id')
            ->get();

        $externalShadow = $this->shadowedBy($externalRows, fn (ExternalEventTemplateMapping $m) => $m->service.':'.$m->event_type);

        $externalMappings = $externalRows
            ->map(fn (ExternalEventTemplateMapping $m) => [
                'service' => $m->service,
                'event_type' => $m->event_type,
                'event_label' => ExternalEventTemplateMapping::SERVICE_EVENT_TYPES[$m->service][$m->event_type]
                    ?? $m->event_type,
                'condition_type' => $m->condition_type,
                'condition_value' => $m->condition_value,
                'condition_unit' => ExternalEventTemplateMapping::supportsCondition($m->service, $m->event_type) ? 'amount' : null,
                'duration_ms' => $m->duration_ms,
                'template' => $m->template ? [
                    'id' => $m->template->id,
                    'name' => $m->template->name,
                    'slug' => $m->template->slug,
                ] : null,
                'shadowed_by' => $externalShadow[$m->id] ?? null,
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
     * Map [mapping_id => winning template name] for every "shadowed" mapping:
     * one that shares an identical trigger condition with an earlier-assigned
     * mapping (same event scope + condition_type + condition_value). The
     * resolver breaks such ties on the lowest template id, so the winner always
     * fires and every other member never does. Only EXACT-condition duplicates
     * are flagged - an `at_least 100` is not shadowed by an `exactly 100`,
     * because it still fires at other amounts.
     *
     * The collection MUST already be ordered by the tie-break id ascending, so
     * the group's first element is the winner.
     *
     * @param  Collection<int, Model>  $mappings
     * @param  callable(Model): string  $scopeKey
     * @return array<int, string>
     */
    private function shadowedBy(Collection $mappings, callable $scopeKey): array
    {
        $shadowed = [];

        $groups = $mappings->groupBy(
            fn ($m) => $scopeKey($m).'|'.($m->condition_type ?? '').'|'.($m->condition_value ?? '')
        );

        foreach ($groups as $group) {
            if ($group->count() < 2) {
                continue;
            }

            $winner = $group->first();
            foreach ($group as $m) {
                if ($m->getKey() !== $winner->getKey()) {
                    $shadowed[$m->getKey()] = $winner->template?->name ?? 'another alert';
                }
            }
        }

        return $shadowed;
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
