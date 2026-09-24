## Audit of OL-2609-125 - feat(shortcuts): G then a letter jumps to a main navigation page, and the Ctrl+K dialog groups shortcuts into sections

**Audited:** 2026-09-24
**Commit:** 745636469209f427117b605fb5f3dcf087a0df01
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/composables/useKeyboardShortcuts.ts:166-174 @7456364` - trim, lowercase, `split(/\s+/)`, each token split on `+` with `space` -> `' '`. Every other `register()` string at @7456364 (`ctrl+space`, `alt+r`, `alt+h`, `alt+f`, `ctrl+b`, `ctrl+k`, `ctrl+s`, `ctrl+p`, `ctrl+shift+f`, `s`, `e`, digits) has no whitespace, so each parses to one step. Parser unchanged @HEAD |
| C2 | CONFIRMED | `useKeyboardShortcuts.ts:187 @7456364` - `Array.isArray(combination) ? [combination] : parseKeyCombination(combination)`; same @HEAD |
| C3 | CONFIRMED | `useKeyboardShortcuts.ts:70-86 @7456364` - fire when `steps.length === depth + 1` after prefix and pressed match; `continues` -> `prefix`; else `none`; same @HEAD |
| C4 | CONFIRMED | `useKeyboardShortcuts.ts:81 @7456364` - returns `fire` inside the loop on the first complete match; a longer chord seen earlier only sets `continues`, which that return ignores; same @HEAD |
| C5 | CONFIRMED | `useKeyboardShortcuts.ts:105 @7456364` - `if (['Control', 'Alt', 'Shift', 'Meta'].includes(event.key)) return;` first statement; same @HEAD |
| C6 | CONFIRMED | `useKeyboardShortcuts.ts:116 @7456364` clears on `inInput \|\| inDialog`; `:137` returns before `armPending` in the `prefix` branch; same @HEAD |
| C7 | CONFIRMED | `useKeyboardShortcuts.ts:27,138-139,99 @7456364` - `CHORD_TIMEOUT_MS = 1500`, `armPending(pressed)`, `preventDefault()`, `setTimeout(clearPending, CHORD_TIMEOUT_MS)`. @HEAD the constant is 3500 (OL-2609-126 C10) |
| C8 | CONFIRMED | `useKeyboardShortcuts.ts:146-149 @7456364` - `if (wasPending) { clearPending(); event.preventDefault(); }`, no callback; same @HEAD |
| C9 | CONFIRMED | `useKeyboardShortcuts.ts:125 @7456364` - `if ((inInput \|\| inDialog) && !hasModifier(resolution.shortcut)) return;`, `hasModifier` tests `ctrl`/`alt`/`meta` across all steps; same @HEAD |
| C10 | CONFIRMED | `resources/js/components/AppSidebar.vue:90-107 @7456364` - loops the three arrays, skips items without `shortcut`, id `` `go-to-${item.title.toLowerCase()}` ``, combination `` `g ${item.shortcut}` ``, `{ description: item.title, group: 'Go to' }`; loop unchanged @HEAD |
| C11 | CONFIRMED | `AppSidebar.vue:52-57,66-68,76-79 @7456364` - o a b l k p e s t h r d on the twelve named items, all distinct; same @HEAD |
| C12 | CONFIRMED | `AppSidebar.vue:99-103 @7456364` - `window.open(item.href, '_blank', 'noopener,noreferrer')` when `target === '_blank'`, else `router.visit(item.href)`. @HEAD each branch also calls `holdKeyTip()` and `router.visit` gets `onFinish`/`onCancel` (OL-2609-127 C4, C5) |
| C13 | CONFIRMED | `AppSidebar.vue:50,62,74 @7456364` - each array is `user.value ? [...] : []`, so `route()` is evaluated only in the signed-in branch and the loop gets three empty arrays |
| C14 | CONFIRMED | `resources/js/components/KeyboardShortcutsDialog.vue:15-31 @7456364` - first-seen `ordered` list keyed on `group ?? ''`, stable sort puts `''` first; `:77` `v-if="sections.length > 1"`; `:78` `section.name \|\| 'Shortcuts'`; file unchanged @HEAD |
| C15 | CONFIRMED | `npm test -- resources/js/composables/useKeyboardShortcuts.test.ts` at HEAD (test file identical to @7456364): 13 passed. `useKeyboardShortcuts.test.ts:16-24 @7456364` asserts both C1 parse examples, `:33` "G then O", `:39` pressedKeys order, `:52-83` fire/prefix/none, `[['g']]`+`['e']` -> none with bare `e` registered, `['shift','o']` -> none, bare `g` wins over `g o` listed first |
| C16 | UNVERIFIABLE | tagged [unverified] (browser observation on overlabels.test) |

### Surface
Complete.

### Findings
- **F1** Scope - `useKeyboardShortcuts.ts:234 @7456364` adds `clearPending()` when the last listener unmounts; no claim, Surface line or Unchanged line of OL-2609-125 records it (the Surface line mentions only the prefix and its timer). OL-2609-126 C7 later mentions it in passing, so a remedy only needs to restate it.
- **F2** Scope - `useKeyboardShortcuts.ts:6,59 @7456364` exports the `Shortcut` interface (previously module-private) and a new `Resolution` type; the Surface line lists the other new exports (`pressedKeys`, `resolveKeystroke`, `CHORD_TIMEOUT_MS`, `ShortcutListing`) but not these two. A remedy claim only needs to record them.

### Notes
- C7's 1500 ms is 3500 ms @HEAD, disclosed by OL-2609-126 C10; C12's callback gained the key-tip hold in OL-2609-127 C4/C5. Neither is a finding.
- All four Unchanged lines hold: no `register()` call site, `isTextEntryTarget.ts` (its `isContentEditable` branch at line 19 @7456364), `NavMain.vue` or the sidebar's Ctrl+K hint block is touched by the diff.
- OL-2609-058 C4's modifier rule for text-entry targets holds after the rewrite (C9).
- The tests were run at HEAD, not a checkout of @7456364; the test file is identical and OL-2609-126 C8 states the functions it tests were not modified.
