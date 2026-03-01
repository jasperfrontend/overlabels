<?php

namespace App\Http\Controllers;

use App\Models\ExternalEventTemplateMapping;
use App\Models\OverlayTemplate;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ExternalEventTemplateMappingController extends Controller
{
    /**
     * Update or create a single external event mapping.
     */
    public function store(Request $request, string $service): \Illuminate\Http\JsonResponse
    {
        if (! array_key_exists($service, ExternalEventTemplateMapping::SERVICE_EVENT_TYPES)) {
            abort(404, "Unknown service: {$service}");
        }

        $validEventTypes = array_keys(ExternalEventTemplateMapping::SERVICE_EVENT_TYPES[$service]);

        $validated = $request->validate([
            'event_type'     => 'required|string|in:' . implode(',', $validEventTypes),
            'overlay_template_id' => 'nullable|exists:overlay_templates,id',
            'duration_ms'    => 'nullable|integer|min:1000|max:30000',
            'transition_in'  => 'nullable|string|in:' . implode(',', array_keys(ExternalEventTemplateMapping::TRANSITION_IN_TYPES)),
            'transition_out' => 'nullable|string|in:' . implode(',', array_keys(ExternalEventTemplateMapping::TRANSITION_OUT_TYPES)),
            'enabled'        => 'nullable|boolean',
        ]);

        $user = Auth::user();

        if (! empty($validated['overlay_template_id'])) {
            $template = OverlayTemplate::where('id', $validated['overlay_template_id'])
                ->where('owner_id', $user->id)
                ->where('type', 'alert')
                ->first();

            if (! $template) {
                return response()->json(['error' => 'Invalid template selected'], 422);
            }
        }

        ExternalEventTemplateMapping::updateOrCreate(
            [
                'user_id'    => $user->id,
                'service'    => $service,
                'event_type' => $validated['event_type'],
            ],
            [
                'overlay_template_id' => $validated['overlay_template_id'] ?? null,
                'duration_ms'         => $validated['duration_ms'] ?? 5000,
                'transition_in'       => $validated['transition_in'] ?? 'fade',
                'transition_out'      => $validated['transition_out'] ?? 'fade',
                'enabled'             => $validated['enabled'] ?? false,
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Bulk update external event mappings.
     */
    public function updateMultiple(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validated = $request->validate([
                'mappings'              => 'required|array',
                'mappings.*.service'    => 'required|string',
                'mappings.*.event_type' => 'required|string',
                'mappings.*.overlay_template_id' => 'nullable|integer|exists:overlay_templates,id',
                'mappings.*.duration_ms'    => 'nullable|integer|min:1000|max:30000',
                'mappings.*.transition_in'  => 'nullable|string|in:' . implode(',', array_keys(ExternalEventTemplateMapping::TRANSITION_IN_TYPES)),
                'mappings.*.transition_out' => 'nullable|string|in:' . implode(',', array_keys(ExternalEventTemplateMapping::TRANSITION_OUT_TYPES)),
                'mappings.*.enabled'        => 'nullable|boolean',
            ]);

            $user = Auth::user();
            $updatedMappings = [];

            foreach ($validated['mappings'] as $mappingData) {
                $service = $mappingData['service'];

                if (! array_key_exists($service, ExternalEventTemplateMapping::SERVICE_EVENT_TYPES)) {
                    return response()->json([
                        'success' => false,
                        'error'   => "Unknown service: {$service}",
                    ], 422);
                }

                $validEventTypes = array_keys(ExternalEventTemplateMapping::SERVICE_EVENT_TYPES[$service]);
                if (! in_array($mappingData['event_type'], $validEventTypes, true)) {
                    return response()->json([
                        'success' => false,
                        'error'   => "Invalid event type '{$mappingData['event_type']}' for service '{$service}'",
                    ], 422);
                }

                if (! empty($mappingData['overlay_template_id'])) {
                    $template = OverlayTemplate::where('id', $mappingData['overlay_template_id'])
                        ->where('owner_id', $user->id)
                        ->where('type', 'alert')
                        ->first();

                    if (! $template) {
                        return response()->json([
                            'success' => false,
                            'error'   => "Invalid template for {$service}:{$mappingData['event_type']}",
                        ], 422);
                    }
                }

                $updatedMappings[] = ExternalEventTemplateMapping::updateOrCreate(
                    [
                        'user_id'    => $user->id,
                        'service'    => $service,
                        'event_type' => $mappingData['event_type'],
                    ],
                    [
                        'overlay_template_id' => $mappingData['overlay_template_id'] ?? null,
                        'duration_ms'         => $mappingData['duration_ms'] ?? 5000,
                        'transition_in'       => $mappingData['transition_in'] ?? 'fade',
                        'transition_out'      => $mappingData['transition_out'] ?? 'fade',
                        'enabled'             => $mappingData['enabled'] ?? false,
                    ]
                );
            }

            return response()->json([
                'success'       => true,
                'message'       => 'External event mappings updated successfully',
                'updated_count' => count($updatedMappings),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to update external event mappings', [
                'error'   => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to update external event mappings',
            ], 500);
        }
    }

    /**
     * Delete a single external event mapping.
     */
    public function destroy(string $service, string $eventType): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        ExternalEventTemplateMapping::where('user_id', $user->id)
            ->where('service', $service)
            ->where('event_type', $eventType)
            ->delete();

        return response()->json(['success' => true]);
    }
}
