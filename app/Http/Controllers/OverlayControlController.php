<?php

namespace App\Http\Controllers;

use App\Events\ControlValueUpdated;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Services\ComputedControlService;
use App\Services\External\ExternalServiceRegistry;
use App\Services\StreamSessionService;
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
                function ($attribute, $value, $fail) use ($template, $request) {
                    $source = $request->input('source');
                    $query = OverlayControl::where('overlay_template_id', $template->id)->where('key', $value);
                    if ($source) {
                        $query->where('source', $source);
                    } else {
                        $query->whereNull('source');
                    }
                    if ($query->exists()) {
                        $label = $source ? "{$source}:{$value}" : $value;
                        $fail("A control with key '{$label}' already exists for this template.");
                    }
                    if (! $source && in_array($value, OverlayControl::RESERVED_KEYS, true)) {
                        $fail("The key '{$value}' is reserved and cannot be used as a control key.");
                    }
                },
            ],
            'label' => 'nullable|string|max:100',
            'type' => 'required|in:'.implode(',', OverlayControl::TYPES),
            'value' => 'nullable|string|max:1000',
            'config' => 'nullable|array',
            'sort_order' => 'nullable|integer|min:0',
            'source' => 'nullable|string|max:50',
        ]);

        // If source is provided, this is a service-managed control preset
        if (! empty($validated['source'])) {
            $source = $validated['source'];

            // Resolve presets from either Twitch stream controls or external service drivers
            if ($source === 'twitch') {
                $provisionedDefs = collect(StreamSessionService::CONTROL_PRESETS)->keyBy('key');
            } elseif (ExternalServiceRegistry::has($source)) {
                $driver = ExternalServiceRegistry::driver($source);
                $provisionedDefs = collect($driver->getAutoProvisionedControls())->keyBy('key');
            } else {
                abort(422, "Unknown service: {$source}");
            }

            $def = $provisionedDefs->get($validated['key']);

            abort_unless($def !== null, 422, "Invalid key '{$validated['key']}' for service '{$source}'");

            $control = OverlayControl::create([
                'overlay_template_id' => $template->id,
                'user_id'             => auth()->id(),
                'key'                 => $def['key'],
                'label'               => $validated['label'] ?? $def['label'] ?? null,
                'type'                => $def['type'],
                'value'               => $def['value'] ?? null,
                'config'              => $def['config'] ?? null,
                'sort_order'          => $validated['sort_order'] ?? 0,
                'source'              => $source,
                'source_managed'      => true,
            ]);

            return response()->json(['control' => $control], 201);
        }

        // Computed control: validate formula and set initial value
        if ($validated['type'] === 'computed') {
            $request->validate([
                'config.formula.watch_key' => 'required|string|max:50',
                'config.formula.watch_source' => 'nullable|string|max:50',
                'config.formula.operator' => 'required|in:==,!=,>,<,>=,<=',
                'config.formula.compare_value' => 'required|string|max:200',
                'config.formula.then_value' => 'required|string|max:1000',
                'config.formula.else_value' => 'required|string|max:1000',
            ]);

            $formula = $request->input('config.formula');
            $computedService = app(ComputedControlService::class);

            // Validate that the watched control exists in scope
            $available = $computedService->getAvailableControls(auth()->user(), $template->id);
            $watchSource = $formula['watch_source'] ?: null;
            $exists = $available->first(function ($c) use ($formula, $watchSource) {
                return $c->key === $formula['watch_key'] && ($c->source ?: null) === $watchSource;
            });

            if (! $exists) {
                abort(422, "Watched control '{$formula['watch_key']}' not found in scope.");
            }

            // Create the control first (needed for cycle detection with an ID)
            $control = OverlayControl::createForTemplate($template, auth()->user(), [
                'key' => $validated['key'],
                'label' => $validated['label'] ?? null,
                'type' => 'computed',
                'value' => null,
                'config' => ['formula' => $formula],
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            // Cycle detection
            if ($computedService->detectCycle($control, $formula, $template->id)) {
                $control->delete();
                abort(422, 'This formula would create a circular dependency.');
            }

            // Evaluate initial value
            $initialValue = $computedService->evaluate($control, $exists->value);
            $control->update(['value' => $initialValue]);

            // Broadcast initial value
            ControlValueUpdated::dispatch(
                $template->slug,
                $control->broadcastKey(),
                $control->type,
                $initialValue,
                auth()->user()->twitch_id,
            );

            return response()->json(['control' => $control->fresh()], 201);
        }

        // Expression control: validate expression string, extract dependencies, check cycles
        if ($validated['type'] === 'expression') {
            $request->validate([
                'config.expression' => 'required|string|max:500',
            ]);

            $expression = $request->input('config.expression');
            $dependencies = OverlayControl::extractExpressionDependencies($expression);

            if (empty($dependencies)) {
                abort(422, 'Expression must reference at least one control (e.g. c.my_control).');
            }

            // Validate all referenced controls exist in scope
            $computedService = app(ComputedControlService::class);
            $available = $computedService->getAvailableControls(auth()->user(), $template->id);

            foreach ($dependencies as $dep) {
                $colonIdx = strpos($dep, ':');
                if ($colonIdx !== false) {
                    $depSource = substr($dep, 0, $colonIdx);
                    $depKey = substr($dep, $colonIdx + 1);
                } else {
                    $depSource = null;
                    $depKey = $dep;
                }

                $found = $available->first(function ($c) use ($depKey, $depSource) {
                    return $c->key === $depKey && ($c->source ?: null) === $depSource;
                });

                if (! $found) {
                    $label = $depSource ? "{$depSource}:{$depKey}" : $depKey;
                    abort(422, "Referenced control '{$label}' not found in scope.");
                }
            }

            // Create the control first (needed for cycle detection)
            $control = OverlayControl::createForTemplate($template, auth()->user(), [
                'key' => $validated['key'],
                'label' => $validated['label'] ?? null,
                'type' => 'expression',
                'value' => null,
                'config' => [
                    'expression' => $expression,
                    'dependencies' => $dependencies,
                ],
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            // Cycle detection for expressions
            if ($computedService->detectExpressionCycle($control, $dependencies, $template->id)) {
                $control->delete();
                abort(422, 'This expression would create a circular dependency.');
            }

            return response()->json(['control' => $control], 201);
        }

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

        if ($control->source_managed) {
            $source = ucfirst($control->source ?? 'an external service');
            abort(403, "This control is managed by {$source} and cannot be edited manually.");
        }

        $validated = $request->validate([
            'label' => 'nullable|string|max:100',
            'value' => ['nullable', function ($attribute, $value, $fail) {
                if (strlen((string) $value) > 1000) {
                    $fail("The $attribute must not exceed 1000 characters.");
                }
            }],
            'config' => 'nullable|array',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Handle computed control formula updates
        if ($control->isComputed() && isset($validated['config']['formula'])) {
            $request->validate([
                'config.formula.watch_key' => 'required|string|max:50',
                'config.formula.watch_source' => 'nullable|string|max:50',
                'config.formula.operator' => 'required|in:==,!=,>,<,>=,<=',
                'config.formula.compare_value' => 'required|string|max:200',
                'config.formula.then_value' => 'required|string|max:1000',
                'config.formula.else_value' => 'required|string|max:1000',
            ]);

            $formula = $request->input('config.formula');
            $computedService = app(ComputedControlService::class);

            // Validate watched control exists
            $available = $computedService->getAvailableControls(auth()->user(), $template->id, $control->id);
            $watchSource = $formula['watch_source'] ?: null;
            $exists = $available->first(function ($c) use ($formula, $watchSource) {
                return $c->key === $formula['watch_key'] && ($c->source ?: null) === $watchSource;
            });

            if (! $exists) {
                abort(422, "Watched control '{$formula['watch_key']}' not found in scope.");
            }

            // Cycle detection
            if ($computedService->detectCycle($control, $formula, $template->id)) {
                abort(422, 'This formula would create a circular dependency.');
            }

            $control->update([
                'label' => $validated['label'] ?? $control->label,
                'config' => ['formula' => $formula],
                'sort_order' => $validated['sort_order'] ?? $control->sort_order,
            ]);

            // Re-evaluate
            $newValue = $computedService->evaluate($control, $exists->value);
            if ($newValue !== ($control->value ?? '')) {
                $control->update(['value' => $newValue]);

                ControlValueUpdated::dispatch(
                    $template->slug,
                    $control->broadcastKey(),
                    $control->type,
                    $newValue,
                    auth()->user()->twitch_id,
                );
            }

            return response()->json(['control' => $control->fresh()]);
        }

        // Handle expression control updates
        if ($control->isExpression() && isset($validated['config']['expression'])) {
            $request->validate([
                'config.expression' => 'required|string|max:500',
            ]);

            $expression = $request->input('config.expression');
            $dependencies = OverlayControl::extractExpressionDependencies($expression);

            if (empty($dependencies)) {
                abort(422, 'Expression must reference at least one control (e.g. c.my_control).');
            }

            $computedService = app(ComputedControlService::class);
            $available = $computedService->getAvailableControls(auth()->user(), $template->id, $control->id);

            foreach ($dependencies as $dep) {
                $colonIdx = strpos($dep, ':');
                if ($colonIdx !== false) {
                    $depSource = substr($dep, 0, $colonIdx);
                    $depKey = substr($dep, $colonIdx + 1);
                } else {
                    $depSource = null;
                    $depKey = $dep;
                }

                $found = $available->first(function ($c) use ($depKey, $depSource) {
                    return $c->key === $depKey && ($c->source ?: null) === $depSource;
                });

                if (! $found) {
                    $label = $depSource ? "{$depSource}:{$depKey}" : $depKey;
                    abort(422, "Referenced control '{$label}' not found in scope.");
                }
            }

            if ($computedService->detectExpressionCycle($control, $dependencies, $template->id)) {
                abort(422, 'This expression would create a circular dependency.');
            }

            $control->update([
                'label' => $validated['label'] ?? $control->label,
                'config' => [
                    'expression' => $expression,
                    'dependencies' => $dependencies,
                ],
                'sort_order' => $validated['sort_order'] ?? $control->sort_order,
            ]);

            return response()->json(['control' => $control->fresh()]);
        }

        if (array_key_exists('value', $validated) && ! in_array($control->type, ['timer', 'datetime', 'computed', 'expression'])) {
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

        if ($control->source_managed) {
            $source = ucfirst($control->source ?? 'an external service');
            abort(403, "This control is managed by {$source} and cannot be edited manually.");
        }

        if ($control->isComputed()) {
            abort(403, 'Computed controls cannot be edited manually. Their value is derived automatically.');
        }

        if ($control->isExpression()) {
            abort(403, 'Expression controls cannot be edited manually. Their value is derived from the expression.');
        }

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

        $cascaded = app(ComputedControlService::class)->cascade(auth()->user(), $control, $template->slug);

        return response()->json(['control' => $control->fresh(), 'value' => $sanitized, 'cascaded' => $cascaded]);
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
            'target_datetime' => $config['target_datetime'] ?? null,
        ];

        $this->broadcastUpdate($template, $control, $displayValue, $timerState);

        $cascaded = app(ComputedControlService::class)->cascade(auth()->user(), $control, $template->slug);

        return response()->json(['control' => $control, 'value' => $displayValue, 'timer_state' => $timerState, 'cascaded' => $cascaded]);
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

            // Skip if key already exists (scoped by source for service controls)
            $existsQuery = OverlayControl::where('overlay_template_id', $template->id)->where('key', $item['key']);
            if (! empty($item['source'])) {
                $existsQuery->where('source', $item['source']);
            } else {
                $existsQuery->whereNull('source');
            }
            if ($existsQuery->exists()) {
                continue;
            }

            // For computed controls, validate that the dependency exists on the target
            if ($item['type'] === 'computed') {
                $formula = $item['config']['formula'] ?? null;
                if (! $formula || empty($formula['watch_key'])) {
                    continue; // Skip computed controls without a valid formula
                }

                $computedService = app(ComputedControlService::class);
                $available = $computedService->getAvailableControls($user, $template->id);
                $watchSource = ($formula['watch_source'] ?? null) ?: null;
                $depExists = $available->first(function ($c) use ($formula, $watchSource) {
                    return $c->key === $formula['watch_key'] && ($c->source ?: null) === $watchSource;
                });

                if (! $depExists) {
                    continue; // Skip - dependency not available on target template
                }
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

        // Evaluate any imported computed controls to set initial values
        $computedService = app(ComputedControlService::class);
        foreach ($created as $control) {
            if ($control->type === 'computed') {
                $formula = $control->config['formula'] ?? null;
                if (! $formula) {
                    continue;
                }
                $watchSource = ($formula['watch_source'] ?? null) ?: null;
                $available = $computedService->getAvailableControls($user, $template->id, $control->id);
                $dep = $available->first(function ($c) use ($formula, $watchSource) {
                    return $c->key === $formula['watch_key'] && ($c->source ?: null) === $watchSource;
                });
                $initialValue = $computedService->evaluate($control, $dep?->value);
                $control->update(['value' => $initialValue]);
            }
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
            $control->broadcastKey(),
            $control->type,
            $value,
            $user->twitch_id,
            $timerState
        );
    }
}
