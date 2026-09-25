## Audit of OL-2609-094 - fix(controls): the two remaining inline tag-key computations route through tagIdentifier()

**Audited:** 2026-09-25
**Commit:** a2b7e77fb1fe8138d0f4c316291d1e203a24491b
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Http/Controllers/OverlayTemplateController.php:689 @a2b7e77` - `'key' => $control->tagIdentifier(),` inside the `isRandom()` / `$randomControls[]` block; same @HEAD at :690 (OL-2609-119 added `$webfonts` lines around it, entry unchanged) |
| C2 | CONFIRMED | `app/Http/Controllers/Settings/BotCommandsController.php:200 @a2b7e77` - `->map(fn (OverlayControl $c) => $c->tagIdentifier())` in `availableControlKeys()`; same @HEAD :200 |
| C3 | CONFIRMED | Multiline search for `source_managed\s*\?\s*\$\w+->broadcastKey\(\)\s*:\s*\$\w+->key\b` over `git archive a2b7e77 app` returns one hit, `app/Models/OverlayControl.php:312 @a2b7e77`, the body of `tagIdentifier()`; a looser `source_managed\s*\?` search over `app/` @HEAD also returns only `OverlayControl.php:312` |
| C4 | CONFIRMED | `tests/Unit/TagIdentifierCallSitesTest.php @a2b7e77` - test named as claimed walks `File::allFiles(app_path())`, skips non-`.php`, `expect($matches)->toBe(1, ...)` for `Models/OverlayControl.php`, collects any other file with a match and `expect($offenders)->toBe([])`. `php artisan test --filter=TagIdentifierCallSitesTest` @HEAD: 1 passed (2 assertions) |
| C5 | UNVERIFIABLE | tagged [unverified]; a fail-first run against a tree that no longer exists, correctly tagged |
| C6 | CONFIRMED | `app/Services/Controls/ExpressionDataContext.php:111 @a2b7e77` - `Log::warning('[expression-context] Twitch data fetch failed; skipping t-tags'` inside `addTwitchTagData()` (:76); `app/Listeners/RecomputeExpressionControls.php:258 @899f7a97^` - `'[recompute-expression] Twitch data fetch failed; ...'` inside `addTwitchTagData()` (:223); `RecomputeExpressionControls.php:120 @a2b7e77` - `'[recompute-expression] max cascade depth reached'` inside `walk()` (:117). No later commit touches either file |
| C7 | CONFIRMED | `app/Models/OverlayControl.php:310-313 @a2b7e77` - `tagIdentifier()` body is exactly `return $this->source_managed ? $this->broadcastKey() : $this->key;`, the expression both removed hunks inlined; same @HEAD |

### Surface
Complete. `git show --stat a2b7e77` lists five paths; the claim file is exempt and the other four (`OverlayTemplateController.php`, `BotCommandsController.php`, `tests/Unit/TagIdentifierCallSitesTest.php`, `OL-2609-060/remedy.md`) are all in Surface. No phantom paths.

### Findings
None.

### Notes
- Unchanged line 1 holds: `$expressionsByKey[$control->broadcastKey()]` is at `OverlayTemplateController.php:681 @a2b7e77`, outside the only hunk (:686-694).
- Unchanged line 3 holds: `ExpressionControlHydrator.php:191 @a2b7e77` writes `'c:'.$control->tagIdentifier()` and `RecomputeExpressionControls.php:159,180 @a2b7e77` use `tagIdentifier()`, as OL-2609-092 C1-C3 state, so the remedy's F1 SKIPPED is consistent with the 060 audit's F1.
- `OverlayTemplateController.php` changed after this commit only in c6e75409 (OL-2609-119), which adds a third `tagIdentifier()` call (`$webfonts[]`, :703 @HEAD) and leaves C1's line intact.
- C4's test would pass vacuously on the `OverlayControl.php` half if that file moved out of `app/Models/`; the claim does not assert otherwise.
