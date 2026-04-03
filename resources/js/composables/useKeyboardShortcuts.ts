import { onMounted, onUnmounted, ref } from 'vue';

type ShortcutCallback = (event: KeyboardEvent) => void;

interface Shortcut {
  keys: string[];
  callback: ShortcutCallback;
  description?: string;
  preventDefault?: boolean;
}

// Global registry shared across all composable instances.
// Each entry is added/removed by the component that owns it.
const registry: Map<string, Shortcut> = new Map();
const version = ref(0);
let listenerCount = 0;

function matchesKeyCombination(event: KeyboardEvent, keyCombination: string[]): boolean {
  const pressedKeys: string[] = [];
  if (event.ctrlKey) pressedKeys.push('ctrl');
  if (event.altKey) pressedKeys.push('alt');
  if (event.shiftKey) pressedKeys.push('shift');
  if (event.metaKey) pressedKeys.push('meta');
  pressedKeys.push(event.key.toLowerCase());

  if (pressedKeys.length !== keyCombination.length) return false;
  return keyCombination.every((key, index) => pressedKeys[index] === key.toLowerCase());
}

function handleKeyDown(event: KeyboardEvent): void {
  const inInput =
    event.target instanceof HTMLInputElement ||
    event.target instanceof HTMLTextAreaElement ||
    (event.target instanceof HTMLElement && event.target.isContentEditable);

  for (const shortcut of registry.values()) {
    if (matchesKeyCombination(event, shortcut.keys)) {
      // Inside inputs, only fire shortcuts that use a modifier key
      const hasModifier = shortcut.keys.some((k) =>
        ['ctrl', 'alt', 'meta'].includes(k.toLowerCase()),
      );
      if (inInput && !hasModifier) break;

      if (shortcut.preventDefault !== false) {
        event.preventDefault();
      }
      shortcut.callback(event);
      break;
    }
  }
}

function formatKeyCombination(keys: string[]): string {
  return keys
    .map((key) => {
      if (key === ' ') return 'Space';
      if (['ctrl', 'alt', 'shift', 'meta'].includes(key.toLowerCase())) {
        return key.charAt(0).toUpperCase() + key.slice(1);
      }
      return key.length === 1 ? key.toUpperCase() : key;
    })
    .join('+');
}

function parseKeyCombination(combination: string): string[] {
  // Split on '+' but preserve 'space' as a key name
  return combination
    .toLowerCase()
    .replace(/\bspace\b/g, ' ')
    .split('+')
    .map((key) => key.trim() || ' ');
}

export function useKeyboardShortcuts() {
  // Track which IDs this component instance owns, so we clean up only ours.
  const ownedIds: Set<string> = new Set();

  function register(
    id: string,
    combination: string | string[],
    callback: ShortcutCallback,
    options: Partial<Pick<Shortcut, 'description' | 'preventDefault'>> = {},
  ) {
    const keys = Array.isArray(combination) ? combination : parseKeyCombination(combination);

    registry.set(id, {
      keys,
      callback,
      description: options.description,
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

  function getAllShortcuts(): { id: string; keys: string; description?: string }[] {
    // Read version so Vue tracks this as a reactive dependency
    void version.value;
    return Array.from(registry.entries()).map(([id, shortcut]) => ({
      id,
      keys: formatKeyCombination(shortcut.keys),
      description: shortcut.description,
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
    }
  });

  return { register, unregister, getAllShortcuts };
}
