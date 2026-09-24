## Audit of OL-2609-132 - docs(claims): restate what OL-2609-129 C5 and C8 actually hold

**Audited:** 2026-09-24
**Commit:** 044c1bbd365847dc5dfc0cff0726a4e96b351f01
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `tests/Feature/ListControllerTest.php:103-123 @044c1bbd` - POST `/lists` with `['Pepperoni', 'Mushroom', '', 'Mushroom', ' ']`, asserts the stored items are identical; `:144-155 @044c1bbd` - POST `/lists`, asserts `'  spaces  '` survives; every PUT test (`:160-321 @044c1bbd`) sends only plain values such as `['b', 'c']`; `bootstrap/app.php:47,50 @044c1bbd` - `$request->is('lists', 'lists/*')`. `php artisan test tests/Feature/ListControllerTest.php --filter=...` - both named tests pass. @HEAD the "only on POST" half is false: `ListControllerTest.php:160 @HEAD` adds a PUT preservation test (`afbacf92`, no claim - see F1) |
| C2 | CONFIRMED | No PUT in `tests/Feature/ListControllerTest.php @044c1bbd` sends an empty, duplicate or whitespace-padded item (PUT bodies at :167, :189, :203, :213, :249, :281, :299, :303, :315). @HEAD false: `ListControllerTest.php:160-176 @HEAD` - "update preserves exactly what was sent, so the lists/* middleware exemption holds too" PUTs `['Pepperoni', 'Mushroom', '', 'Mushroom', ' ']` and asserts it verbatim; passes (`afbacf92`, no claim - see F1) |
| C3 | CONFIRMED | `docs/changelog/claims/2026/09/OL-2609-129/claim.md:41 @290db402` carries the quoted C8 text verbatim; `git grep -n "/dashboard/lists" 290db402 -- app routes bootstrap resources/js resources/help tests` returns `routes/web.php:214,215` and `tests/Feature/ListControllerTest.php:41,42,43` |
| C4 | CONFIRMED | `git show 276d3ab4` - one-line in-place replacement of C8 in `OL-2609-129/claim.md`, text as C4 describes; the same `git grep` at HEAD returns exactly `routes/web.php:214,215 @HEAD` (both `Route::permanentRedirect`) and `tests/Feature/ListControllerTest.php:41,42,43 @HEAD` |

### Surface
Complete.

### Findings
- **F1** changed without a claim - `afbacf92` (after this commit) added "update preserves exactly what was sent, so the lists/* middleware exemption holds too" at `tests/Feature/ListControllerTest.php:160 @HEAD`, which makes C1's "asserted only on POST `/lists`" and C2's "the `lists/*` half ... has no test coverage" false @HEAD, and no claim records it (the commit is tests-only and exempt from the claim rule, so it carries no trailer); a reader of this claim at HEAD is told a gap exists that is closed. Record the new coverage in a claim citing OL-2609-132 C1, C2 and OL-2609-129 audit F1, or accept the drift knowingly.

### Notes
- Ran `php artisan test tests/Feature/ListControllerTest.php` filtered to the two tests C1 names plus the `afbacf92` PUT test: 3 passed (10 assertions).
- Read against `OL-2609-129/audit.md` F1 and F2: both RECORD lines in `OL-2609-129/remedy.md @044c1bbd` restate what the findings describe; F1 offered "record the narrower truth ... or add a PUT preservation test", and `afbacf92` later did the second as well.
- C1 is tagged [test] but its "only" half is a negative statement about the file that running tests cannot establish; it was checked by reading the file.
