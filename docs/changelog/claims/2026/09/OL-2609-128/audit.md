## Audit of OL-2609-128 - feat(a11y): Tab starts on the page's list, and a skip link leads the tab order

**Audited:** 2026-09-24
**Commit:** a98c6e05c98364b3fbb465bafc192f797aa8f7a0
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/composables/useFocusStart.ts:26-28 @a98c6e05` - `querySelector('[data-tab-start]') ?? querySelector('#main-content')`, which is null when neither matches; file unchanged @HEAD |
| C2 | CONFIRMED | `resources/js/composables/useFocusStart.ts:30-38 @a98c6e05` - returns null with no target, `setAttribute('tabindex','-1')` only under `!hasAttribute('tabindex')`, `focus({ preventScroll: !options.scroll })`, returns target; unchanged @HEAD |
| C3 | CONFIRMED | `resources/js/composables/useFocusStart.ts:40-47 @a98c6e05` - `onMounted`, `if (active && active !== document.body) return;` then `focusStart()`; unchanged @HEAD |
| C4 | CONFIRMED | `resources/js/layouts/AppLayout.vue:55,58 @a98c6e05` - `useProductFocus()` then `useFocusStart()` in setup; `package-lock.json:625-626 @a98c6e05` pins `@inertiajs/vue3` 3.7.0; `node_modules/@inertiajs/vue3/dist/index.js:579` (installed 3.7.0) - `key.value = options.preserveState ? key.value : Date.now();`; pages wrap themselves in `<AppLayout>` (e.g. `resources/js/pages/kits/index.vue:59 @a98c6e05`), so the layout is inside the keyed page component |
| C5 | CONFIRMED | `resources/js/layouts/app/AppSidebarLayout.vue:25-35 @a98c6e05` - `<a>` is the first child of `<AppShell variant="sidebar">`, `:href="#${MAIN_CONTENT_ID}"`, classes `sr-only focus:not-sr-only focus:fixed ...`, `@click.prevent="focusStart({ scroll: true })"`, text "Skip to content"; unchanged @HEAD |
| C6 | CONFIRMED | `AppSidebarLayout.vue @a98c6e05` - `<AppContent :id="MAIN_CONTENT_ID" ...>`; `resources/js/components/AppContent.vue @a98c6e05` declares only `variant`/`class` props and has a single v-if/v-else root `SidebarInset`; `resources/js/components/ui/sidebar/SidebarInset.vue @a98c6e05` declares only `class` and has a single root `<main data-slot="sidebar-inset">`, so `id` falls through to it |
| C7 | CONFIRMED | `git show a98c6e05 -- resources/css/app.css` is one hunk of 8 added lines (comment plus `[data-tab-start]:focus, #main-content:focus { outline: none; }`), no removed or modified lines; unchanged @HEAD |
| C8 | CONFIRMED | `git grep data-tab-start a98c6e05 -- resources/js` finds exactly one occurrence in each of the six pages, on the Surface-listed elements: `templates/index.vue:225`, `dashboard/lists/index.vue:359`, `dashboard/recents.vue:579` (on `EventsTable` with `v-if="recentEvents.data.length > 0"` at :578), `dashboard/stream-sessions.vue:449`, `dashboard/gps-sessions.vue:121`, `kits/index.vue:74`; @HEAD `lists/index.vue` and `recents.vue` changed (OL-2609-129, OL-2609-130, and `48a98b5c`) but the `data-tab-start` lines are untouched |
| C9 | UNVERIFIABLE | tagged [unverified]; a browser observation on overlabels.test |

### Surface
Complete.

### Findings
None.

### Notes
- Unchanged: `CollectionList.vue`, `EventsTable.vue`, `KitCard.vue`, `NavMain.vue` and `useKeyboardShortcuts.ts` are not in a98c6e05. `KitCard.vue` was changed later by `23e59c12` (a `.vue`-only commit, claim-exempt).
- Risk says the two `/products` pages get no focus management. `49eca947` (a `.vue`-only commit, claim-exempt) later changed that: `resources/js/layouts/ProductsLayout.vue:101 @HEAD` renders inside `<AppLayout>`, so `useFocusStart()` now runs there.
- `gps-sessions.vue:121 @a98c6e05` puts `data-tab-start` on a container with no `v-if`. When there are no sessions, the target is that empty div and not `main`. No claim says otherwise.
- No tests were run. Every claim is `[code]` or `[unverified]`.
