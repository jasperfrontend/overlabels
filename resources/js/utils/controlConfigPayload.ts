/**
 * The `config` the control dialog sends when it saves.
 *
 * The dialog draws fields for a handful of config keys per type and rebuilds
 * those from the form. Everything else on the row is not the dialog's, and is
 * sent back as it was: `choices`, `webfont` and `enabled_if` come from an
 * overlay document or a product install and have no field here. The server
 * stores the config it is sent whole, so a key left out of the payload is a
 * key deleted - which is what used to happen: editing a text, color or
 * boolean control sent `null`, and a number sent its six fields alone, so a
 * renamed label turned a select back into a text box.
 *
 * Expression and list_writer controls are not built here; the dialog sends
 * exactly their config, and the server rebuilds both.
 */
export interface DialogConfigForm {
  min?: number;
  max?: number;
  step?: number;
  reset_value?: number;
  random?: boolean;
  random_interval?: number;
  mode?: string;
  base_seconds?: number;
  target_datetime?: string;
}

/** Every key the dialog draws a field for, on any type. */
const DIALOG_KEYS = new Set([
  'min',
  'max',
  'step',
  'reset_value',
  'random',
  'random_interval',
  'mode',
  'base_seconds',
  'target_datetime',
  'offset_seconds',
  'running',
  'started_at',
]);

export function controlConfigPayload(
  type: string,
  form: DialogConfigForm,
  existing: Record<string, unknown> | null | undefined,
): Record<string, unknown> | null {
  // Another type's dialog keys are dropped too: they describe a widget this
  // control does not have.
  const kept = Object.fromEntries(Object.entries(existing ?? {}).filter(([key]) => !DIALOG_KEYS.has(key)));

  if (type === 'number' || type === 'counter') {
    return {
      ...kept,
      min: form.min ?? null,
      max: form.max ?? null,
      step: form.step ?? null,
      reset_value: form.reset_value,
      random: form.random || false,
      random_interval: form.random ? form.random_interval || 1000 : null,
    };
  }

  if (type === 'timer') {
    return {
      mode: form.mode,
      base_seconds: form.mode === 'countdown' ? form.base_seconds : 0,
      target_datetime: form.mode === 'countto' ? form.target_datetime : null,
      offset_seconds: 0,
      running: false,
      started_at: null,
    };
  }

  return Object.keys(kept).length ? kept : null;
}
