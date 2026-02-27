<?php

namespace App\Http\Controllers;

use App\Models\EventTemplateMapping;
use App\Models\OverlayTemplate;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EventTemplateMappingController extends Controller
{
    /**
     * Display the event mapping configuration page
     */
    public function index()
    {
        $user = Auth::user();
        $mappings = EventTemplateMapping::with('template')
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('event_type');

        $alertTemplates = OverlayTemplate::where('owner_id', $user->id)
            ->where('type', 'alert')
            ->get();

        // Create mappings for all event types if they don't exist
        $eventTypes = EventTemplateMapping::EVENT_TYPES;
        $allMappings = [];

        foreach ($eventTypes as $eventType => $displayName) {
            $mapping = $mappings->get($eventType);
            if (! $mapping) {
                // Create a proper array structure for non-existing mappings
                $mapping = [
                    'id' => null,
                    'user_id' => $user->id,
                    'event_type' => $eventType,
                    'template_id' => null,
                    'duration_ms' => 5000,
                    'transition_in' => 'fade',
                    'transition_out' => 'fade',
                    'enabled' => false,
                    'template' => null,
                ];
            } else {
                // Convert the existing model to an array for consistency
                $mapping = $mapping->toArray();
            }
            $allMappings[] = $mapping;
        }

        return Inertia::render('events/index', [
            'mappings' => $allMappings,
            'alertTemplates' => $alertTemplates,
            'eventTypes' => $eventTypes,
            'transitionTypes' => EventTemplateMapping::TRANSITION_TYPES,
        ]);
    }

    /**
     * Update or create an event mapping
     */
    public function store(Request $request)
    {
        $request->validate([
            'event_type' => 'required|string|in:'.implode(',', array_keys(EventTemplateMapping::EVENT_TYPES)),
            'template_id' => 'nullable|exists:overlay_templates,id',
            'duration_ms' => 'nullable|integer|min:1000|max:30000',
            'transition_in' => 'nullable|string|in:'.implode(',', array_keys(EventTemplateMapping::TRANSITION_TYPES)),
            'transition_out' => 'nullable|string|in:'.implode(',', array_keys(EventTemplateMapping::TRANSITION_TYPES)),
            'enabled' => 'nullable|boolean',
        ]);

        $user = Auth::user();

        // If template_id is provided, verify it belongs to the user and is an alert template
        if ($request->template_id) {
            $template = OverlayTemplate::where('id', $request->template_id)
                ->where('owner_id', $user->id)
                ->where('type', 'alert')
                ->first();

            if (! $template) {
                return response()->json(['error' => 'Invalid template selected'], 422);
            }
        }

        EventTemplateMapping::updateOrCreate(
            [
                'user_id' => $user->id,
                'event_type' => $request->event_type,
            ],
            [
                'template_id' => $request->template_id,
                'duration_ms' => $request->duration_ms ?? 5000,
                'transition_in' => $request->transition_in ?? 'fade',
                'transition_out' => $request->transition_out ?? 'fade',
                'enabled' => $request->enabled ?? false,
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Update multiple mappings at once
     */
    public function updateMultiple(Request $request)
    {
        try {
            $validated = $request->validate([
                'mappings' => 'required|array',
                'mappings.*.event_type' => 'required|string|in:'.implode(',', array_keys(EventTemplateMapping::EVENT_TYPES)),
                'mappings.*.template_id' => 'nullable|integer|exists:overlay_templates,id',
                'mappings.*.duration_ms' => 'nullable|integer|min:1000|max:30000',
                'mappings.*.transition_in' => 'nullable|string|in:'.implode(',', array_keys(EventTemplateMapping::TRANSITION_TYPES)),
                'mappings.*.transition_out' => 'nullable|string|in:'.implode(',', array_keys(EventTemplateMapping::TRANSITION_TYPES)),
                'mappings.*.enabled' => 'nullable|boolean',
            ]);

            $user = Auth::user();
            $updatedMappings = [];

            foreach ($validated['mappings'] as $mappingData) {
                // Verify template ownership if template_id is provided
                if (! empty($mappingData['template_id'])) {
                    $template = OverlayTemplate::where('id', $mappingData['template_id'])
                        ->where('owner_id', $user->id)
                        ->where('type', 'alert')
                        ->first();

                    if (! $template) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Invalid template selected for event: '.$mappingData['event_type'],
                        ], 422);
                    }
                }

                $mapping = EventTemplateMapping::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'event_type' => $mappingData['event_type'],
                    ],
                    [
                        'template_id' => $mappingData['template_id'] ?? null,
                        'duration_ms' => $mappingData['duration_ms'] ?? 5000,
                        'transition_in' => $mappingData['transition_in'] ?? 'fade',
                        'transition_out' => $mappingData['transition_out'] ?? 'fade',
                        'enabled' => $mappingData['enabled'] ?? false,
                    ]
                );

                $updatedMappings[] = $mapping;
            }

            return response()->json([
                'success' => true,
                'message' => 'Event mappings updated successfully',
                'updated_count' => count($updatedMappings),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to update event mappings', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to update event mappings',
            ], 500);
        }
    }

    /**
     * Delete an event mapping
     */
    public function destroy(Request $request, string $eventType)
    {
        $user = Auth::user();

        EventTemplateMapping::where('user_id', $user->id)
            ->where('event_type', $eventType)
            ->delete();

        return response()->json(['success' => true]);
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
