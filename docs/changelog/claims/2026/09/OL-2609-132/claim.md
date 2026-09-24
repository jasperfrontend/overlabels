## OL-2609-132 - docs(claims): restate what OL-2609-129 C5 and C8 actually hold

Remedies OL-2609-129 audit F1, F2.

**Shipped:** 2026-09-24
**Commit:** `git log --grep=OL-2609-132`

### Surface
- `docs/changelog/claims/2026/09/OL-2609-129/remedy.md` - new file, the remedy table for the OL-2609-129 audit

### Claims
- **C1** [test] In `tests/Feature/ListControllerTest.php`, empty-line, duplicate and whitespace preservation is asserted only on POST `/lists`, by "store creates a user-authored list preserving exactly what was sent" and "store strips NUL bytes but preserves everything else verbatim"; those tests exercise the `lists` pattern of the `bootstrap/app.php` exemptions and not the `lists/*` pattern (corrects OL-2609-129 C5, audit F1).
- **C2** [code] No test in `tests/Feature/ListControllerTest.php` sends an empty, duplicate or whitespace-padded item in a PUT to `/lists/{list}`, so the `lists/*` half of the `TrimStrings` and `ConvertEmptyStringsToNull` exemptions in `bootstrap/app.php` has no test coverage (corrects OL-2609-129 C5, audit F1).
- **C3** [code] As shipped in commit `290db402`, OL-2609-129 C8 read "No file under `app/`, `routes/`, `bootstrap/`, `resources/js/`, `resources/help/` or `tests/` contains the string `/dashboard/lists`.", which was false at that commit: `routes/web.php:214-215` and `tests/Feature/ListControllerTest.php:41-43` contain it (corrects OL-2609-129 C8, audit F2).
- **C4** [code] Commit `276d3ab4` rewrote OL-2609-129 C8 in place inside the shipped `claim.md`; the rewritten text, that `/dashboard/lists` occurs under those six directories only in the two `Route::permanentRedirect` calls at `routes/web.php:214-215` and the redirect test at `tests/Feature/ListControllerTest.php:41-43`, is true of the tree at HEAD (records OL-2609-129 C8, audit F2).
