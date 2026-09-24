## Audit of OL-2609-001 - feat(templates): filter alerts by event assignment

**Audited:** 2026-09-25
**Commit:** 7c44972a567bb1bb845e1e84d7e7cb25257665d5
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Http/Controllers/OverlayTemplateController.php:83-88 @7c44972a` - `when(type === 'alert' && assignment === 'assigned')` wraps `whereHas('eventMappings', user_id = $request->user()->id)` `orWhereHas('externalEventMappings', same)` in one `where()`; same at `:86-91 @HEAD` (line shift only) |
| C2 | CONFIRMED | `OverlayTemplateController.php:89-92 @7c44972a` - `whereDoesntHave('eventMappings', user_id)` then `whereDoesntHave('externalEventMappings', user_id)`; same at `:92-95 @HEAD` |
| C3 | CONFIRMED | `OverlayTemplateController.php:83,89 @7c44972a` - both `when()` conditions begin `$request->input('type') === 'alert' &&`; same at `:86,92 @HEAD` |
| C4 | CONFIRMED | `OverlayTemplateController.php:114 @7c44972a` - `$request->only(['filter', 'search', 'type', 'assignment', 'sort', 'direction'])`; same at `:117 @HEAD` |
| C5 | CONFIRMED | `resources/js/pages/templates/index.vue:61-62 @7c44972a` - `if (filters.value.type === 'alert' && filters.value.assignment) params.assignment = ...`; `:213 @7c44972a` - Assignment `FilterSelect` has `v-if="filters.type === 'alert'"`; unchanged @HEAD (only later diff in the file is `data-tab-start` on `TemplateCollection`, a98c6e05) |
| C6 | CONFIRMED | `resources/js/components/FilterBar.vue:9 @7c44972a` - `class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5"`, no condition; file has no `<script>` block, so no props; identical @HEAD |
| C7 | CONTRADICTED | `tests/Feature/TemplateIndexAssignmentFilterTest.php @7c44972a` (identical @HEAD) has 4 tests. Test 1 covers C1 with one Twitch and one external mapping. Test 2 ("unassigned ...") creates only an `EventTemplateMapping`; no test asserts that an alert with only an `ExternalEventTemplateMapping` is excluded from `assignment=unassigned`, so the `externalEventMappings` half of C2 is unasserted. Test 3 covers another user's Twitch mapping, as C7 states. Test 4 requests `/templates?filter=mine&assignment=assigned` with NO `type` param and asserts one static template is listed; no test sends `type=static` or `type=block`, and no block template is created. Not run: see Notes |
| C8 | UNVERIFIABLE | tagged [unverified]; a fail-first run against a pre-fix tree, correctly tagged |

### Surface
Complete.

### Findings
- **F1** test narrower than claim - `TemplateIndexAssignmentFilterTest.php` test 2 `@7c44972a` only maps a Twitch event, so C7's "asserts C2" does not cover the `whereDoesntHave('externalEventMappings', ...)` clause at `OverlayTemplateController.php:91 @7c44972a`; add a case with an external-only mapping that must be absent from `assignment=unassigned`, or restate C7.
- **F2** test narrower than claim - `TemplateIndexAssignmentFilterTest.php` test 4 `@7c44972a` exercises the `type === 'alert'` gate only with the `type` param absent, while C3 (which C7 says it asserts) speaks of static and block listings; add `type=static` and `type=block` requests (with a block template), or restate C7 as "the param is ignored when no type filter is set".

### Notes
- C7 was not run: this worktree has no `vendor/` and no `.env`, so `php artisan test --filter=TemplateIndexAssignmentFilterTest` fails at `artisan:10` (missing `vendor/autoload.php`). The C7 verdict rests on the test's content, not its result; whether the 4 tests pass is unconfirmed by this audit.
- `claim.md` was edited after ship by ba6feb34 (Unchanged section rewritten into four bullets); OL-2609-002 discloses this in its Surface, C4 and Risk. This audit judged the Unchanged lines as they stand @HEAD.
- Unchanged lines checked against the diff: the eager-load closures (`OverlayTemplateController.php:101-106 @7c44972a`), the search/type/ownership/sort clauses, `TemplateCollection.vue`, `dashboard/recents.vue` and `dashboard/lists/index.vue` are all outside the diff hunks; the latter two render `FilterBar` @7c44972a (`recents.vue:377`, `lists/index.vue:309`).
- The controller and `index.vue` were touched by later commits (e.g. 6abb44b8, a98c6e05); none of them altered the assignment clauses, the echoed filters, `buildQuery()` or the Assignment field.
