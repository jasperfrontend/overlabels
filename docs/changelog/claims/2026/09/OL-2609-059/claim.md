## OL-2609-059 - feat(settings): one filter box, extracted from GroupedCollection and fitted to five list pages

**Shipped:** 2026-09-10
**Commit:** `git log --grep=OL-2609-059`

### Surface
- `resources/js/components/CollectionFilter.vue` - new file; the magnifier + input + toolbar slot, lifted out of `GroupedCollection`
- `resources/js/composables/useCollectionFilter.ts` - new file; query ref and the narrowing it drives
- `resources/js/composables/useCollectionFilter.test.ts` - new file; 8 vitest tests for the composable
- `resources/js/components/GroupedCollection.vue` - renders `CollectionFilter` in place of its own inline filter row
- `resources/js/pages/settings/bot/commands/Index.vue` - filter over command name and reply body
- `resources/js/pages/settings/bot/aliases/Index.vue` - filter over both sides of the rewrite
- `resources/js/pages/settings/triggers/index.vue` - filter over every mapping row; sectioning regroups what survives
- `resources/js/pages/settings/integrations/index.vue` - filter over the external services list
- `resources/js/pages/overlaytokens/index.vue` - filter over token name and prefix

### Claims
- **C1** [code] `CollectionFilter.vue` renders a `Search` icon from `@lucide/vue` and an `input` bound to a `defineModel<string>`, wrapped in `div.flex.items-center.gap-3`, with a `toolbar` slot after the input. The input carries `class="input-border w-full py-1.5 pr-2.5 pl-8 text-sm"` - byte-identical to the classes `GroupedCollection` used before this change.
- **C2** [code] `CollectionFilter.vue` sets `:aria-label="inputPlaceholder"` on the input. The block it was lifted from had no accessible name.
- **C3** [code] `GroupedCollection.vue` no longer imports `Search`, and no longer contains an `input` element of its own. It passes its existing `inputPlaceholder` computed to `CollectionFilter` and forwards its own `toolbar` slot (with its `items` scope) into the child's `toolbar` slot.
- **C4** [code] `useCollectionFilter(items, matches)` returns `query` (raw, as typed), `normalized` (lowercased and trimmed), `filtering` (`normalized !== ''`) and `filtered`. When `filtering` is false, `filtered` returns the source array without calling `matches`.
- **C5** [code] `matches` is called with the normalized query, the same contract as `GroupedCollection`'s `matches` prop, which also lowercases and trims before calling it.
- **C6** [code] `useCollectionFilter` takes items as a getter (`() => TItem[]`) and reads it inside the `filtered` computed, so a page whose props change re-filters rather than holding a snapshot taken at setup.
- **C7** [test] `useCollectionFilter.test.ts` asserts all of C4, C5 and C6, including that a whitespace-only query is not a filter and that `query` is left exactly as typed.
- **C8** [code] The bot commands predicate matches `` `!${command.command}` `` and `command.reply`, both lowercased, so a word appearing only in the reply body finds its command and the displayed `!name` form matches as typed.
- **C9** [code] The bot aliases predicate matches `` `!${alias.command}` `` and `` `!${alias.target_template}` ``, so an alias is found by what it rewrites to as well as by its own name.
- **C10** [code] `triggers/index.vue` runs one `useCollectionFilter` over `[...twitchMappings, ...externalMappings]`; the `sections` computed groups `filtered` rather than the props, and drops sections with no rows only while `filtering` is true.
- **C11** [code] `triggers/index.vue` narrows `unassignedEventTypes` through the same `normalized` query into an `unassigned` computed, and the template iterates `unassigned`.
- **C12** [code] Every one of the five pages renders `CollectionFilter` only when its unfiltered collection is non-empty, so an account with nothing to filter is not shown a filter box.
- **C13** [code] Each of the five pages renders a distinct "no X match" line when the filter empties a non-empty collection; on `overlaytokens/index.vue` this goes through `CollectionList`'s `empty-message` via the `listEmptyMessage` computed rather than a second empty state.
- **C14** [unverified] All five pages were driven in Chrome against local data: `mention`, `!steve` and `kangaroo` on bot commands; `!list` on aliases; `cheer` and `zzzz` on triggers; `chat` on integrations; `chat tower` on tokens. `/settings/controls` was checked in the same pass and still filters, counts and collapses.

### Unchanged
- `FilterBar.vue`, `FilterSearchInput.vue`, `FilterSelect.vue` and `useSearchFilters.ts` are the URL-backed server-search stack used by `/templates`, `/dashboard/recents` and `/dashboard/lists`. They answer a different question - reaching rows the page does not hold - and none of the four is in the diff.
- `GroupedCollection`'s filtering, count line, expand/collapse-all and per-`storageKey` persistence are not in the diff; only the markup of its filter row moved. `ControlsManager.vue`, `TemplateTagsList.vue` and the `/tags` page consume it and are likewise untouched.
- `CollectionList.vue` is not in the diff. The three pages that use it hand it an already-narrowed array.
- No controller, route or test on the PHP side is in the diff: this filters rows the page already received, so nothing was added to any Inertia payload.

### Risk
None. Every page gains an input; no existing control, route or payload changes.
