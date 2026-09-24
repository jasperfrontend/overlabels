## Audit of OL-2609-133 - docs(claims): record the PUT preservation test that closes the lists/* gap

**Audited:** 2026-09-24
**Commit:** 33aba977d650bcb5f0853c447043c74d99e9d97a
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `tests/Feature/ListControllerTest.php:160-176 @33aba977` - `put("/lists/{$list->id}", ['items' => ['Pepperoni', 'Mushroom', '', 'Mushroom', ' ']])`, then `expect(ListItems::values($list->items))->toBe([...same array])`; `php artisan test tests/Feature/ListControllerTest.php --filter="update preserves exactly what was sent"` - 1 passed (2 assertions). HEAD is `33aba977` |
| C2 | CONFIRMED | POST `/lists`: `ListControllerTest.php:103-123 @33aba977` (empty, duplicate, `' '`) and `:144-155 @33aba977` (`'  spaces  '`); PUT `/lists/{list}`: `:160-176 @33aba977` (empty, duplicate, `' '`) |
| C3 | CONFIRMED | `bootstrap/app.php:47,50 @33aba977` - both exemptions are `$request->is('lists', 'lists/*')`; `routes/web.php:628 @33aba977` - `Route::put('/{list}', ...)` inside `Route::prefix('lists')` (`:613`), so the C1 test's PUT path matches `lists/*` and not `lists` |
| C4 | CONFIRMED | `git show --stat afbacf92` - one path, `tests/Feature/ListControllerTest.php`, 18 insertions; its message contains no `Changelog:` line (`grep -c Changelog` = 0) |

### Surface
Complete.

### Findings
None.

### Notes
- Diff is `docs/changelog/claims/2026/09/OL-2609-132/remedy.md` (listed) plus this claim file (exempt). The remedy's single F1 RECORD row matches the remedy option `OL-2609-132/audit.md` F1 offered ("Record the new coverage in a claim citing OL-2609-132 C1, C2 and OL-2609-129 audit F1").
- C3 asserts the test exercises the `lists/*` pattern by route match only; the `afbacf92` message says it was verified to fail with that pattern removed, which this audit did not re-run (it would require editing `bootstrap/app.php`).
