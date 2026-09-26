/**
 * The vocabulary a text control declares, read off its own config.
 *
 * A text control that may only take a handful of values (`bubble`, `snow`,
 * `heart`) says so in `config.choices`, next to the `min`/`max` a number
 * control already carries. The overlay document round-trips it like any
 * other config key, so an install, an import or a copy lands with it on the
 * row. Both the Controls tab and the product designer read it from here and
 * render a select instead of a free-text box.
 *
 * Two shapes are accepted: a list of `{ value, label, hint }` objects, which
 * is what the recipes write, and a bare list of strings for a hand-authored
 * document, where the value is its own label. Anything malformed degrades to
 * "no vocabulary" and the control renders as plain text, never throws.
 */
export interface ControlChoice {
  value: string;
  label: string;
  hint: string;
}

export function controlChoices(config: unknown): ControlChoice[] {
  if (!config || typeof config !== 'object') return [];

  const raw = (config as Record<string, unknown>).choices;
  if (!Array.isArray(raw)) return [];

  const seen = new Set<string>();
  const choices: ControlChoice[] = [];

  for (const item of raw) {
    let value: unknown;
    let label: unknown;
    let hint: unknown;

    if (typeof item === 'string') {
      value = item;
    } else if (item && typeof item === 'object') {
      ({ value, label, hint } = item as Record<string, unknown>);
    }

    if (typeof value !== 'string' || value === '' || seen.has(value)) continue;
    seen.add(value);

    choices.push({
      value,
      label: typeof label === 'string' && label !== '' ? label : value,
      hint: typeof hint === 'string' ? hint : '',
    });
  }

  return choices;
}

/** The hint of the choice currently held, or nothing when the value is not one of them. */
export function choiceHint(choices: ControlChoice[], value: string): string {
  return choices.find((choice) => choice.value === value)?.hint ?? '';
}
