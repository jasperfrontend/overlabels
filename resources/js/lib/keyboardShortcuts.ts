/**
 * Global keyboard shortcuts manager
 * This provides a singleton instance for managing keyboard shortcuts across the application
 * outside Vue components
 */

type ShortcutCallback = (event: KeyboardEvent) => void;

interface Shortcut {
  keys: string[];
  callback: ShortcutCallback;
  description?: string;
  scope?: string;
  preventDefault?: boolean;
}

class KeyboardShortcutsManager {
  private shortcuts: Map<string, Shortcut> = new Map();
  private initialized: boolean = false;

  constructor() {
    this.handleKeyDown = this.handleKeyDown.bind(this);
  }

  /**
   * Initialize the keyboard shortcuts manager
   */
  public init(): void {
    if (!this.initialized) {
      window.addEventListener('keydown', this.handleKeyDown);
      this.initialized = true;
    }
  }

  /**
   * Cleanup event listeners
   */
  public destroy(): void {
    window.removeEventListener('keydown', this.handleKeyDown);
    this.initialized = false;
  }

  /**
   * Register a keyboard shortcut
   */
  public register(id: string, combination: string | string[], callback: ShortcutCallback, options: Partial<Shortcut> = {}): void {
    const keys = Array.isArray(combination) ? combination : this.parseKeyCombination(combination);

    this.shortcuts.set(id, {
      keys,
      callback,
      description: options.description,
      scope: options.scope,
      preventDefault: options.preventDefault !== false
    });

    // Ensure the keyboard shortcuts manager is initialized when registering the first shortcut
    if (!this.initialized) {
      this.init();
    }
  }

  /**
   * Unregister a keyboard shortcut
   */
  public unregister(id: string): void {
    this.shortcuts.delete(id);
  }

  /**
   * Get all registered shortcuts
   */
  public getAllShortcuts(): { id: string; keys: string; description?: string; scope?: string }[] {
    return Array.from(this.shortcuts.entries()).map(([id, shortcut]) => ({
      id,
      keys: this.formatKeyCombination(shortcut.keys),
      description: shortcut.description,
      scope: shortcut.scope
    }));
  }

  /**
   * Handle keydown events globally
   */
  private handleKeyDown(event: KeyboardEvent): void {
    // Skip if the event target is an input element or textarea
    if (
      event.target instanceof HTMLInputElement ||
      event.target instanceof HTMLTextAreaElement ||
      (event.target instanceof HTMLElement && event.target.isContentEditable)
    ) {
      return;
    }

    // Check all registered shortcuts
    for (const shortcut of this.shortcuts.values()) {
      if (this.matchesKeyCombination(event, shortcut.keys)) {
        if (shortcut.preventDefault !== false) {
          event.preventDefault();
        }
        shortcut.callback(event);
        break;
      }
    }
  }

  /**
   * Check if a key combination matches the pressed keys
   */
  private matchesKeyCombination(event: KeyboardEvent, keyCombination: string[]): boolean {
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
   * Format key combination into a user-friendly string
   */
  private formatKeyCombination(keys: string[]): string {
    return keys.map(key => {
      // Capitalize the first letter of special keys
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
  private parseKeyCombination(combination: string): string[] {
    return combination.toLowerCase().split('+').map(key => key.trim());
  }
}

// Export a singleton instance
export const keyboardShortcuts = new KeyboardShortcutsManager();

// Auto-initialize in browser environments
if (typeof window !== 'undefined') {
  keyboardShortcuts.init();
}
