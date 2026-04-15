<?php

namespace App\Http\Controllers\Api\Internal;

use App\Events\ControlValueUpdated;
use App\Http\Controllers\Controller;
use App\Models\OverlayControl;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotControlController extends Controller
{
    /**
     * Return the first matching non-source-managed control for a user by key.
     * Shape: { key, type, value, label }
     */
    public function show(string $login, string $key): JsonResponse
    {
        $user = $this->resolveUser($login);
        if (! $user) {
            return response()->json(['error' => 'channel not found'], 404);
        }

        $control = $this->userControlsQuery($user, $key)->first();
        if (! $control) {
            return response()->json(['error' => 'control not found'], 404);
        }

        return response()->json([
            'key' => $control->key,
            'type' => $control->type,
            'value' => $control->resolveDisplayValue(),
            'label' => $control->label,
        ]);
    }

    /**
     * Apply a bot-driven write to all of a user's matching non-source-managed
     * controls and broadcast ControlValueUpdated for each.
     *
     * Actions: set (requires value), increment, decrement, reset, enable,
     * disable, toggle. increment/decrement/reset only valid for number|counter;
     * enable/disable/toggle only valid for boolean.
     */
    public function update(Request $request, string $login, string $key): JsonResponse
    {
        $data = $request->validate([
            'action' => 'required|string|in:set,increment,decrement,reset,enable,disable,toggle',
            'value' => 'required_if:action,set|string',
            'amount' => 'nullable|numeric',
        ]);

        $user = $this->resolveUser($login);
        if (! $user) {
            return response()->json(['error' => 'channel not found'], 404);
        }

        $controls = $this->userControlsQuery($user, $key)->with('template')->get();

        if ($controls->isEmpty()) {
            return response()->json(['error' => 'control not found'], 404);
        }

        $action = $data['action'];
        $numericActions = ['increment', 'decrement', 'reset'];
        $booleanActions = ['enable', 'disable', 'toggle'];

        if (in_array($action, $numericActions, true) && ! $this->allNumeric($controls)) {
            return response()->json(
                ['error' => "action '$action' requires a number or counter control"],
                422,
            );
        }

        if (in_array($action, $booleanActions, true) && ! $this->allBoolean($controls)) {
            return response()->json(
                ['error' => "action '$action' requires a boolean control"],
                422,
            );
        }

        if (in_array($action, ['enable', 'disable'], true)) {
            $target = $action === 'enable' ? '1' : '0';
            if ($controls->every(fn (OverlayControl $c) => $c->value === $target)) {
                $state = $action === 'enable' ? 'enabled' : 'disabled';

                return response()->json(
                    ['error' => "$key already $state"],
                    409,
                );
            }
        }

        $amount = isset($data['amount']) ? (float) $data['amount'] : 1.0;
        $applied = null;

        foreach ($controls as $control) {
            $newValue = match ($action) {
                'set' => OverlayControl::sanitizeValue($control->type, $data['value']),
                'increment' => (string) ((float) ($control->value ?? 0) + $amount),
                'decrement' => (string) ((float) ($control->value ?? 0) - $amount),
                'reset' => (string) ((float) ($control->config['reset_value'] ?? 0)),
                'enable' => '1',
                'disable' => '0',
                'toggle' => $control->value === '1' ? '0' : '1',
            };

            $control->update(['value' => $newValue]);

            $overlaySlug = $control->overlay_template_id
                ? ($control->template?->slug ?? '')
                : '';

            ControlValueUpdated::dispatch(
                $overlaySlug,
                $control->broadcastKey(),
                $control->type,
                $newValue,
                $user->twitch_id,
            );

            $applied = [
                'key' => $control->key,
                'type' => $control->type,
                'value' => $newValue,
            ];
        }

        return response()->json($applied);
    }

    private function resolveUser(string $login): ?User
    {
        $login = strtolower($login);

        return User::where('bot_enabled', true)
            ->whereNotNull('twitch_data')
            ->get()
            ->first(fn (User $u) => strtolower($u->twitch_data['login'] ?? '') === $login);
    }

    /**
     * Query for a user's non-source-managed controls matching a key.
     * Excludes service-managed controls (kofi, streamlabs, etc.) from chat reach.
     */
    private function userControlsQuery(User $user, string $key): Builder
    {
        return OverlayControl::where('user_id', $user->id)
            ->where('key', $key)
            ->where('source_managed', false);
    }

    private function allNumeric(Collection $controls): bool
    {
        return $controls->every(fn (OverlayControl $c) => in_array($c->type, ['number', 'counter']));
    }

    private function allBoolean(Collection $controls): bool
    {
        return $controls->every(fn (OverlayControl $c) => $c->type === 'boolean');
    }
}
