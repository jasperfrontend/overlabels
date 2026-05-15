<?php

namespace App\Http\Controllers;

use App\Events\ControlValueUpdated;
use App\Models\OptionSet;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Services\External\ExternalServiceRegistry;
use App\Services\StreamSessionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Random\RandomException;

class OverlayControlController extends Controller
{
    /**
     * List controls for a template.
     */
    public function index(OverlayTemplate $template): JsonResponse
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
        abort_if($template->controls()->count() >= 50, 422, 'Templates are limited to 50 controls.');

        $validated = $request->validate([
            'key' => [
                'required',
                'string',
                'max:50',
                'regex:'.OverlayControl::KEY_PATTERN,
                function ($attribute, $value, $fail) use ($template, $request) {
                    $source = $request->input('source');
                    $query = OverlayControl::query()->where('overlay_template_id', $template->id)->where('key', $value);
                    if ($source) {
                        $query->where('source', $source);
                    } else {
                        $query->whereNull('source');
                    }
                    if ($query->exists()) {
                        $label = $source ? "$source:$value" : $value;
                        $fail("A control with key '$label' already exists for this template.");
                    }
                    if (! $source && in_array($value, OverlayControl::RESERVED_KEYS, true)) {
                        $fail("The key '$value' is reserved and cannot be used as a control key.");
                    }
                },
            ],
            'label' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
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
                abort(422, "Unknown service: $source");
            }

            $def = $provisionedDefs->get($validated['key']);

            abort_unless($def !== null, 422, "Invalid key '{$validated['key']}' for service '$source'");

            $control = OverlayControl::create([
                'overlay_template_id' => $template->id,
                'user_id' => auth()->id(),
                'key' => $def['key'],
                'label' => $validated['label'] ?? $def['label'] ?? null,
                'description' => $validated['description'] ?? null,
                'type' => $def['type'],
                'value' => $def['value'] ?? null,
                'config' => $def['config'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
                'source' => $source,
                'source_managed' => true,
            ]);

            return response()->json(['control' => $control], 201);
        }

        // Expression control: validate expression string, extract dependencies, check cycles
        if ($validated['type'] === 'expression') {
            $request->validate([
                'config.expression' => 'required|string|max:500',
            ]);

            $expression = $request->input('config.expression');
            $dependencies = OverlayControl::extractExpressionDependencies($expression);

            // Validate all referenced controls exist in scope
            $available = OverlayControl::getAvailableControls(auth()->user(), $template->id);

            $this->dependencies($dependencies, $available);

            // Create the control first (needed for cycle detection)
            $control = OverlayControl::createForTemplate($template, auth()->user(), [
                'key' => $validated['key'],
                'label' => $validated['label'] ?? null,
                'description' => $validated['description'] ?? null,
                'type' => 'expression',
                'value' => null,
                'config' => [
                    'expression' => $expression,
                    'dependencies' => $dependencies,
                ],
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            // Cycle detection for expressions
            if (OverlayControl::detectExpressionCycle($control, $dependencies, $template->id)) {
                $control->delete();
                abort(422, 'This expression would create a circular dependency.');
            }

            ControlValueUpdated::dispatch(
                $template->slug,
                $control->broadcastKey(),
                $control->type,
                $control->value ?? '',
                auth()->user()->twitch_id,
                null,
                $expression,
            );

            return response()->json(['control' => $control], 201);
        }

        // List writer: side-effect control that appends the source control's
        // value to a target list every time the source updates. Has no
        // renderable value of its own; the row exists so the binding fits
        // into the same management surface as every other control.
        if ($validated['type'] === 'list_writer') {
            $listWriterConfig = $this->validateListWriterConfig($request, auth()->id(), $template->id);

            $control = OverlayControl::createForTemplate($template, auth()->user(), [
                'key' => $validated['key'],
                'label' => $validated['label'] ?? null,
                'description' => $validated['description'] ?? null,
                'type' => 'list_writer',
                'value' => null,
                'config' => $listWriterConfig,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            return response()->json(['control' => $control], 201);
        }

        $control = OverlayControl::createForTemplate($template, auth()->user(), $validated);

        if ($control->isRandom()) {
            $cfg = $control->config ?? [];
            $randomState = [
                'min' => (int) ($cfg['min'] ?? 0),
                'max' => (int) ($cfg['max'] ?? 100),
                'interval' => max(100, (int) ($cfg['random_interval'] ?? 1000)),
            ];
            $this->broadcastUpdate($template, $control, $control->resolveDisplayValue(), null, $randomState);
        }

        return response()->json(['control' => $control], 201);
    }

    /**
     * Update a control's label, config, or sort_order. Key is immutable.
     *
     * @throws RandomException
     */
    public function update(Request $request, OverlayTemplate $template, OverlayControl $control): JsonResponse
    {
        abort_if($template->owner_id !== auth()->id(), 403);
        abort_if($control->overlay_template_id !== $template->id, 404);

        if ($control->source_managed) {
            $source = ucfirst($control->source ?? 'an external service');
            abort(403, "This control is managed by $source and cannot be edited manually.");
        }

        $validated = $request->validate([
            'label' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'value' => ['nullable', function ($attribute, $value, $fail) {
                if (strlen((string) $value) > 1000) {
                    $fail("The $attribute must not exceed 1000 characters.");
                }
            }],
            'config' => 'nullable|array',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Handle expression control updates
        if ($control->isExpression() && isset($validated['config']['expression'])) {
            $request->validate([
                'config.expression' => 'required|string|max:500',
            ]);

            $expression = $request->input('config.expression');
            $dependencies = OverlayControl::extractExpressionDependencies($expression);

            $available = OverlayControl::getAvailableControls(auth()->user(), $template->id, $control->id);

            $this->dependencies($dependencies, $available);

            if (OverlayControl::detectExpressionCycle($control, $dependencies, $template->id)) {
                abort(422, 'This expression would create a circular dependency.');
            }

            $control->update([
                'label' => $validated['label'] ?? $control->label,
                'description' => array_key_exists('description', $validated) ? $validated['description'] : $control->description,
                'config' => [
                    'expression' => $expression,
                    'dependencies' => $dependencies,
                ],
                'sort_order' => $validated['sort_order'] ?? $control->sort_order,
            ]);

            // Broadcast so the overlay re-registers the expression with the new formula
            ControlValueUpdated::dispatch(
                $template->slug,
                $control->broadcastKey(),
                $control->type,
                $control->value ?? '',
                auth()->user()->twitch_id,
                null,
                $expression,
            );

            return response()->json(['control' => $control->fresh()]);
        }

        // List writer update: re-validate the new source + target if either
        // is being changed. Label / sort_order updates pass through normally
        // below; value never applies to list_writer.
        if ($control->isListWriter() && isset($validated['config'])) {
            $listWriterConfig = $this->validateListWriterConfig($request, auth()->id(), $template->id);

            $control->update([
                'label' => $validated['label'] ?? $control->label,
                'description' => array_key_exists('description', $validated) ? $validated['description'] : $control->description,
                'config' => $listWriterConfig,
                'sort_order' => $validated['sort_order'] ?? $control->sort_order,
            ]);

            return response()->json(['control' => $control->fresh()]);
        }

        if (array_key_exists('value', $validated) && ! in_array($control->type, ['timer', 'expression', 'list_writer'])) {
            $validated['value'] = OverlayControl::sanitizeValue($control->type, $validated['value'] ?? '');
        } else {
            unset($validated['value']);
        }

        $control->update($validated);

        // Broadcast random state so the overlay starts/stops the random interval
        if ($control->isRandom()) {
            $cfg = $control->config ?? [];
            $randomState = [
                'min' => (int) ($cfg['min'] ?? 0),
                'max' => (int) ($cfg['max'] ?? 100),
                'interval' => max(100, (int) ($cfg['random_interval'] ?? 1000)),
            ];
            $this->broadcastUpdate($template, $control, $control->resolveDisplayValue(), null, $randomState);
        }

        return response()->json(['control' => $control->fresh()]);
    }

    /**
     * Validate the config block for a list_writer control and return the
     * canonical config array. Verifies the source control and target list
     * both exist and belong to the requesting user. Returns the int-cast
     * IDs to keep the stored JSON consistent.
     *
     * @return array{source_control_id:int,target_list_id:int}
     */
    private function validateListWriterConfig(Request $request, int $userId, int $templateId): array
    {
        $request->validate([
            'config.source_control_id' => 'required|integer',
            'config.target_list_id' => 'required|integer',
        ]);

        $sourceId = (int) $request->input('config.source_control_id');
        $targetId = (int) $request->input('config.target_list_id');

        // Source can live on this template OR be user-scoped
        // (overlay_template_id=null) the same way Expression Control
        // dependencies are scoped.
        $source = OverlayControl::where('id', $sourceId)
            ->where('user_id', $userId)
            ->where(function ($q) use ($templateId) {
                $q->where('overlay_template_id', $templateId)
                    ->orWhereNull('overlay_template_id');
            })
            ->first();
        abort_unless($source !== null, 422, 'Source control not found in this template or your user scope.');

        $target = OptionSet::where('id', $targetId)
            ->where('user_id', $userId)
            ->first();
        abort_unless($target !== null, 422, 'Target list not found or not owned by you.');

        return [
            'source_control_id' => $sourceId,
            'target_list_id' => $targetId,
        ];
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
     *
     * @throws RandomException
     */
    public function setValue(Request $request, OverlayTemplate $template, OverlayControl $control): JsonResponse
    {
        abort_if($template->owner_id !== auth()->id(), 403);
        abort_if($control->overlay_template_id !== $template->id, 404);

        if ($control->source_managed) {
            $source = ucfirst($control->source ?? 'an external service');
            abort(403, "This control is managed by $source and cannot be edited manually.");
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

        return response()->json(['control' => $control->fresh(), 'value' => $sanitized]);
    }

    /**
     * Handle timer-specific mutations.
     *
     * @throws RandomException
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
            'controls.*.description' => 'nullable|string|max:1000',
            'controls.*.type' => 'required|in:'.implode(',', OverlayControl::TYPES),
            'controls.*.value' => 'nullable|string|max:1000',
            'controls.*.config' => 'nullable|array',
            'controls.*.sort_order' => 'nullable|integer|min:0',
            'controls.*.source' => 'nullable|string|max:50',
            'controls.*.source_managed' => 'nullable|boolean',
        ]);

        $created = [];
        $user = auth()->user();

        foreach ($validated['controls'] as $item) {
            if ($item['action'] !== 'create') {
                continue;
            }

            $source = $item['source'] ?? null;

            // Skip if key already exists (scoped by source for service controls)
            $existsQuery = OverlayControl::query()->where('overlay_template_id', $template->id)->where('key', $item['key']);
            if (! empty($source)) {
                $existsQuery->where('source', $source);
            } else {
                $existsQuery->whereNull('source');
            }
            if ($existsQuery->exists()) {
                continue;
            }

            $control = OverlayControl::createForTemplate($template, $user, [
                'key' => $item['key'],
                'label' => $item['label'] ?? null,
                'description' => $item['description'] ?? null,
                'type' => $item['type'],
                'value' => $item['value'] ?? null,
                'config' => $item['config'] ?? null,
                'sort_order' => $item['sort_order'] ?? 0,
                'source' => $source,
                'source_managed' => ! empty($source) ? (bool) ($item['source_managed'] ?? true) : false,
            ]);

            $created[] = $control;
        }

        return response()->json(['created' => $created, 'count' => count($created)]);
    }

    /**
     * Dispatch the ControlValueUpdated broadcast event.
     */
    private function broadcastUpdate(OverlayTemplate $template, OverlayControl $control, string $value, ?array $timerState = null, ?array $randomState = null): void
    {
        $user = auth()->user();

        ControlValueUpdated::dispatch(
            $template->slug,
            $control->broadcastKey(),
            $control->type,
            $value,
            $user->twitch_id,
            $timerState,
            null,
            $randomState
        );
    }

    public function dependencies(array $dependencies, Collection $available): void
    {
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
                $label = $depSource ? "$depSource:$depKey" : $depKey;
                abort(422, "Referenced control '$label' not found in scope.");
            }
        }
    }
}
