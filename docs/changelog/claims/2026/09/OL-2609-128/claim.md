## OL-2609-128 - feat(a11y): Tab starts on the page's list, and a skip link leads the tab order

**Shipped:** 2026-09-24
**Commit:** `git log --grep=OL-2609-128`

The sidebar comes first in the DOM and nothing moved focus after a page rendered, so the first Tab
on any page landed on the logo and a page's own content sat twenty-odd presses further on. A new
composable moves the sequential focus starting point into the page once the app layout has
mounted: onto the element marked `data-tab-start` when the page marks one, otherwise onto the main
landmark. Six list pages mark their primary list. A visually hidden "Skip to content" link is the
first Tab stop in the app shell.

### Surface
- `resources/js/composables/useFocusStart.ts` - new: `TAB_START_ATTRIBUTE`, `MAIN_CONTENT_ID`, `focusStartTarget()`, `focusStart()`, `useFocusStart()`
- `resources/js/layouts/AppLayout.vue` - calls `useFocusStart()` in setup, after `useProductFocus()`
- `resources/js/layouts/app/AppSidebarLayout.vue` - skip link before `ProductFlowFrame`; `AppContent` gets `:id="MAIN_CONTENT_ID"`
- `resources/css/app.css` - `[data-tab-start]:focus, #main-content:focus { outline: none; }`
- `resources/js/pages/templates/index.vue` - `data-tab-start` on `TemplateCollection`
- `resources/js/pages/dashboard/lists/index.vue` - `data-tab-start` on `CollectionList`
- `resources/js/pages/dashboard/recents.vue` - `data-tab-start` on `EventsTable`
- `resources/js/pages/dashboard/stream-sessions.vue` - `data-tab-start` on the `<nav aria-label="Streams">` rail
- `resources/js/pages/dashboard/gps-sessions.vue` - `data-tab-start` on the sessions container
- `resources/js/pages/kits/index.vue` - `data-tab-start` on the user's kits grid

### Claims
- **C1** [code] `focusStartTarget()` returns the first element matching `[data-tab-start]`, else the element with id `main-content`, else null.
- **C2** [code] `focusStart()` sets `tabindex="-1"` on the target only when it has no `tabindex` attribute, then calls `focus()` with `preventScroll: true` unless called with `{ scroll: true }`, and returns the target (null when there is none).
- **C3** [code] `useFocusStart()` registers an `onMounted` hook that returns without focusing when `document.activeElement` is anything other than `document.body` or null, and calls `focusStart()` otherwise.
- **C4** [code] `AppLayout.vue` calls `useFocusStart()` in setup, so the hook runs on every mount of the layout; Inertia 3.7.0 keys the page component with `Date.now()` on every visit that does not set `preserveState` (`node_modules/@inertiajs/vue3/dist/index.js`, `key.value = options.preserveState ? key.value : Date.now()`), so the layout remounts on every such visit and does not remount on a `preserveState` visit.
- **C5** [code] The skip link in `AppSidebarLayout.vue` is an `<a>` with `href="#main-content"`, text "Skip to content", the first element inside `AppShell`, `sr-only` at rest with `focus:not-sr-only` and fixed positioning on focus, and `@click.prevent` calling `focusStart({ scroll: true })`.
- **C6** [code] `AppContent` receives `:id="MAIN_CONTENT_ID"`, which falls through `AppContent` and `SidebarInset` onto the `<main data-slot="sidebar-inset">` element, so `#main-content` is the app's main landmark.
- **C7** [code] `app.css` removes the outline on `[data-tab-start]:focus` and `#main-content:focus`; no other focus style in the file changes.
- **C8** [code] Six pages carry one `data-tab-start` each, on the elements listed in Surface; in `recents.vue` it sits on the `EventsTable` behind its existing `v-if`, so the attribute is absent when there are no events and focus falls back to `main`.
- **C9** [unverified] On overlabels.test in Chrome, dark mode: a full load of `/templates?filter=mine&type=static` left `document.activeElement` on the `[data-tab-start]` div with `tabindex="-1"`; one Tab moved it to the first row's stretched link (`aria-label` "Twitch Chat", `href` `/templates/357`). On `/dashboard/stream-sessions` the active element was the `nav[aria-label="Streams"]` whose first button is the newest stream; dispatching G then E moved the active element to the recents `EventsTable` div after the visit. On `/dashboard` the active element was `main#main-content` with computed `outline-style: none`. Focusing the skip link with the window focused gave `position: fixed`, violet background, and a visible pill at the top-left.

### Unchanged
- `CollectionList.vue`, `EventsTable.vue`, `KitCard.vue` and `NavMain.vue` are not in the diff; which elements inside a list are focusable is as before.
- `useKeyboardShortcuts.ts` is not in the diff; a focused `[data-tab-start]` container is not a text-entry target, so chords keep working from it.

### Risk
None for existing data. A page whose first render already holds focus (an autofocus field) is left
alone by C3. A page rendered outside `AppLayout` (the two `/products` pages, help) gets no focus
management from this change.
