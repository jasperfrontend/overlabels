/**
 * Display names and ordering for user-created control types. Shared by the
 * overlay Controls tab (ControlsManager) and /settings/controls so both group
 * a user's controls under the same headings in the same order.
 */
export const CONTROL_TYPE_LABELS: Record<string, string> = {
  text: 'Text',
  number: 'Number',
  counter: 'Counter',
  timer: 'Timer',
  boolean: 'Toggle',
  color: 'Color',
  expression: 'Expression',
  datetime: 'Date/Time',
  list_writer: 'List writer',
};

export const CONTROL_TYPE_ORDER = ['counter', 'timer', 'number', 'text', 'color', 'boolean', 'expression', 'list_writer', 'datetime'];
