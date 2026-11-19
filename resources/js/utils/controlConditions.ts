import { useConditionalTemplates } from '@/composables/useConditionalTemplates';
import { controlChoices } from '@/utils/controlChoices';

/**
 * `config.enabled_if`: whether a control is worth editing right now, and why not.
 *
 * A control may say it only does anything while other controls of the same
 * overlay hold certain values (`enabled_if=["c:look != plain"]`). The shared
 * knob greys such a control out in place, with the reason under it, so the
 * page never changes shape as other knobs move. Nothing else changes: the
 * value is kept, and the overlay reads it as before.
 *
 * Each condition is the body of an `[[[if:...]]]` (`c:spawn = cannon`,
 * `c:look != plain`, `c:speed > 4`, a bare `c:shadow`), parsed and evaluated by
 * the overlay engine's own functions, so there is one condition language.
 * A list means every condition must hold. Anything malformed - a condition
 * naming a control that is not here, a config that is not a string or a list
 * of strings - is ignored, so the worst a bad declaration can do is leave a
 * control enabled. The server-side twin, and the rules a recipe is held to,
 * is `App\Support\ControlConditions`.
 */
export interface ConditionSubject {
  key: string;
  label: string | null;
  config: Record<string, unknown> | null;
  value: string;
}

const { parseCondition, evaluateCondition } = useConditionalTemplates();

/** The conditions a config declares, or none. */
export function enabledIf(config: unknown): string[] {
  if (!config || typeof config !== 'object') return [];

  const raw = (config as Record<string, unknown>).enabled_if;
  const list = typeof raw === 'string' ? [raw] : raw;
  if (!Array.isArray(list)) return [];

  return list.filter((condition): condition is string => typeof condition === 'string' && condition.trim() !== '').map((c) => c.trim());
}

const PHRASES: Record<string, string> = {
  '>': 'above',
  '<': 'below',
  '>=': 'at least',
  '<=': 'at most',
};

/**
 * Why a control is disabled, as the sentence to show under it, or null when
 * it is enabled. `subjects` holds the other controls by tag key (`c:look`).
 * The first condition that does not hold is the one named.
 */
export function disabledReason(config: unknown, subjects: Record<string, ConditionSubject>): string | null {
  for (const condition of enabledIf(config)) {
    const parsed = parseCondition(condition);
    const subject = subjects[parsed.variable];
    if (!subject) continue;

    if (evaluateCondition(parsed, { [parsed.variable]: subject.value })) continue;

    const label = subject.label || subject.key;
    if (parsed.isBoolean) return `Only used while ${label} is on.`;

    const value = parsed.value ?? '';
    const shown = controlChoices(subject.config).find((choice) => choice.value === value)?.label ?? value;

    if (parsed.operator === '!=') return `Not used while ${label} is ${shown}.`;
    if (parsed.operator === '==') return `Only used while ${label} is ${shown}.`;

    return `Only used while ${label} is ${PHRASES[parsed.operator ?? ''] ?? parsed.operator} ${shown}.`;
  }

  return null;
}
