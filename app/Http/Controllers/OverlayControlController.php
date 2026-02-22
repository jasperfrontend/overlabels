<?php

namespace App\Http\Controllers;

use App\Events\ControlValueUpdated;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OverlayControlController extends Controller
{
    /**
     * List controls for a template.
     */
    public function index(Request $request, OverlayTemplate $template): JsonResponse
    {
        abort_if($template->owner_id !== auth()->id(), 403);

        $controls = $template->controls()->orderBy('sort_order')->get();

        return response()->json(['controls' => $controls]);
    }

    /**
     * Create a new control for a template.
     */
    public function store(Request $request, OverlayTemplate $template): JsonResponse
    {
        abort_if($template->owner_id !== auth()->id(), 403);
        abort_if($template->controls()->count() >= 20, 422, 'Templates are limited to 20 controls.');

        $validated = $request->validate([
            'key' => [
                'required',
                'string',
                'max:50',
                'regex:'.OverlayControl::KEY_PATTERN,
                function ($attribute, $value, $fail) use ($template) {
                    if (OverlayControl::where('overlay_template_id', $template->id)->where('key', $value)->exists()) {
                        $fail("A control with key '{$value}' already exists for this template.");
                    }
                },
            ],
            'label' => 'nullable|string|max:100',
            'type' => 'required|in:'.implode(',', OverlayControl::TYPES),
            'value' => 'nullable|string|max:1000',
            'config' => 'nullable|array',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $control = OverlayControl::createForTemplate($template, auth()->user(), $validated);

        return response()->json(['control' => $control], 201);
    }

    /**
     * Update a control's label, config, or sort_order. Key is immutable.
     */
    public function update(Request $request, OverlayTemplate $template, OverlayControl $control): JsonResponse
    {
        abort_if($template->owner_id !== auth()->id(), 403);
        abort_if($control->overlay_template_id !== $template->id, 404);

        $validated = $request->validate([
            'label' => 'nullable|string|max:100',
            'value' => 'nullable|string|max:1000',
            'config' => 'nullable|array',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if (array_key_exists('value', $validated) && ! in_array($control->type, ['timer', 'datetime'])) {
            $validated['value'] = OverlayControl::sanitizeValue($control->type, $validated['value'] ?? '');
        } else {
            unset($validated['value']);
        }

        $control->update($validated);

        return response()->json(['control' => $control->fresh()]);
    }

    /**
     * Delete a control.
     */
    public function destroy(OverlayTemplate $template, OverlayControl $control): JsonResponse
    {
        abort_if($template->owner_id !== auth()->id(), 403);
        abort_if($control->overlay_template_id !== $template->id, 404);

        $control->delete();

        return response()->json(['message' => 'Control deleted.']);
    }

    /**
     * Mutate the value of a control at stream time.
     */
    public function setValue(Request $request, OverlayTemplate $template, OverlayControl $control): JsonResponse
    {
        abort_if($template->owner_id !== auth()->id(), 403);
        abort_if($control->overlay_template_id !== $template->id, 404);

        if ($control->type === 'timer') {
            return $this->setTimerValue($request, $template, $control);
        }

        $validated = $request->validate([
            'value' => 'nullable|max:1000',
            'action' => 'nullable|in:increment,decrement,reset',
        ]);

        $config = $control->config ?? [];

        if (isset($validated['action'])) {
            $currentValue = (float) ($control->value ?? 0);
            $step = (float) ($config['step'] ?? 1);
            $resetValue = (float) ($config['reset_value'] ?? 0);
            $min = isset($config['min']) ? (float) $config['min'] : null;
            $max = isset($config['max']) ? (float) $config['max'] : null;

            $newValue = match ($validated['action']) {
                'increment' => $currentValue + $step,
                'decrement' => $currentValue - $step,
                'reset' => $resetValue,
            };

            if ($min !== null) {
                $newValue = max($min, $newValue);
            }
            if ($max !== null) {
                $newValue = min($max, $newValue);
            }

            $sanitized = OverlayControl::sanitizeValue($control->type, $newValue);
        } else {
            $raw = $validated['value'] ?? '';
            $sanitized = OverlayControl::sanitizeValue($control->type, $raw);

            // Validate against min/max for number/counter types
            if (in_array($control->type, ['number', 'counter']) && is_numeric($sanitized)) {
                $num = (float) $sanitized;
                $min = isset($config['min']) ? (float) $config['min'] : null;
                $max = isset($config['max']) ? (float) $config['max'] : null;
                if ($min !== null) {
                    $num = max($min, $num);
                }
                if ($max !== null) {
                    $num = min($max, $num);
                }
                $sanitized = (string) $num;
            }
        }

        $control->update(['value' => $sanitized]);

        $this->broadcastUpdate($template, $control, $sanitized);

        return response()->json(['control' => $control->fresh(), 'value' => $sanitized]);
    }

    /**
     * Handle timer-specific mutations.
     */
    private function setTimerValue(Request $request, OverlayTemplate $template, OverlayControl $control): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:start,stop,reset,set_offset',
            'offset' => 'required_if:action,set_offset|integer|min:0',
        ]);

        $config = $control->config ?? [];
        $action = $validated['action'];

        switch ($action) {
            case 'start':
                $config['running'] = true;
                $config['started_at'] = now()->toIso8601String();
                break;

            case 'stop':
                if (! empty($config['running']) && ! empty($config['started_at'])) {
                    $elapsed = (int) Carbon::parse($config['started_at'])->diffInSeconds(now());
                    $config['offset_seconds'] = ((int) ($config['offset_seconds'] ?? 0)) + $elapsed;
                }
                $config['running'] = false;
                $config['started_at'] = null;
                break;

            case 'reset':
                $config['running'] = false;
                $config['offset_seconds'] = 0;
                $config['started_at'] = null;
                break;

            case 'set_offset':
                $offset = (int) $validated['offset'];
                $baseSeconds = (int) ($config['base_seconds'] ?? 0);
                $mode = $config['mode'] ?? 'countup';
                if ($mode === 'countdown' && $baseSeconds > 0) {
                    $offset = min($offset, $baseSeconds);
                }
                $config['offset_seconds'] = $offset;
                break;
        }

        $control->update(['config' => $config]);
        $control->refresh();

        $displayValue = $control->resolveDisplayValue();

        $timerState = [
            'mode' => $config['mode'] ?? 'countup',
            'base_seconds' => (int) ($config['base_seconds'] ?? 0),
            'offset_seconds' => (int) ($config['offset_seconds'] ?? 0),
            'running' => (bool) ($config['running'] ?? false),
            'started_at' => $config['started_at'] ?? null,
        ];

        $this->broadcastUpdate($template, $control, $displayValue, $timerState);

        return response()->json(['control' => $control, 'value' => $displayValue, 'timer_state' => $timerState]);
    }

    /**
     * Import controls from a fork source into a new template.
     */
    public function importForkedControls(Request $request, OverlayTemplate $template): JsonResponse
    {
        abort_if($template->owner_id !== auth()->id(), 403);

        $validated = $request->validate([
            'controls' => 'required|array',
            'controls.*.action' => 'required|in:create,skip',
            'controls.*.key' => 'required|string|max:50|regex:'.OverlayControl::KEY_PATTERN,
            'controls.*.label' => 'nullable|string|max:100',
            'controls.*.type' => 'required|in:'.implode(',', OverlayControl::TYPES),
            'controls.*.config' => 'nullable|array',
            'controls.*.sort_order' => 'nullable|integer|min:0',
        ]);

        $created = [];
        $user = auth()->user();

        foreach ($validated['controls'] as $item) {
            if ($item['action'] !== 'create') {
                continue;
            }

            // Skip if key already exists
            if (OverlayControl::where('overlay_template_id', $template->id)->where('key', $item['key'])->exists()) {
                continue;
            }

            $control = OverlayControl::createForTemplate($template, $user, [
                'key' => $item['key'],
                'label' => $item['label'] ?? null,
                'type' => $item['type'],
                'value' => null,
                'config' => $item['config'] ?? null,
                'sort_order' => $item['sort_order'] ?? 0,
            ]);

            $created[] = $control;
        }

        return response()->json(['created' => $created, 'count' => count($created)]);
    }

    /**
     * Dispatch the ControlValueUpdated broadcast event.
     */
    private function broadcastUpdate(OverlayTemplate $template, OverlayControl $control, string $value, ?array $timerState = null): void
    {
        $user = auth()->user();

        ControlValueUpdated::dispatch(
            $template->slug,
            $control->key,
            $control->type,
            $value,
            $user->twitch_id,
            $timerState
        );
    }
}
