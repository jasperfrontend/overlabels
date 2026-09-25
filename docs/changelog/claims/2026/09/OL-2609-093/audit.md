## Audit of OL-2609-093 - docs(controls): ExpressionDataContext docblock names the key it actually uses

**Audited:** 2026-09-25
**Commit:** 1473cca4f40137debd1db75c5cd942277f0bf935
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/Controls/ExpressionDataContext.php:15 @1473cca` - `* Keyed "c:<tagIdentifier>" for every control the user owns`; `:47 @1473cca` - `$key = 'c:'.$control->tagIdentifier();`. File unchanged @HEAD (`git log 1473cca..HEAD -- <path>` is empty) |
| C2 (half 1: no line outside the docblock changed) | CONFIRMED | `git show 1473cca -- app/Services/Controls/ExpressionDataContext.php` - one hunk `@@ -12,7 +12,7 @@`, one line replaced, `:15` inside the class docblock (:12-24) |
| C2 (half 2: `git show` for this commit has one hunk) | CONTRADICTED | `git show --format= 1473cca \| grep -c '^@@'` prints `2`: the PHP hunk plus `@@ -0,0 +1,13 @@` creating `docs/changelog/claims/2026/09/OL-2609-093/claim.md`. One hunk is true only when restricted to `ExpressionDataContext.php` |

### Surface
Complete.

### Findings
- **F1** compound claim, halves differ (C2) - the claim says `git show` for 1473cca has one hunk, but it has two (the second creates this claim's own `claim.md`); the PHP half holds. A new claim should restate C2 as "`git show 1473cca -- app/Services/Controls/ExpressionDataContext.php` has one hunk", citing OL-2609-093 C2 inline.

### Notes
- The change cites OL-2609-060 (audit note on `ExpressionDataContext.php:15`) and OL-2609-092 (Unchanged line 3, which left the docblock for a docs pass) inline, so reversing that Unchanged line is disclosed, not a contradiction.
- No tests were run: both claims are `[code]` and name no test.
