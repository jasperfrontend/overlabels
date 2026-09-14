## OL-2609-071 - refactor(dashboard): tab the dashboard's lists and extract the one violet tab strip

**Shipped:** 2026-09-14
**Commit:** `git log --grep=OL-2609-071`

### Surface
- `resources/js/components/TabStrip.vue` - new file, the shared violet tab strip
- `resources/js/pages/dashboard/index.vue` - two-column layout, three lists moved into a TabStrip, WhatsNewCard call site removed
- `resources/js/pages/templates/show.vue` - hand-written strip replaced with TabStrip
- `resources/js/pages/templates/edit.vue` - hand-written strip replaced with TabStrip
- `app/Http/Controllers/DashboardController.php` - dashboard query limits raised

### Claims
- **C1** [code] `TabStrip.vue` exports an interface `TabStripItem` with `key`, `label`, `icon` and an optional `class`.
- **C2** [code] `TabStrip.vue` declares its selection with `defineModel<string>({ required: true })` and takes a single `tabs` prop typed `TabStripItem[]`.
- **C3** [code] `TabStrip.vue` renders one `<button type="button" role="tab">` per entry inside a `<div role="tablist">`, with `aria-selected` bound to whether that entry's `key` equals the model.
- **C4** [code] `TabStrip.vue` gives the selected tab `tabindex="0"` and every other tab `tabindex="-1"`.
- **C5** [code] `TabStrip.vue`'s `onKeydown` handles `ArrowRight`, `ArrowLeft`, `Home` and `End`, wrapping at both ends, and moves focus to the newly selected button via a `buttons` ref populated by `setButton`.
- **C6** [code] `TabStrip.vue` applies `px-4 py-2.5` to every tab and `border-t-2 border-t-violet-400 bg-white text-black dark:bg-violet-500/30 dark:text-violet-300 dark:hover:text-violet-200` to the selected one.
- **C7** [code] `TabStrip.vue` appends each entry's optional `class` to that tab's class list.
- **C8** [code] `TabStrip.vue` renders its `actions` slot in a sibling container, guarded by `v-if="$slots.actions"`.
- **C9** [code] The string `border-t-violet-400` occurs in exactly one file under `resources/js`: `TabStrip.vue`.
- **C10** [code] `show.vue` and `edit.vue` each render `<TabStrip v-model="mainTab" :tabs="mainTabs" />` and contain no `<button v-for="tab in mainTabs">` of their own.
- **C11** [code] In `show.vue`, the `obs` entry pushed onto `mainTabs` carries `class: 'product:border-t-green-400 product:bg-green-600 product:text-white product:hover:bg-green-700'`, and no `tab.key === 'obs'` ternary remains in its template.
- **C12** [code] `show.vue` and `edit.vue` both type their `mainTabs` computed as `TabStripItem[]` rather than `Array<{ key: string; label: string; icon: any }>`.
- **C13** [code] `dashboard/index.vue`'s top grid is `grid-cols-1 lg:grid-cols-5`, with the tabbed column `lg:col-span-3` and the updates column `lg:col-span-2`.
- **C14** [code] `dashboard/index.vue` declares three tabs - `overlays` (`Layers`), `alerts` (`Bell`), `activity` (`Newspaper`) - and renders their panels as three `v-show` siblings of the `TabStrip`, not inside it.
- **C15** [code] `dashboard/index.vue` passes the view-all and create links through `TabStrip`'s `actions` slot, with their hrefs resolved by the `tabActions` computed from the current tab.
- **C16** [code] `dashboard/index.vue` no longer imports or renders `WhatsNewCard`, and `resources/js/components/WhatsNewCard.vue` still exists.
- **C17** [code] `dashboard/index.vue` still declares the `whatsNew` prop, and `DashboardController::index()` still passes it.
- **C18** [code] `dashboard/index.vue` imports nothing from `@/components/ui/tabs`; `resources/js/pages/admin/users/show.vue` is the only file that does.
- **C19** [code] The overlays and alerts panels pass `empty-message` to `TemplateCollection` instead of being hidden by a `v-if` on list length.
- **C20** [code] `DashboardController::index()` fetches 10 alert templates, 10 static templates and 20 recent events, was 5 of each.
- **C21** [unverified] None of the three strips was rendered in a browser before shipping. Verified only by `vue-tsc`, eslint, prettier, `npm test` and `npm run build`.

### Unchanged
- `DashboardSectionHeader.vue` is not in the diff. It still carries the title plus the view and create buttons, and the dashboard's Recent updates column is still its one remaining caller - the three tabbed sections dropped it because the tab label already prints the section title.
- `resources/js/components/ui/tabs/*` is not in the diff. `TabStrip` does not wrap it: `show.vue` and `edit.vue` render their panels in a bordered box that is a sibling of the strip, and Reka's `TabsContent` must be a descendant of `TabsRoot`.
- The `useKeyboardShortcuts` registrations in `show.vue` and `edit.vue` that bind number keys 1-7 to `mainTabs` entries are not in the diff; they still set `mainTab` directly.
- `EventsTable.vue`, `TemplateCollection.vue` and `UpdatesList.vue` are not in the diff. The dashboard moved where they render, not what they render.

### Risk
`DashboardController::index()` now selects four times the events and twice the templates per dashboard load (C20).
