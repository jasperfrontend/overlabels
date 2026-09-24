## Audit of OL-2609-126 - feat(shortcuts): key tips on the sidebar while G waits for its letter

**Audited:** 2026-09-24
**Commit:** 863a3a4a30cf00e4437f535bca93ee0e73e088f1
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/composables/useKeyboardShortcuts.ts:41 @863a3a4a` - `const pending = ref<string[][]>([])`; `:240` returns `armedPrefix: readonly(pending)`; `:140` `armPending(pressed)` appends the single step `['g']` on a prefix resolution, `:91` `clearPending` sets `[]`. Same @HEAD (file unchanged since). |
| C2 | CONFIRMED | `resources/js/components/AppSidebar.vue:92 @863a3a4a` - `armedPrefix.value.length === 1 && armedPrefix.value[0].length === 1 && armedPrefix.value[0][0] === 'g'`. Same @HEAD. |
| C3 | CONFIRMED | `resources/js/components/NavMain.vue:63,69 @863a3a4a` - `<kbd v-if="keyTips && item.shortcut" ... aria-hidden="true">{{ item.shortcut.toUpperCase() }}</kbd>` after the title span in both link variants. @HEAD the condition is `showTip(item)`, which also shows a confirmed tip without `keyTips` (OL-2609-127 C1). |
| C4 | CONFIRMED | `resources/js/components/NavMain.vue:24 @863a3a4a` - `keyTipClass` contains `size-5`, `rounded-sm`, `bg-violet-400`, `font-mono`, `text-violet-950`, `shadow-sm`, `dark:bg-violet-300`, `group-data-[collapsible=icon]:hidden`. Same @HEAD. |
| C5 | CONFIRMED | `resources/js/components/NavMain.vue:7-16 @863a3a4a` - `withDefaults(..., { keyTips: false })`; `AppSidebar.vue:179-180 @863a3a4a` admin and signed-out groups pass no `key-tips`. @HEAD defaults also carry `confirmed: null` (OL-2609-127). |
| C6 | CONFIRMED | `resources/js/components/AppSidebar.vue:176-178 @863a3a4a` - three `v-if="user && ..."` groups carry `:key-tips="gArmed"`; `:179-180` do not. Same @HEAD. |
| C7 | CONFIRMED | `resources/js/composables/useKeyboardShortcuts.ts @863a3a4a` - timeout `:101` `setTimeout(clearPending, ...)`; completed chord `:125`; miss `:149`; input/dialog `:118`; last listener unmount `:236`; all call `clearPending()` which sets `pending.value = []` (`:91`). @HEAD the fired chord's tip stays up via separate sidebar state (OL-2609-127 C7). |
| C8 | CONFIRMED | Compound, both halves true. `npm test -- resources/js/composables/useKeyboardShortcuts.test.ts` - 1 file, 13 passed; test file and composable identical @863a3a4a and @HEAD. Diff hunks touch only `pending`, `clearPending`, `armPending`, `handleKeyDown`, the return of `useKeyboardShortcuts()`, the import and `CHORD_TIMEOUT_MS`; `resolveKeystroke`, `parseKeyCombination`, `formatKeyCombination`, `pressedKeys` bodies are not in it. |
| C10 | CONFIRMED | `resources/js/composables/useKeyboardShortcuts.ts:28 @863a3a4a` - `CHORD_TIMEOUT_MS = 3500`; the removed line was `= 1500`, matching OL-2609-125 C7. Same @HEAD. |
| C9 | UNVERIFIABLE | tagged [unverified]; a browser observation, correctly tagged |

### Surface
Complete.

### Findings
None.

### Notes
- Unchanged lines hold @863a3a4a: the registration loop (`AppSidebar.vue:94-110`) and the Ctrl+K hint block (`:181-190`) appear only as context; `KeyboardShortcutsDialog.vue` is not in the diff.
- OL-2609-125's Unchanged line "`NavMain.vue` does not read `shortcut`" and its Risk "within 1.5 s" are superseded by this change; the prose opens with "Builds on OL-2609-125" and C10 cites 125 C7.
- Claims are numbered out of order (C10 listed before C9).
- Later drift in `AppSidebar.vue` and `NavMain.vue` is OL-2609-127 (30b0bf5d), which discloses it.
