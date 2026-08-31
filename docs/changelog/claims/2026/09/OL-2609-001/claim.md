## OL-2609-001 - feat(templates): filter alerts by event assignment

**Shipped:** 2026-09-01
**Commit:** `git log --grep=OL-2609-001`

### Surface
- `app/Http/Controllers/OverlayTemplateController.php` - assignment clauses in `index()`, `assignment` added to the echoed filters
- `resources/js/pages/templates/index.vue` - Assignment `FilterSelect`, `assignment` in `FiltersShape`, `normalizeFilters()` and `buildQuery()`
- `resources/js/components/FilterBar.vue` - desktop grid fixed at five columns
- `tests/Feature/TemplateIndexAssignmentFilterTest.php` - new file

### Claims
- **C1** [code] `OverlayTemplateController::index()` with `type=alert&assignment=assigned` keeps only templates with a `whereHas` match on `eventMappings` or `externalEventMappings` scoped to the requesting user's id.
- **C2** [code] `index()` with `type=alert&assignment=unassigned` applies `whereDoesntHave` on both relations, scoped the same way.
- **C3** [code] Both `assignment` clauses in `index()` also require `type === 'alert'`, so the param has no effect on static or block listings.
- **C4** [code] `assignment` is in the `$request->only()` list `index()` echoes back as `filters`.
- **C5** [code] `buildQuery()` in `templates/index.vue` emits `assignment` only when `filters.type === 'alert'` and the value is non-empty, and the Assignment `FilterSelect` renders under the same `v-if`.
- **C6** [code] `FilterBar.vue` uses `lg:grid-cols-5` unconditionally and takes no props, so field widths do not change when a fifth field appears.
- **C7** [test] `TemplateIndexAssignmentFilterTest` (4 tests) asserts C1 for both mapping kinds, C2, that another user's mapping counts as unassigned for the viewer, and C3.
- **C8** [unverified] The three behavior tests in C7 were run with the controller change stashed and failed; the C3 test passes either way by design.

### Unchanged
The eager loads of `eventMappings`/`externalEventMappings`, the ownership/type/search/sort filters, and `TemplateCollection` rendering. The other two `FilterBar` pages (`dashboard/recents.vue`, `dashboard/lists/index.vue`) pass nothing new.

### Risk
Pages sharing `FilterBar` with fewer than five fields (recents, lists) now render fields at one fifth of the row instead of one quarter at `lg` and up.
