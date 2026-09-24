## OL-2609-131 - docs(claims): record the unmount prefix clear and the two type exports OL-2609-125 left out

**Shipped:** 2026-09-24
**Commit:** `git log --grep=OL-2609-131`

Remedies OL-2609-125 audit F1, F2.

### Surface
- `docs/changelog/claims/2026/09/OL-2609-125/remedy.md` - new file, the remedy of the OL-2609-125 audit

### Claims
- **C1** [code] In `useKeyboardShortcuts()`, the `onUnmounted` handler calls `clearPending()` in the same `listenerCount === 0` branch that removes the `keydown` listener, so the pending chord prefix is cleared when the last listener unmounts. This was added by the OL-2609-125 commit and is not recorded in its Surface or Claims (corrects OL-2609-125 Surface, audit F1).
- **C2** [code] `useKeyboardShortcuts.ts` exports the `Shortcut` interface, which was module-private before the OL-2609-125 commit (corrects OL-2609-125 Surface, audit F2).
- **C3** [code] `useKeyboardShortcuts.ts` exports the `Resolution` type (`fire` with a `shortcut`, `prefix`, or `none`), the return type of `resolveKeystroke()`, added by the OL-2609-125 commit (corrects OL-2609-125 Surface, audit F2).
- **C4** [code] Outside `useKeyboardShortcuts.ts`, the only importer of `Shortcut` is `useKeyboardShortcuts.test.ts`, and nothing imports `Resolution`.

### Unchanged
- No code is in this diff: the unmount clear and both exports are recorded as they stand in `useKeyboardShortcuts.ts`, which is not touched.
