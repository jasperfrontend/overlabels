## OL-2609-058 - feat(lists): Ctrl+S saves on the list detail page

**Shipped:** 2026-09-10
**Commit:** `git log --grep=OL-2609-058`

### Surface
- `resources/js/pages/dashboard/lists/show.vue` - imports `useKeyboardShortcuts`, registers a `save-list` shortcut on mount

### Claims
- **C1** [code] `resources/js/pages/dashboard/lists/show.vue` calls `useKeyboardShortcuts()` and, inside an `onMounted`, calls `register('save-list', 'ctrl+s', ...)` with the description `Save list`.
- **C2** [code] That shortcut's callback invokes the page's existing `saveAll()` function. No second save path is introduced: `saveAll` is the same function the header "Save changes" button's `@click` binds to.
- **C3** [code] The registration passes no `preventDefault` option, so `useKeyboardShortcuts.register()` applies its default of `true` and the browser's own Ctrl+S is suppressed.
- **C4** [code] The shortcut fires while focus is inside the items textarea: `handleKeyDown` in `resources/js/composables/useKeyboardShortcuts.ts` skips a shortcut in a text-entry target only when none of its keys are `ctrl`, `alt` or `meta`, and this combination contains `ctrl`.
- **C5** [code] The id `save-list` is not registered anywhere else in `resources/js`. The registry in `useKeyboardShortcuts.ts` is a module-level `Map` keyed by id, and `onUnmounted` deletes only the ids the unmounting instance owns.
- **C6** [unverified] Pressing Ctrl+S on `/dashboard/lists/{slug}` in a browser saves the list; confirmed by the user, not by an automated test.

### Unchanged
- `saveAll()` in the same file already returns early when `saving` is true and when neither `isDirty` nor `expiryIsDirty` holds, and already chains the items PUT into the expiry PUT. The shortcut calls it with no arguments and none of that logic is in the diff.
- `templates/edit.vue` registers `save-template` on `ctrl+s` and `templates/create.vue` registers `save-overlay` on the same combination. Both are untouched: they are separate ids on separate pages, and only a mounted page's entries are present in the shared registry.
- The overlay editor's Ctrl+S is a `useKeyboardShortcuts` registration, not a CodeMirror keymap entry - `resources/js/components/templates/TemplateCodeEditor.vue` binds only `ctrl+shift+f` and is not in the diff.

### Risk
None. The page gains a shortcut; no existing binding, route or save path changes.
