# Keyboard Shortcuts System

This directory contains various composables including the keyboard shortcuts system, which provides a centralized way to manage keyboard shortcuts throughout the application.

## Usage in Vue Components

Use the `useKeyboardShortcuts` composable in your Vue components:

```typescript
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';

// Inside your setup function
const { register, unregister, getAllShortcuts } = useKeyboardShortcuts();

// Register shortcuts during component mounting
onMounted(() => {
  // Register a keyboard shortcut with ID, key combination, callback function, and options
  register('save-document', 'ctrl+s', () => {
    saveDocument();
  }, { 
    description: 'Save document', // Used for displaying in shortcut help
    scope: 'editor',             // Optional scope for grouping
    preventDefault: true         // Prevent default browser action (default: true)
  });

  // You can also use an array of keys
  register('preview', ['ctrl', 'p'], () => {
    previewDocument();
  });
});

// You can show a list of keyboard shortcuts
const shortcuts = getAllShortcuts();
// Returns: [{ id: 'save-document', keys: 'Ctrl+S', description: 'Save document', scope: 'editor' }, ...]
```

## Usage Outside Vue Components

For non-component code, use the global singleton from `lib/keyboardShortcuts`:

```typescript
import { keyboardShortcuts } from '@/lib/keyboardShortcuts';

// Register a shortcut globally
keyboardShortcuts.register('global-search', 'ctrl+k', () => {
  openGlobalSearch();
});

// Unregister when no longer needed
keyboardShortcuts.unregister('global-search');
```

## Key Combinations

Key combinations can be specified in two ways:

1. As a string with keys separated by `+`: `'ctrl+s'`, `'shift+alt+p'`
2. As an array of keys: `['ctrl', 's']`, `['shift', 'alt', 'p']`

Special keys that are supported:
- `ctrl`
- `alt`
- `shift`
- `meta` (Command key on macOS)

## Adding New Shortcuts

When adding new shortcuts, consider these guidelines:

1. Use common conventions where appropriate (e.g., Ctrl+S for Save)
2. Avoid conflicts with browser shortcuts when possible
3. Group related shortcuts with the same modifier key
4. Provide clear descriptions for keyboard shortcut help dialogs
5. Consider accessibility - some users may not be able to use certain combinations

## Displaying Keyboard Shortcuts

Use the `getAllShortcuts()` method to get a list of all registered shortcuts for displaying in a help dialog.
