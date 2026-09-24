## OL-2609-126 - feat(shortcuts): key tips on the sidebar while G waits for its letter

**Shipped:** 2026-09-24
**Commit:** `git log --grep=OL-2609-126`

Builds on OL-2609-125. While the G chord prefix is armed, every main navigation item that has a
chord letter shows it as a small filled square beside the item, the way Word shows KeyTips after
Alt. The pending prefix in `useKeyboardShortcuts` becomes reactive and is exposed read-only; the
sidebar derives "G is armed" from it and hands a boolean to `NavMain`.

### Surface
- `resources/js/composables/useKeyboardShortcuts.ts` - `pending` becomes a `ref<string[][]>`; `clearPending`, `armPending` and `handleKeyDown` read and write `.value`; `useKeyboardShortcuts()` returns `armedPrefix: readonly(pending)`; `CHORD_TIMEOUT_MS` 1500 -> 3500
- `resources/js/components/AppSidebar.vue` - `gArmed` computed from `armedPrefix`; passed as `key-tips` to the three signed-in `NavMain` groups
- `resources/js/components/NavMain.vue` - `keyTips` prop (default false); `keyTipClass`; a `<kbd>` after the title in both link variants, rendered when `keyTips && item.shortcut`; the internal link's title span gains `truncate`

### Claims
- **C1** [code] `useKeyboardShortcuts()` returns `armedPrefix`, a read-only ref of the module-level `pending` chord steps; it is empty when no prefix is armed and `[['g']]` between a bare G press and the second key or the timeout.
- **C2** [code] `gArmed` in `AppSidebar.vue` is true only when `armedPrefix` holds exactly one step of exactly one key equal to `g`; any other armed prefix leaves it false.
- **C3** [code] `NavMain.vue` renders a `<kbd aria-hidden="true">` with `item.shortcut.toUpperCase()` after the title span, for an item, only when the `keyTips` prop is true and the item has a `shortcut`.
- **C4** [code] `keyTipClass` sets `bg-violet-400` by default and `dark:bg-violet-300`, with `text-violet-950`, `size-5`, `rounded-sm`, `font-mono`, `shadow-sm`, and `group-data-[collapsible=icon]:hidden`.
- **C5** [code] `keyTips` defaults to false via `withDefaults`, so `NavMain` call sites that do not pass it (the admin group and the signed-out Learn group) render no tips.
- **C6** [code] The three signed-in `NavMain` groups in `AppSidebar.vue` receive `:key-tips="gArmed"`; the admin group and the signed-out group do not.
- **C7** [code] Clearing the prefix, by the timeout, a completed chord, a miss, a keystroke inside an input or dialog, or the last listener unmounting, sets `pending.value = []`, so the tips disappear on every path that ends the chord.
- **C8** [test] The 13 tests in `useKeyboardShortcuts.test.ts` (OL-2609-125 C15) still pass; `resolveKeystroke`, `parseKeyCombination`, `formatKeyCombination` and `pressedKeys` are not in the diff.
- **C10** [code] `CHORD_TIMEOUT_MS` is 3500, two seconds longer than the 1500 recorded in OL-2609-125 C7, so the tips stay readable before the prefix expires.
- **C9** [unverified] On overlabels.test in Chrome, dark mode: pressing G showed O A B L K P E S T H R D beside the twelve items; a zoom two seconds later showed none (observed while `CHORD_TIMEOUT_MS` was still 1500, before C10). With the `dark` class removed from `<html>`, pressing G showed the same twelve tips in violet-400 with dark letters, distinct from the active Overlays row.

### Unchanged
- The chord registration loop in `AppSidebar.vue` (OL-2609-125 C10) and the twelve letters (C11) are not in the diff; the tips read the same `shortcut` field the chords are built from.
- The `<kbd>` hint block at the bottom of the sidebar is not in the diff.
- `KeyboardShortcutsDialog.vue` is not in the diff; the tips are a sidebar affordance, the dialog stays the reference.

### Risk
None for existing data. The tips are hidden when the sidebar is collapsed to icons, so a user with
a collapsed sidebar gets the chords without the visual prompt.
