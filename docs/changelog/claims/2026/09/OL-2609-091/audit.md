## Audit of OL-2609-091 - feat(help): theme switcher in the help header, coffee icon when sepia is active

**Audited:** 2026-09-25
**Commit:** 55ae69c7 (named directly on the claim's Commit line; the `Changelog: OL-2609-091` trailer resolves only to 098b574, which adds `claim.md` and nothing else)
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/welcome/app.ts:2 @55ae69c7` - `import { wireThemeMenus } from '../utils/themeMenu'`; `grep` for `applyTheme`/`storedAppearance`/`function wireThemeMenus` in that file @55ae69c7 finds only the import and the call at line 49. No later commit touches the file (`git log 55ae69c7..HEAD`), same @HEAD |
| C2 | CONFIRMED | `resources/js/utils/themeMenu.ts:47 @55ae69c7` - `localStorage.setItem('appearance', value)`; `:51` - `appearance=${value};path=/;...`. `resources/js/composables/useAppearance.ts:81,92 @55ae69c7` read/write `localStorage['appearance']`, `:39,95` set the `appearance` cookie with `path=/`. Unchanged @HEAD |
| C3 | CONFIRMED | `resources/js/utils/themeMenu.ts:63 @55ae69c7` - `window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', ...)` inside `wireThemeMenus()` (lines 32-66); `welcome/app.ts:49 @55ae69c7` still calls `wireThemeMenus()` on `DOMContentLoaded`. Unchanged @HEAD |
| C4 | CONFIRMED | `resources/views/layouts/help.blade.php:91 @55ae69c7` - the only `theme-toggle` match (count 1); `#kits` count 0. Same @HEAD (line 91) |
| C5 | CONFIRMED | `resources/views/welcome/theme-toggle.blade.php:4-6 @55ae69c7` - sun `size-4 dark:hidden`, moon `hidden size-4 dark:block sepia:hidden`, coffee `hidden size-4 sepia:block`. Built CSS `public/build/assets/app-DZtQclMA.css` emits `.dark\:block:is(.dark *)` at offset 218814 and `.sepia\:hidden:is(.theme-sepia *)` at 251132 - equal specificity, sepia later - so under `dark theme-sepia` only the coffee shows. Unchanged @HEAD |
| C6 | CONFIRMED | `resources/css/app.css:13 @55ae69c7` - `@custom-variant sepia (&:is(.theme-sepia *));`; `app.css` is not in `git show --stat 55ae69c7`. Same line @HEAD (later edits to app.css by OL-2609-127/128 do not touch it) |
| C7 | UNVERIFIABLE | tagged [unverified]; a manual browser observation on a local host, not checkable in-repo |

### Surface
Complete.

### Findings
None.

### Notes
- Unchanged lines checked: the pre-paint script at `layouts/help.blade.php:8-15 @55ae69c7` reads only `$appearance` (the `welcome.blade.php:12 @55ae69c7` one reads `localStorage` first) and is outside the diff hunk; `useAppearance.ts` and `AppearanceTabs.vue` are not in the diff.
- Surface says the three functions were "moved verbatim"; they also gained `export` in `themeMenu.ts:11,24,32 @55ae69c7`, and the OS-theme listener moved from `welcome/app.ts`'s `DOMContentLoaded` block into `wireThemeMenus()` (disclosed by C3). With the move, help pages now also register that listener and the document click-to-close listener.
- C5's CSS ordering was read from the existing `public/build` (built 2026-09-24, near HEAD), not from a build of 55ae69c7. Variant order comes from `app.css:12-13`, which is identical at both revisions.
- No tests were run; the claim has no [test] lines.
