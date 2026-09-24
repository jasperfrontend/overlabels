## OL-2609-133 - docs(claims): record the PUT preservation test that closes the lists/* gap

Remedies OL-2609-132 audit F1.

**Shipped:** 2026-09-24
**Commit:** `git log --grep=OL-2609-133`

### Surface
- `docs/changelog/claims/2026/09/OL-2609-132/remedy.md` - new file, the remedy table for the OL-2609-132 audit

### Claims
- **C1** [test] `tests/Feature/ListControllerTest.php` "update preserves exactly what was sent, so the lists/* middleware exemption holds too" PUTs `['Pepperoni', 'Mushroom', '', 'Mushroom', ' ']` to `/lists/{list}` and asserts the stored items are exactly that array (corrects OL-2609-132 C1, audit F1).
- **C2** [code] Empty-line, duplicate and whitespace preservation in `tests/Feature/ListControllerTest.php` is asserted on both POST `/lists` and PUT `/lists/{list}`, so OL-2609-132 C1's "asserted only on POST `/lists`" no longer holds at HEAD (corrects OL-2609-132 C1, audit F1).
- **C3** [code] The `lists/*` pattern of the `TrimStrings` and `ConvertEmptyStringsToNull` exemptions at `bootstrap/app.php:47,50` is exercised by the test in C1, so OL-2609-132 C2's "has no test coverage" no longer holds at HEAD (corrects OL-2609-132 C2, audit F1; closes the gap named in OL-2609-129 audit F1).
- **C4** [code] The test in C1 was added by commit `afbacf92`, which touches only `tests/Feature/ListControllerTest.php` and carries no `Changelog:` trailer (records OL-2609-132 audit F1).
