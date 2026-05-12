<?php

namespace App\Services\Expressions;

use App\Models\OverlayControl;
use App\Models\User;

/**
 * Renders an alert template's `tts_expression` against the same flat
 * $templateData payload that ships with AlertTriggered.
 *
 * SINGLE-PASS BY DESIGN: matches the day-one "tags never reparse" rule and
 * mirrors BotExpressionResolver. Substituted values are never re-scanned for
 * tags.
 *
 * Tag families inside [[[...]]]:
 *   c:<key>            -> own OverlayControl by key
 *   c:<service>:<key>  -> service-managed OverlayControl by broadcastKey
 *   <flat key>         -> $templateData lookup (event.user_name, user_name, ...)
 *
 * Pipe formatters (e.g. |number, |currency:USD) run after lookup via
 * ExpressionFormatter. Unknown tags resolve to empty string per the
 * null-over-placeholder rule.
 *
 * Reserved gate control: if the user owns a user-scoped (overlay_template_id
 * null) boolean control with key `tts` and its value is "0", render() returns
 * null. The caller treats null as "do not include tts_text in the broadcast".
 */
class AlertExpressionRenderer
{
    private const string TAG_REGEX = '/\[\[\[([\w.:\-]+)(?:\|([\w.:\- ]+))?]]]/';

    private const int MAX_RESOLVED_LENGTH = 500;

    private const string GATE_KEY = 'tts';

    /**
     * @param  array<string,mixed>  $templateData  Flat dot-keyed map already
     *                                             built for the alert payload.
     */
    public function render(User $user, ?string $expression, array $templateData): ?string
    {
        if ($expression === null || trim($expression) === '') {
            return null;
        }

        if ($this->isGatedOff($user)) {
            return null;
        }

        $controls = $this->loadControls($user);
        $locale = (string) ($user->preference('locale', 'en-US'));

        $resolved = preg_replace_callback(
            self::TAG_REGEX,
            function (array $matches) use ($controls, $templateData, $locale): string {
                $key = $matches[1];
                $pipe = $matches[2] ?? null;
                $value = $this->lookup($key, $controls, $templateData);
                if ($pipe !== null) {
                    $value = ExpressionFormatter::apply($value, $pipe, $locale);
                }

                return $value;
            },
            $expression
        );

        if (mb_strlen($resolved) > self::MAX_RESOLVED_LENGTH) {
            $resolved = mb_substr($resolved, 0, self::MAX_RESOLVED_LENGTH);
        }

        $resolved = trim($resolved);

        return $resolved === '' ? null : $resolved;
    }

    /**
     * @param  array<string,string>  $controls
     * @param  array<string,mixed>  $templateData
     */
    private function lookup(string $key, array $controls, array $templateData): string
    {
        if (str_starts_with($key, 'c:')) {
            $controlKey = substr($key, 2);

            return (string) ($controls[$controlKey] ?? '');
        }

        $value = $templateData[$key] ?? null;

        return $value === null ? '' : (string) $value;
    }

    /**
     * @return array<string,string>
     */
    private function loadControls(User $user): array
    {
        $rows = OverlayControl::where('user_id', $user->id)->get();
        $map = [];
        foreach ($rows as $control) {
            $identifier = $control->source_managed
                ? $control->broadcastKey()
                : $control->key;
            $map[$identifier] = $control->resolveDisplayValue();
        }

        return $map;
    }

    /**
     * User opts out of TTS by creating a user-scoped boolean control with the
     * reserved key and toggling it off. Absent control = TTS enabled (default).
     */
    private function isGatedOff(User $user): bool
    {
        $gate = OverlayControl::where('user_id', $user->id)
            ->whereNull('overlay_template_id')
            ->where('key', self::GATE_KEY)
            ->where('type', 'boolean')
            ->first();

        if (! $gate) {
            return false;
        }

        return $gate->resolveDisplayValue() === '0';
    }
}
