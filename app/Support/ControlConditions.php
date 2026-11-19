<?php

namespace App\Support;

/**
 * `config.enabled_if`: when a control is worth editing, read off the others.
 *
 * A control can say it only does anything while other controls of the same
 * overlay hold certain values: the bubble colors are unused while Look is
 * Plain, Spawn X and Y only steer the cannon. The editing screens (the product
 * designer and the Values tab, through the one shared knob) grey such a
 * control out with the reason under it. Nothing else changes: the value is
 * kept, presets and chat still write it, and the overlay reads it as before.
 *
 * Each condition is the body of an `[[[if:...]]]` - `c:look != plain`,
 * `c:spawn = cannon`, `c:speed > 4`, or a bare `c:show_shadow` for "is on"
 * (equality is a single `=`, as it is there) - parsed by the same
 * DSL pattern and evaluated by the same rules, so there is one condition
 * language in Overlabels. A list means every condition must hold. There is
 * no `disabled_if` and no "or": `!=` covers what those would be used for, and
 * two opposite keys could contradict each other.
 *
 * Disabling never changes a value, so one control's state never feeds into
 * another's and no ordering or cycle can matter. The only references worth
 * refusing are ones that cannot mean anything: a key the overlay does not
 * declare, a control with no knob, or the control itself.
 *
 * The frontend twin is `resources/js/utils/controlConditions.ts`.
 */
class ControlConditions
{
    /**
     * The conditions a config declares, as a list. A single string is one
     * condition; anything that is not a string or a list of strings is null,
     * which a caller treats as "always enabled" rather than an error.
     *
     * @return list<string>|null
     */
    public static function of(mixed $config): ?array
    {
        if (! is_array($config) || ! array_key_exists('enabled_if', $config)) {
            return null;
        }

        $raw = $config['enabled_if'];
        $list = is_string($raw) ? [$raw] : $raw;

        if (! is_array($list) || ! array_is_list($list) || $list === []) {
            return null;
        }

        foreach ($list as $condition) {
            if (! is_string($condition) || trim($condition) === '') {
                return null;
            }
        }

        return array_map('trim', $list);
    }

    /**
     * Every problem with the `enabled_if` declarations of one overlay's
     * controls, one sentence each.
     *
     * @param  list<array{key: string, type: string, config?: array<string, mixed>|null}>  $controls
     * @return list<string>
     */
    public static function problems(array $controls): array
    {
        $declared = [];
        foreach ($controls as $control) {
            $declared[$control['key']] = $control;
        }

        $problems = [];

        foreach ($declared as $key => $control) {
            $config = $control['config'] ?? null;
            if (! is_array($config) || ! array_key_exists('enabled_if', $config)) {
                continue;
            }

            if (! in_array($control['type'], ProductDesigner::KNOB_TYPES, true)) {
                $problems[] = "Control \"{$key}\" is a {$control['type']} control; only text, number, boolean and color controls can declare enabled_if.";

                continue;
            }

            $conditions = self::of($config);
            if ($conditions === null) {
                $problems[] = "Control \"{$key}\" has an enabled_if that is not a condition or a list of conditions.";

                continue;
            }

            foreach ($conditions as $condition) {
                [$subject, $operator, $value] = self::split($condition);

                if (! str_starts_with($subject, 'c:') || ! isset($declared[substr($subject, 2)])) {
                    $problems[] = "Control \"{$key}\" depends on \"{$subject}\", which is not a control of this overlay. Write it as c:<key>.";

                    continue;
                }

                $other = $declared[substr($subject, 2)];

                if ($operator === '==' && str_starts_with((string) $value, '=')) {
                    // `==` is not an operator: it reads as `=` against "= value".
                    $problems[] = "Control \"{$key}\" writes \"{$condition}\"; equality is a single =, as in [[[if:...]]].";
                } elseif ($other['key'] === $key) {
                    $problems[] = "Control \"{$key}\" depends on itself.";
                } elseif (! in_array($other['type'], ProductDesigner::KNOB_TYPES, true)) {
                    $problems[] = "Control \"{$key}\" depends on \"{$other['key']}\", a {$other['type']} control; a condition may only read text, number, boolean and color controls.";
                } elseif ($operator === '==' || $operator === '!=') {
                    $choices = array_column($other['config']['choices'] ?? [], 'value');
                    if ($choices !== [] && ! in_array($value, $choices, true)) {
                        $problems[] = "Control \"{$key}\" compares {$other['key']} to \"{$value}\", which is not one of its choices (".implode(', ', $choices).').';
                    }
                }
            }
        }

        return $problems;
    }

    /**
     * A condition as [subject, operator, value], the way `[[[if:...]]]` reads
     * it: `=` is `==`, quotes around the value are optional, and a bare key
     * has no operator.
     *
     * @return array{0: string, 1: string|null, 2: string|null}
     */
    private static function split(string $condition): array
    {
        if (preg_match(Dsl::conditionBodyPattern(), $condition, $m)) {
            $value = preg_replace('/^["\']|["\']$/', '', trim($m[3])) ?? '';

            return [$m[1], $m[2] === '=' ? '==' : $m[2], $value];
        }

        return [$condition, null, null];
    }
}
