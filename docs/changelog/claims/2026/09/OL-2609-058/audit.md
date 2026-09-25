## Audit of OL-2609-058 - feat(lists): Ctrl+S saves on the list detail page

**Audited:** 2026-09-25
**Commit:** 057d8fccf08bf39d5680a7124707f65f6977fd33
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/pages/dashboard/lists/show.vue:163 @057d8fc` - `const { register } = useKeyboardShortcuts();`; `:165-167 @057d8fc` - `onMounted(() => { register('save-list', 'ctrl+s', () => saveAll(), { description: 'Save list' }); })`; same lines @HEAD |
| C2 | CONFIRMED | `resources/js/pages/dashboard/lists/show.vue:166 @057d8fc` - callback is `() => saveAll()`; `:94 @057d8fc` - `function saveAll()` predates the diff (not in hunks); `:596 @057d8fc` - header button `@click="saveAll"` renders `'Save changes'` (`:597`); same @HEAD |
| C3 | CONFIRMED | `resources/js/pages/dashboard/lists/show.vue:166 @057d8fc` - options object is `{ description: 'Save list' }` only; `resources/js/composables/useKeyboardShortcuts.ts:91 @057d8fc` - `preventDefault: options.preventDefault !== false`, applied at `:45-47`; @HEAD same default at `:196`, applied at `:129-131` (restructured by OL-2609-125) |
| C4 | CONFIRMED | `resources/js/composables/useKeyboardShortcuts.ts:42-43 @057d8fc` - `hasModifier` = keys include `ctrl`/`alt`/`meta`; `if ((inInput \|\| inDialog) && !hasModifier) break;`; `ctrl+s` parses to `['ctrl','s']` (`:66-73`); items `<textarea` at `show.vue:661 @057d8fc`; @HEAD the same rule is `hasModifier()` `:59-61` and `:127` (OL-2609-125) |
| C5 | CONFIRMED | `git grep -n save-list 057d8fc -- resources/js` returns only `show.vue:166`; same @HEAD. `useKeyboardShortcuts.ts:15 @057d8fc` - module-level `const registry: Map<string, Shortcut>`; `:120-125 @057d8fc` - `onUnmounted` deletes only `ownedIds`; @HEAD `:34`, `:226-231` |
| C6 | UNVERIFIABLE | tagged [unverified]; a manual browser observation, not checkable in-repo |

### Surface
Complete.

### Findings
None.

### Notes
- `useKeyboardShortcuts.ts` has since been rewritten for chords (`keys` -> `steps`, `resolveKeystroke`) by OL-2609-125, whose Unchanged section names `dashboard/lists/show` as an untouched call site; C3 to C5 still hold @HEAD.
- `show.vue` was edited by OL-2609-129 (URLs only, route moved to `/lists`); the registration at `:166` is unchanged @HEAD.
- Unchanged lines checked: `saveAll()` body (`:94-157 @057d8fc`) is outside the diff hunks; `templates/edit.vue:512` (`save-template`) and `templates/create.vue:120` (`save-overlay`) @057d8fc are not in the diff; `TemplateCodeEditor.vue` @057d8fc registers only `ctrl+shift+f` (`:82-90`), has no CodeMirror keymap import, and is not in the diff.
- No `[test]` claims, so no tests were run. `CLAUDE.md` and earlier claims contain no rule about `save-list` or a list-page Ctrl+S.
