import { isTextEntryTarget } from '@/utils/isTextEntryTarget';
import { onMounted, onUnmounted, readonly, ref } from 'vue';

type ShortcutCallback = (event: KeyboardEvent) => void;

export interface Shortcut {
  // One entry per keystroke. A plain shortcut like Ctrl+S is one step; a chord
  // like "G then O" is two. Every step is a modifier list plus one key.
  steps: string[][];
  callback: ShortcutCallback;
  description?: string;
  // Heading the Ctrl+K dialog files this under. Ungrouped shortcuts share one section.
  group?: string;
  preventDefault?: boolean;
}

export interface ShortcutListing {
  id: string;
  keys: string;
  description?: string;
  group?: string;
}

// How long a chord prefix stays armed. Gmail and GitHub give about 1.5 s; this
// is two seconds longer because the sidebar shows key tips while G is armed,
// and the user needs time to read them and pick. Still short enough that a
// stray G does not swallow a keystroke much later.
export const CHORD_TIMEOUT_MS = 3500;

const MODIFIER_KEYS = ['ctrl', 'alt', 'shift', 'meta'];

// Global registry shared across all composable instances.
// Each entry is added/removed by the component that owns it.
const registry: Map<string, Shortcut> = new Map();
const version = ref(0);
let listenerCount = 0;

// The chord steps pressed so far, as normalised key lists. Empty when no
// prefix is armed. Reactive so the UI can show key tips while a prefix waits
// for its second key (the sidebar does, for G).
const pending = ref<string[][]>([]);
let pendingTimer: ReturnType<typeof setTimeout> | null = null;

export function pressedKeys(event: Pick<KeyboardEvent, 'ctrlKey' | 'altKey' | 'shiftKey' | 'metaKey' | 'key'>): string[] {
  const keys: string[] = [];
  if (event.ctrlKey) keys.push('ctrl');
  if (event.altKey) keys.push('alt');
  if (event.shiftKey) keys.push('shift');
  if (event.metaKey) keys.push('meta');
  keys.push(event.key.toLowerCase());
  return keys;
}

function sameStep(pressed: string[], step: string[]): boolean {
  if (pressed.length !== step.length) return false;
  return step.every((key, index) => pressed[index] === key.toLowerCase());
}

function hasModifier(shortcut: Shortcut): boolean {
  return shortcut.steps.some((step) => step.some((k) => ['ctrl', 'alt', 'meta'].includes(k.toLowerCase())));
}

export type Resolution = { kind: 'fire'; shortcut: Shortcut } | { kind: 'prefix' } | { kind: 'none' };

/**
 * Decide what one keystroke means given the chord steps already pressed.
 *
 * A shortcut whose steps are exactly `prefix + pressed` fires. If none does
 * but some shortcut continues past this step, the keystroke arms (or extends)
 * the prefix. Otherwise it is nothing. A complete match wins over a longer
 * chord sharing the same start, so a single-key shortcut can never be shadowed
 * by a chord registered later.
 */
export function resolveKeystroke(shortcuts: Iterable<Shortcut>, prefix: string[][], pressed: string[]): Resolution {
  const depth = prefix.length;
  let continues = false;

  for (const shortcut of shortcuts) {
    if (shortcut.steps.length <= depth) continue;
    const prefixMatches = prefix.every((step, index) => sameStep(step, shortcut.steps[index]));
    if (!prefixMatches || !sameStep(pressed, shortcut.steps[depth])) continue;

    if (shortcut.steps.length === depth + 1) return { kind: 'fire', shortcut };
    continues = true;
  }

  return continues ? { kind: 'prefix' } : { kind: 'none' };
}

function clearPending(): void {
  pending.value = [];
  if (pendingTimer !== null) {
    clearTimeout(pendingTimer);
    pendingTimer = null;
  }
}

function armPending(step: string[]): void {
  pending.value = [...pending.value, step];
  if (pendingTimer !== null) clearTimeout(pendingTimer);
  pendingTimer = setTimeout(clearPending, CHORD_TIMEOUT_MS);
}

