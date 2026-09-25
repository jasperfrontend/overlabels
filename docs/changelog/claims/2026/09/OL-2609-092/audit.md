## Audit of OL-2609-092 - fix(controls): a resolved expression dependency lands under the key its dependents read

**Audited:** 2026-09-25
**Commit:** 7d1dc5318c634ab3f44de90948b94d2ee9e05526
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/Controls/ExpressionControlHydrator.php:191 @7d1dc53` - `$data['c:'.$control->tagIdentifier()] = $value;` is the only assignment into `$data` in `evaluate()` (:152-192); `:180` only passes `$data` to the engine. File unchanged @HEAD |
| C2 | CONFIRMED | `app/Listeners/RecomputeExpressionControls.php:159 @7d1dc53` - `$data['c:'.$expr->tagIdentifier()] = $newValue;` is the only assignment into `$data` in `walk()` (:117-182). File unchanged @HEAD |
| C3 | CONFIRMED | `RecomputeExpressionControls.php:180 @7d1dc53` - `$this->walk($expr->tagIdentifier(), ...)`; `app/Models/OverlayControl.php:429-439 @7d1dc53` - `extractExpressionDependencies()` stores `c.<key>` as bare `<key>` and `c.<a>.<b>` as `<a>:<b>`, which equals `tagIdentifier()` (:312) for a non-managed row and for a source-managed non-recipe row. Unchanged @HEAD |
| C4 | CONFIRMED | `RecomputeExpressionControls.php:165-167 @7d1dc53` - `ControlValueUpdated::dispatch($overlaySlug, $expr->broadcastKey(), ...)`; the diff does not touch that line. Unchanged @HEAD |
| C5 | CONTRADICTED | Compound. First half is false as written: `app/Models/OverlayControl.php:281-287 @7d1dc53` - `broadcastKey()` returns `<recipe>:<instance>:<key>` whenever `recipe_instance_id` resolves, before the `source` check at :289, while `tagIdentifier()` (:312) returns bare `key` when not `source_managed`; so `source` null + `source_managed` false + `recipe_instance_id` set gives two different strings. The only control create site that sets `recipe_instance_id` (`app/Services/Recipes/RecipeInstaller.php:199-211 @7d1dc53`) sets `source_managed => true`, so no code path creates that shape. Second half ("every row shape that exists on prod today") is a prod observation, not checkable in-repo, under a [code] tag. Unchanged @HEAD |
| C6 | CONFIRMED | `tests/Feature/ExpressionControlHydrationTest.php:307-329 @7d1dc53` - `mid` is `source => 'user'`, `source_managed => false`; asserts `$seenByTop['c:mid']` is `'5'` (the faked fresh value, stored value `'1'`) and no key `c:user:mid` (:327-328). `php artisan test --filter='lands under the key its dependents read'` @HEAD: 2 passed (7 assertions) |
| C7 | CONFIRMED | `ExpressionControlHydrationTest.php:331-366 @7d1dc53` - `seed` counter (source null), sourced `mid` = `c.seed + 1`, `top` = `c.mid + 1`; drives `RecomputeExpressionControls::handle()` (:359) and asserts `top` was evaluated (`not->toBeNull()`), `c:mid` is `'5'` and no `c:user:mid` (:363-365). Passed in the same run; whole file `--filter=ExpressionControlHydrationTest` @HEAD: 14 passed |
| C8 | UNVERIFIABLE | tagged [unverified] |

### Surface
Complete.

### Findings
- **F1** contradicted claim (C5) - `app/Models/OverlayControl.php:281-287 @7d1dc53` (same @HEAD): `broadcastKey()` checks `recipe_instance_id` before `source`, so "source null => tagIdentifier() equals broadcastKey()" is false for a non-managed recipe-instance row; no create site makes that row (`RecipeInstaller.php:210` sets `source_managed => true`), so the reader should restate the precondition as "source null and no `recipe_instance_id`, or `source_managed` true" in a new claim (OL-2609-060 C23 already named that shape).
- **F2** mistagged compound (C5) - the clause "every row shape that exists on prod today is unaffected" is a prod observation carried under a [code] tag; split it into its own `[unverified]` claim.

### Notes
- Unchanged line 3 (`ExpressionDataContext.php:15` docblock) was accurate @7d1dc53 and has since been changed by 1473cca4 (OL-2609-093), which now says `"c:<tagIdentifier>"`.
- Unchanged lines 1-2 hold: `ExpressionDataContext.php` and `OverlayControl.php` are not in `git show --stat 7d1dc53`; `ExpressionDataContext.php:47 @7d1dc53` keys by `tagIdentifier()`.
- Outside this diff: `RecomputeExpressionControls::handle()` (:79 @7d1dc53) still enters `walk()` with `$event->key`, and every `ControlValueUpdated` dispatch site passes `broadcastKey()` (e.g. `OverlayControlController.php:141 @7d1dc53`), so a sourced, non-managed NON-expression control's change would look up `user:<key>` in `config->dependencies`, which holds `<key>`. C7's `seed` has `source` null, so the test does not reach that path.
- OL-2609-060's remedy.md (OL-2609-094) resolves its audit F1 by citing this claim's C1 and C2.
