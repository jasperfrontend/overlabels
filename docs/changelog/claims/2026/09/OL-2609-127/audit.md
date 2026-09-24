## Audit of OL-2609-127 - feat(shortcuts): the key tip of a completed chord stays up and bounces while its page loads

**Audited:** 2026-09-24
**Commit:** 30b0bf5db5291827d7f4402a3ab7f84d95319313

**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/components/NavMain.vue:23-26 @30b0bf5` - `if (!item.shortcut) return false; return props.keyTips \|\| props.confirmed === item.shortcut;`; NavMain.vue unchanged since @30b0bf5 (`git diff 30b0bf5d HEAD` empty) |
| C2 | CONFIRMED | `NavMain.vue:73` and `:81 @30b0bf5` - both `<kbd v-if="showTip(item)">` carry `{ 'key-tip-confirm': confirmed === item.shortcut }` in `:class`; same @HEAD |
| C3 | CONFIRMED | `resources/js/components/AppSidebar.vue` `holdKeyTip()` @30b0bf5 - sets `confirmedShortcut.value = letter`, `clearTimeout(confirmTimer)` if set, `confirmTimer = setTimeout(done, ms)`, returns `done`, which clears the timer, nulls `confirmTimer` and sets the value to null; AppSidebar.vue unchanged since @30b0bf5 |
| C4 | CONFIRMED | `AppSidebar.vue:130-131 @30b0bf5` - `const done = holdKeyTip(item.shortcut!, CONFIRM_FALLBACK_MS); router.visit(item.href, { onFinish: done, onCancel: done });`; `CONFIRM_FALLBACK_MS = 4000`; same @HEAD |
| C5 | CONFIRMED | `AppSidebar.vue:126-128 @30b0bf5` - `_blank` branch calls `holdKeyTip(item.shortcut!, CONFIRM_NEW_TAB_MS)` and discards the returned `done`, then `window.open`; `CONFIRM_NEW_TAB_MS = 900`; the `_blank` items are Help (`:76`) and Reference (`:78`); same @HEAD |
| C6 | CONFIRMED | `resources/css/app.css:52-76 @30b0bf5` - `animation: key-tip-confirm 0.6s ease-out 1`; keyframes set only `transform: translateY(..) scale(..)`; `@media (prefers-reduced-motion: reduce) { .key-tip-confirm { animation: none; } }`; the only later change to app.css (a98c6e05) adds an unrelated `[data-tab-start]:focus` rule below it |
| C7 | CONFIRMED | `AppSidebar.vue @30b0bf5` - `gArmed` computed reads only `armedPrefix` and is not in the diff; `confirmedShortcut` is a separate `ref`; NavMain receives both as separate props; 12 items carry a `shortcut` (`:52-57`, `:66-68`, `:76-79`), so eleven others drop with `keyTips` |
| C8 | UNVERIFIABLE | tagged [unverified]; browser observation on overlabels.test |

### Surface
Complete.

### Findings
None.

### Notes
- C5 "nothing else clears it": a second chord fired inside the 900 ms window calls `holdKeyTip()` again and overwrites `confirmedShortcut` with the new letter, which removes the first tip early. C3 describes that overwrite, so no finding is raised.
- Unchanged line on OL-2609-125 C10/C11: the diff edits the body of the registered callback in `AppSidebar.vue` `onMounted`, but not the id, combination, description, group or letters those claims describe.
- No tests exist for these components and none were run; every claim is `[code]` or `[unverified]`.
