## Audit of OL-2609-131 - docs(claims): record the unmount prefix clear and the two type exports OL-2609-125 left out

**Audited:** 2026-09-24
**Commit:** 97633b4f3ee1e296e1ee8d7f5ede016cf3e86385
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/composables/useKeyboardShortcuts.ts:226-236 @97633b4` - `onUnmounted` decrements `listenerCount`; inside `if (listenerCount === 0)` it calls `removeEventListener('keydown', handleKeyDown)` then `clearPending()`. `git show 7456364` adds exactly the `+      clearPending();` line in that branch. File unchanged @HEAD (`git diff 97633b4f HEAD` empty for it) |
| C2 | CONFIRMED | `useKeyboardShortcuts.ts:6 @97633b4` - `export interface Shortcut {`; `useKeyboardShortcuts.ts:6 @7456364^` - `interface Shortcut {` (module-private); same @HEAD |
| C3 | CONFIRMED | `useKeyboardShortcuts.ts:63 @97633b4` - `export type Resolution = { kind: 'fire'; shortcut: Shortcut } \| { kind: 'prefix' } \| { kind: 'none' };`, `:74` `resolveKeystroke(...): Resolution`; absent @7456364^, added as a `+` line in 7456364; same @HEAD |
| C4 | CONFIRMED | `git grep -nwE "Shortcut\|Resolution" 97633b4f -- resources/js` excluding the module itself: only `useKeyboardShortcuts.test.ts:2,6` (imports `type Shortcut`) and `pages/Terms.vue:222-223` (prose "Dispute Resolution", not an import). No importer of `Resolution`. Same result @HEAD |

### Surface
Complete.

### Findings
None.

### Notes
- The remedy.md in the OL-2609-125 folder maps F1 to C1 and F2 to C2/C3 (C4 naming the importer); both RECORD outcomes answer the findings in `OL-2609-125/audit.md` as written, which asked only for the restatement.
- Unchanged line holds: `useKeyboardShortcuts.ts` is not in the diff of 97633b4f (two paths: the claim and `OL-2609-125/remedy.md`).