function handleKeyDown(event: KeyboardEvent): void {
  // A bare modifier press is not a step. Ignoring it keeps an armed prefix
  // alive while the user reaches for Shift, and never fires anything.
  if (['Control', 'Alt', 'Shift', 'Meta'].includes(event.key)) return;

  const inInput = isTextEntryTarget(event.target);

  // A modal owns the keyboard while it's open: single-key page shortcuts must
  // not reach through it (e.g. 'e' jumping a <select> to "Expression", or 'a'
  // firing "Add to OBS" from a button inside the dialog).
  const inDialog = event.target instanceof Element && event.target.closest('[role="dialog"]') !== null;

  // Typing never continues a chord: a G pressed on the page and an O typed
  // into a search box are unrelated keystrokes.
  if (inInput || inDialog) clearPending();

  const pressed = pressedKeys(event);
  const wasPending = pending.value.length > 0;
  const resolution = resolveKeystroke(registry.values(), pending.value, pressed);

  if (resolution.kind === 'fire') {
    clearPending();
    // Inside inputs or an open dialog, only fire shortcuts that use a modifier key
    if ((inInput || inDialog) && !hasModifier(resolution.shortcut)) return;

    if (resolution.shortcut.preventDefault !== false) {
      event.preventDefault();
    }
    resolution.shortcut.callback(event);
    return;
  }

  if (resolution.kind === 'prefix') {
    // A chord never carries a modifier, so it has no business starting inside
    // an input or a dialog.
    if (inInput || inDialog) return;
    armPending(pressed);
    event.preventDefault();
    return;
  }

  // An armed prefix followed by a key that completes nothing is a miss: the
  // chord is dropped, and the key is swallowed so 'G then E' cannot fall
  // through to a page's bare 'E' shortcut.
  if (wasPending) {
    clearPending();
    event.preventDefault();
  }
}

function formatKey(key: string): string {
  if (key === ' ') return 'Space';
  if (MODIFIER_KEYS.includes(key.toLowerCase())) {
    return key.charAt(0).toUpperCase() + key.slice(1);
  }
  return key.length === 1 ? key.toUpperCase() : key;
}

export function formatKeyCombination(steps: string[][]): string {
  return steps.map((step) => step.map(formatKey).join('+')).join(' then ');
}

/**
 * 'ctrl+s' is one step; 'g o' is a chord of two, one per whitespace-separated
 * token. 'space' names the space bar inside a step ('ctrl+space').
 */
export function parseKeyCombination(combination: string): string[][] {
  return combination
    .trim()
    .toLowerCase()
    .split(/\s+/)
    .map((step) => step.split('+').map((key) => (key === 'space' ? ' ' : key)));
}

export function useKeyboardShortcuts() {
  // Track which IDs this component instance owns, so we clean up only ours.
  const ownedIds: Set<string> = new Set();

  function register(
    id: string,
    combination: string | string[],
    callback: ShortcutCallback,
    options: Partial<Pick<Shortcut, 'description' | 'group' | 'preventDefault'>> = {},
  ) {
    // An array is one step spelled out ('ctrl', 's'); a string may be a chord.
    const steps = Array.isArray(combination) ? [combination] : parseKeyCombination(combination);

    registry.set(id, {
      steps,
      callback,
      description: options.description,
      group: options.group,
      preventDefault: options.preventDefault !== false,
    });
    ownedIds.add(id);
    version.value++;
  }

  function unregister(id: string) {
    registry.delete(id);
    ownedIds.delete(id);
    version.value++;
  }

  function getAllShortcuts(): ShortcutListing[] {
    // Read version so Vue tracks this as a reactive dependency
    void version.value;
    return Array.from(registry.entries()).map(([id, shortcut]) => ({
      id,
      keys: formatKeyCombination(shortcut.steps),
      description: shortcut.description,
      group: shortcut.group,
    }));
  }

  onMounted(() => {
    if (listenerCount === 0) {
      window.addEventListener('keydown', handleKeyDown);
    }
    listenerCount++;
  });

  onUnmounted(() => {
    // Remove only the shortcuts this instance registered
    for (const id of ownedIds) {
      registry.delete(id);
    }
    ownedIds.clear();

    listenerCount--;
    if (listenerCount === 0) {
      window.removeEventListener('keydown', handleKeyDown);
      clearPending();
    }
  });

  return { register, unregister, getAllShortcuts, armedPrefix: readonly(pending) };
}
