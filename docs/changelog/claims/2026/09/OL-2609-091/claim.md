## OL-2609-091 - feat(help): theme switcher in the help header, coffee icon when sepia is active

**Shipped:** 2026-09-17
**Commit:** `git show 55ae69c7` (committed before this ID was allocated, so it carries no trailer; the trailer is on the commit that adds this file)

### Surface
- `resources/js/utils/themeMenu.ts` - new file: `applyTheme()`, `storedAppearance()`, `wireThemeMenus()`, moved verbatim out of `welcome/app.ts`
- `resources/js/welcome/app.ts` - the three functions above and the `prefers-color-scheme` listener removed; imports and calls `wireThemeMenus()`
- `resources/js/help/main.ts` - imports and calls `wireThemeMenus()` on `DOMContentLoaded`
- `resources/views/layouts/help.blade.php` - `@include('welcome.theme-toggle')` added between the Updates link and the dashboard button; the `/#kits` link removed
- `resources/views/welcome/theme-toggle.blade.php` - trigger's moon svg gains `sepia:hidden`; a third svg (the coffee cup already used by the Sepia option) added with `hidden size-4 sepia:block`
- `resources/js/components/DarkModeToggle.vue` - trigger's `Moon` gains `sepia:scale-0 sepia:rotate-90`; a `Coffee` icon added with `sepia:scale-100 sepia:rotate-0`

### Claims
- **C1** [code] `welcome/app.ts` no longer defines `applyTheme`, `storedAppearance` or `wireThemeMenus`; it imports `wireThemeMenus` from `../utils/themeMenu`.
- **C2** [code] `themeMenu.ts` writes the choice to `localStorage['appearance']` and to an `appearance` cookie with `path=/`, the same two stores `composables/useAppearance.ts` reads and writes.
- **C3** [code] `themeMenu.ts` registers the `prefers-color-scheme: dark` change listener inside `wireThemeMenus()`, so the homepage keeps following the OS theme in system mode after the move.
- **C4** [code] `layouts/help.blade.php` includes `welcome.theme-toggle` exactly once, and contains no link to `/#kits`.
- **C5** [code] In `theme-toggle.blade.php` the trigger renders three svgs whose visibility classes are mutually exclusive across the three `<html>` states: none (sun), `dark` (moon), `dark theme-sepia` (coffee).
- **C6** [code] The `sepia` variant used by C5 and by `DarkModeToggle.vue` is `@custom-variant sepia (&:is(.theme-sepia *))` in `resources/css/app.css`, which is not in the diff.
- **C7** [unverified] On `overlabels.test`, choosing Light, Dark and Sepia from the help header changed `<html>`'s classes accordingly, the choice survived a reload, and the trigger showed the coffee cup under sepia on both `/help/bot/commands` and `/dashboard`.

### Unchanged
- The pre-paint script in `layouts/help.blade.php` still reads only the server-shared `$appearance` (cookie), not `localStorage`, unlike the one in `welcome.blade.php`; both stores are written together by C2, so the two agree, and the script is not in the diff.
- `composables/useAppearance.ts` is not in the diff: the Inertia app keeps its own class logic, and `themeMenu.ts` mirrors it rather than importing it, because the Blade pages have no Vue.
- `AppearanceTabs.vue` on the settings page is not in the diff: it has no trigger icon, each tab already carries its own.
