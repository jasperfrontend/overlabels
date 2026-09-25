## Audit of OL-2609-071 - refactor(dashboard): tab the dashboard's lists and extract the one violet tab strip

**Audited:** 2026-09-25
**Commit:** f6adf7e27e43c7604574b63de5eb0e94f8c4b210
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/components/TabStrip.vue:15-21 @f6adf7e` - `export interface TabStripItem { key: string; label: string; icon: Component; class?: string }`; TabStrip.vue unchanged @HEAD |
| C2 | CONFIRMED | `TabStrip.vue:23 @f6adf7e` - `defineProps<{ tabs: TabStripItem[] }>()`; `:25` - `defineModel<string>({ required: true })` |
| C3 | CONFIRMED | `TabStrip.vue:56-63 @f6adf7e` - `<div role="tablist">` wrapping `v-for` `<button type="button" role="tab" :aria-selected="model === tab.key">` |
| C4 | CONFIRMED | `TabStrip.vue:64 @f6adf7e` - `:tabindex="model === tab.key ? 0 : -1"` |
| C5 | CONFIRMED | `TabStrip.vue:37-51 @f6adf7e` - ArrowRight wraps last->0, ArrowLeft wraps 0->last, Home->0, End->last; `buttons.value[next]?.focus()` at `:50`; `buttons` ref `:27` filled by `setButton` `:29-31` via `:ref` at `:60` |
| C6 | CONFIRMED | `TabStrip.vue:66 @f6adf7e` - base class includes `px-4 py-2.5`; `:68` - selected adds `border-t-2 border-t-violet-400 bg-white text-black dark:bg-violet-500/30 dark:text-violet-300 dark:hover:text-violet-200` |
| C7 | CONFIRMED | `TabStrip.vue:70 @f6adf7e` - `tab.class ?? ''` in the class array |
| C8 | CONFIRMED | `TabStrip.vue:80-82 @f6adf7e` - `<div v-if="$slots.actions" ...><slot name="actions" /></div>`, sibling of the tablist div |
| C9 | CONFIRMED | `git grep border-t-violet-400 f6adf7e -- resources/js` - one hit, `TabStrip.vue:68`; same single hit @HEAD |
| C10 | CONFIRMED | `templates/edit.vue:652 @f6adf7e` - `<TabStrip v-model="mainTab" :tabs="mainTabs" />`; `templates/show.vue:320 @f6adf7e` - same element with a leading `v-if="canEdit"`; no `v-for="tab in mainTabs"` in either file @f6adf7e. @HEAD `mainTab` comes from `useAddressableTabs()` (OL-2609-072 C25) |
| C11 | CONFIRMED | `templates/show.vue:90-96 @f6adf7e` - `obs` push carries the stated `class` string; no `tab.key === 'obs'` in show.vue @f6adf7e; `show.vue:98 @HEAD` same string |
| C12 | CONFIRMED | `templates/show.vue:75` and `templates/edit.vue:224 @f6adf7e` - `const tabs: TabStripItem[]`; no `Array<{ key` in either file @f6adf7e |
| C13 | CONFIRMED | `dashboard/index.vue:93 @f6adf7e` - `grid grid-cols-1 gap-6 lg:grid-cols-5`; `:95` tabbed column `lg:col-span-3`; `:138` updates section `lg:col-span-2` |
| C14 | CONFIRMED | `dashboard/index.vue:41-43 @f6adf7e` - `overlays`/`Layers`, `alerts`/`Bell`, `activity`/`Newspaper`; panels `:114`, `:122`, `:130` are `v-show` divs after the closing `</TabStrip>`, not in it |
| C15 | CONFIRMED | `dashboard/index.vue:97-106 @f6adf7e` - `#actions` template binds `tabActions.viewHref` / `tabActions.createHref`; `tabActions` computed on `activeTab` at `:51` |
| C16 | CONFIRMED | no `WhatsNewCard` in `dashboard/index.vue @f6adf7e`; `resources/js/components/WhatsNewCard.vue` exists @f6adf7e and @HEAD |
| C17 | CONFIRMED | `dashboard/index.vue:32 @f6adf7e` - `whatsNew: WhatsNew;`; `DashboardController.php:60 @f6adf7e` - `'whatsNew' => WhatsNewController::props($user)`; controller unchanged @HEAD |
| C18 | CONFIRMED | `git grep components/ui/tabs f6adf7e -- resources/js` - only `pages/admin/users/show.vue:7`; same @HEAD |
| C19 | CONFIRMED | `dashboard/index.vue:118`, `:126 @f6adf7e` - `empty-message` passed; no length `v-if` on either panel; `TemplateCollection.vue:26 @f6adf7e` declares `emptyMessage` |
| C20 | CONFIRMED | `DashboardController.php @f6adf7e` - alert and static queries `->limit(10)` (were `limit(5)` in the diff), `mergeRecentEvents($user->id, 20)` (was 5) |
| C21 | UNVERIFIABLE | tagged [unverified] |

### Surface
Complete.

### Findings
- **F1** Unchanged line inaccurate - the third Unchanged line says the `useKeyboardShortcuts` registrations in `show.vue` and `edit.vue` bind number keys 1-7, but `resources/js/pages/templates/edit.vue:527-537 @f6adf7e` loops `i = 1..10` and binds `0` to the tenth tab (only `show.vue:154 @f6adf7e` is 1-7); the "not in the diff" half is true. A later claim should restate the edit.vue range as 1-9 plus 0.

### Notes
- `WhatsNewCard` has no caller anywhere under `resources/js` @f6adf7e or @HEAD, so the card behaviours recorded in OL-2609-036 and OL-2609-037 cannot be reached from the UI, while `DashboardController::index()` still computes `whatsNew` on each load (C17). The claim discloses the removal (C16) but does not cite those IDs.
- OL-2609-072 (75daa5eb) later replaced the `ref` bound to `TabStrip` in all three pages with `useAddressableTabs()` and removed `obsFirst` from show.vue; its C25 discloses this.
- af89218c and bb728e31 (no claim) touch `templates/show.vue`, but only the public/private badge markup and imports. No symbol this claim names is affected.
- No [test] claims; no tests were run.
