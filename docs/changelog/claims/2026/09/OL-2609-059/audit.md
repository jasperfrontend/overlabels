## Audit of OL-2609-059 - feat(settings): one filter box, extracted from GroupedCollection and fitted to five list pages

**Audited:** 2026-09-25
**Commit:** 4ea399882f6ca445f40f11f26a8af3e4c1e82dec
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/components/CollectionFilter.vue:20,32,43,45,48,50 @4ea3998` - `Search` from `@lucide/vue`, `defineModel<string>`, `div.flex.items-center.gap-3`, input class `input-border w-full py-1.5 pr-2.5 pl-8 text-sm`, `<slot name="toolbar" />` after the input; the removed input in `GroupedCollection.vue @4ea3998^` carried the identical class string. File unchanged @HEAD |
| C2 | CONFIRMED | `CollectionFilter.vue:48 @4ea3998` - `:aria-label="inputPlaceholder"`; the removed `<input>` in `GroupedCollection.vue @4ea3998^` had no `aria-label` |
| C3 | CONFIRMED | `GroupedCollection.vue:18 @4ea3998` imports `ChevronRight, ChevronsUpDown, ChevronsDownUp` only; `:149-153` `<CollectionFilter v-model="searchQuery" :placeholder="inputPlaceholder">` with `<slot name="toolbar" :items="visibleItems" />` inside `#toolbar`; no `<input>` in the file; `inputPlaceholder` computed at `:98`. Unchanged @HEAD |
| C4 | CONFIRMED | `resources/js/composables/useCollectionFilter.ts:30-35 @4ea3998` - `normalized` = `toLowerCase().trim()`, `filtering` = `normalized.value !== ''`, `filtered` returns `items()` without calling `matches` when not filtering. Unchanged @HEAD |
| C5 | CONFIRMED | `useCollectionFilter.ts:33 @4ea3998` passes `normalized.value`; `GroupedCollection.vue:75,81 @4ea3998` passes `searchQuery.value.toLowerCase().trim()` to `props.matches` |
| C6 | CONFIRMED | `useCollectionFilter.ts:17,33 @4ea3998` - `items: () => TItem[]`, called inside the `filtered` computed |
| C7 | CONTRADICTED | `useCollectionFilter.test.ts @4ea3998` exists and `npm test -- resources/js/composables/useCollectionFilter.test.ts` passed 8/8 @HEAD (file unchanged). It asserts: `filtering` false on empty and whitespace-only queries, true on `kangaroo`; narrowing by name and by body; predicate receives `rules` for `'  RuLeS  '`; `query` stays `'  RuLeS '` and `normalized` is `rules`; re-filter when a `ref` source changes. It does NOT assert that `matches` is not called while `filtering` is false, nor that `filtered` returns the source array itself (the unfiltered cases check `toHaveLength(3)` only), nor anything about `GroupedCollection`'s `matches` contract (C5's second half) |
| C8 | CONFIRMED | `resources/js/pages/settings/bot/commands/Index.vue:41 @4ea3998` - `` `!${command.command}`.toLowerCase() `` or `command.reply.toLowerCase()`; unchanged @HEAD |
| C9 | CONFIRMED | `resources/js/pages/settings/bot/aliases/Index.vue:40 @4ea3998` - `` `!${alias.command}` `` or `` `!${alias.target_template}` ``, lowercased; unchanged @HEAD |
| C10 | CONFIRMED | `resources/js/pages/settings/triggers/index.vue:79 @4ea3998` source `[...props.twitchMappings, ...props.externalMappings]`; `:92` sections loop over `filtered.value`; `:116` drops empty sections only when `filtering.value`. Unchanged @HEAD |
| C11 | CONFIRMED | `triggers/index.vue:120-126 @4ea3998` - `unassigned` computed filters on `normalized.value`; `:242` `v-for="row in unassigned"` |
| C12 | CONFIRMED | `@4ea3998`: `overlaytokens/index.vue:176` `v-if="tokens.length > 0"`; `bot/aliases/Index.vue:84` `props.aliases.length > 0`; `bot/commands/Index.vue:101` `props.commands.length > 0`; `integrations/index.vue:402` `props.services.length > 0`; `triggers/index.vue:186` `totalAssigned > 0 \|\| unassignedEventTypes.length > 0`. @HEAD `integrations/index.vue` renders `CollectionFilter` with no `v-if` over a list that now always holds Twitch and bot rows (OL-2609-063, OL-2609-069) |
| C13 | CONFIRMED | `@4ea3998`: `bot/aliases/Index.vue:92`, `bot/commands/Index.vue:109`, `integrations/index.vue:404-405`, `triggers/index.vue:193` (via `nothingMatches` `:128`), `overlaytokens/index.vue:55,183` `listEmptyMessage` into `:empty-message` |
| C14 | UNVERIFIABLE | tagged [unverified] |

### Surface
Complete.

### Findings
- **F1** test narrower than claim - C7 says `useCollectionFilter.test.ts` asserts all of C4, but no case asserts that `matches` goes uncalled, or that the source array itself comes back, while `filtering` is false (the unfiltered cases only check `toHaveLength(3)`). Add a case with a spy predicate and an identity check on the empty query, or restate C7 in a new claim.
- **F2** scope - `resources/js/pages/overlaytokens/index.vue:184 @4ea3998` changes `empty-dashed` from always-on to `:empty-dashed="!filtering"`, so the tokens page's empty state stops being dashed while a filter is active. No claim or Surface line mentions this. Record it in a new claim.
- **F3** Unchanged line inaccurate - the `CollectionList.vue` line says "The three pages that use it hand it an already-narrowed array", but only two of the five pages use `CollectionList` @4ea3998 (`overlaytokens/index.vue:179`, `triggers/index.vue:201`). The part that says `CollectionList.vue` is not in the diff is true. Correct the count in a new claim.

### Notes
- `integrations/index.vue` @HEAD filters `IntegrationRow`s through `matchesIntegration()` from `utils/integrationRows.ts`, not the C12-era `ServiceInfo` predicate. The source was changed by OL-2609-063 (C6) and then OL-2609-069 (C13, C19). Both claims disclose it.
- The Unchanged lines about `FilterBar.vue`, `FilterSearchInput.vue`, `FilterSelect.vue`, `useSearchFilters.ts`, `ControlsManager.vue`, `TemplateTagsList.vue`, `CollectionList.vue` and PHP files all hold: none of them is in `git show --stat 4ea3998`.
- In `GroupedCollection.vue`, the diff touches only the import line and the filter-row markup.
