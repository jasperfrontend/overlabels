import { onMounted, onUnmounted } from 'vue';

type ShortcutCallback = (event: KeyboardEvent) => void;

interface Shortcut {
  keys: string[];
  callback: ShortcutCallback;
  description?: string;
  scope?: string;
  preventDefault?: boolean;
}

const shortcuts: Map<string, Shortcut> = new Map();

/**
 * Check if a key combination matches the pressed keys
 */
function matchesKeyCombination(event: KeyboardEvent, keyCombination: string[]): boolean {
  // Build an array of the currently pressed keys
  const pressedKeys: string[] = [];

  if (event.ctrlKey) pressedKeys.push('ctrl');
  if (event.altKey) pressedKeys.push('alt');
  if (event.shiftKey) pressedKeys.push('shift');
  if (event.metaKey) pressedKeys.push('meta');

  // Add the actual key pressed (convert to lowercase for case-insensitive comparison)
  pressedKeys.push(event.key.toLowerCase());

  // Check if the arrays match (order matters)
  if (pressedKeys.length !== keyCombination.length) return false;

  return keyCombination.every((key, index) => pressedKeys[index] === key.toLowerCase());
}

/**
 * Handle keydown events globally
 */
function handleKeyDown(event: KeyboardEvent): void {
  // Skip if the event target is an input element or textarea
  if (
    event.target instanceof HTMLInputElement ||
    event.target instanceof HTMLTextAreaElement ||
    (event.target instanceof HTMLElement && event.target.isContentEditable)
  ) {
    return;
  }

  // Check all registered shortcuts
  for (const shortcut of shortcuts.values()) {
    if (matchesKeyCombination(event, shortcut.keys)) {
      if (shortcut.preventDefault !== false) {
        event.preventDefault();
      }
      shortcut.callback(event);
      break;
    }
  }
}

/**
 * Format key combination into a user-friendly string
 */
function formatKeyCombination(keys: string[]): string {
  return keys.map(key => {
    // Capitalize first letter of special keys
    if (['ctrl', 'alt', 'shift', 'meta'].includes(key.toLowerCase())) {
      return key.charAt(0).toUpperCase() + key.slice(1);
    }
    // Format single character keys
    return key.length === 1 ? key.toUpperCase() : key;
  }).join('+');
}

/**
 * Parse a key combination string into an array of keys
 */
function parseKeyCombination(combination: string): string[] {
  return combination.toLowerCase().split('+').map(key => key.trim());
}

export function useKeyboardShortcuts() {
  /**
   * Register a keyboard shortcut
   */
  function register(id: string, combination: string | string[], callback: ShortcutCallback, options: Partial<Shortcut> = {}) {
    const keys = Array.isArray(combination) ? combination : parseKeyCombination(combination);

    shortcuts.set(id, {
      keys,
      callback,
      description: options.description,
      scope: options.scope,
      preventDefault: options.preventDefault !== false
    });
  }

  /**
   * Unregister a keyboard shortcut
   */
  function unregister(id: string) {
    shortcuts.delete(id);
  }

  /**
   * Get all registered shortcuts
   */
  function getAllShortcuts(): { id: string; keys: string; description?: string; scope?: string }[] {
    return Array.from(shortcuts.entries()).map(([id, shortcut]) => ({
      id,
      keys: formatKeyCombination(shortcut.keys),
      description: shortcut.description,
      scope: shortcut.scope
    }));
  }

  /**
   * Setup event listeners
   */
  onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
  });

  /**
   * Clean up event listeners
   */
  onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
  });

  return {
    register,
    unregister,
    getAllShortcuts
  };
}
