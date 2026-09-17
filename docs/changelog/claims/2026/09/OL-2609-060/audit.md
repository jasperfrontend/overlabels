## Audit of OL-2609-060 - fix(controls): Expression Controls resolve in bot replies, alert messages and the living title

**Audited:** 2026-09-17
**Commit:** 899f7a97c218490bead81aafbfb1e25621d248ea
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Models/OverlayControl.php:422 @899f7a97` - `preg_match_all('/\bc\.([a-z]...` and the bracket form at :438 match `c.`/`c[` only; no `t.` branch. Same body @HEAD (:424, shifted by OL-2609-066) |
| C2 | CONFIRMED | `app/Listeners/RecomputeExpressionControls.php:128-130 @899f7a97` - `->whereJsonContains('config->dependencies', $key)`; file unchanged @HEAD |
| C3 | CONFIRMED | `app/Http/Controllers/OverlayControlController.php:120-131 @899f7a97` - `createForTemplate(..., 'value' => null, ...)`, no evaluate call; `update()` :271 excludes `expression` from value writes and :363 aborts 403; the only writer is `RecomputeExpressionControls.php:158`, reached only through `walk()` (C2). File unchanged @HEAD |
| C4 | CONFIRMED | `app/Models/OverlayControl.php:189-200 @899f7a97` - branches `timer`, `isRandom()`, then `return $this->value ?? ''`; not in the diff; same @HEAD (:196) |
| C5 | CONFIRMED | `app/Services/Controls/ExpressionControlHydrator.php:74-76 @899f7a97` - `if ($source === null \|\| ! str_contains($source, 'c:')) { return $controls; }` before any query; same @HEAD |
| C6 | CONFIRMED | `ExpressionControlHydrator.php:131-141 @899f7a97` - `needed()` is `str_contains($source, 'c:'.$identifier)` over `expressionControls()` keys (:113-118, `where('type', 'expression')`); same @HEAD |
| C7 | CONTRADICTED | Depth-first half holds: `ExpressionControlHydrator.php:176-180 @899f7a97` recurses into `config['dependencies']` before `:182` evaluates. The key half is false: `:189 @899f7a97` writes `$data['c:'.$control->broadcastKey()] = $value;`, not `tagIdentifier()`. Same line @HEAD |
| C8 | CONFIRMED | `ExpressionControlHydrator.php:172 @899f7a97` seeds `$evaluated[$identifier]` with `(string) ($control->value ?? '')`; `:184-186` returns on `null` before overwriting; same @HEAD |
| C9 | CONFIRMED | `app/Services/Bot/BotCommandResolver.php:117-119 @899f7a97` - `if (! $dryRun) { $controls = $this->expressions->hydrate(...); }`; same @HEAD |
| C10 | CONFIRMED | `app/Services/Messages/AlertMessageRenderer.php:149 @899f7a97` - `hydrate()` inside `resolveWith()`; `renderAlert()` calls it at :113-114 and `resolve()` at :131; same @HEAD |
| C11 | CONFIRMED | `git show --stat 899f7a97` does not list `app/Services/LivingTitleService.php`; `LivingTitleService.php:74 @899f7a97` injects `BotCommandResolver`, `:135` calls `$this->resolver->resolve($user, $template)`; file unchanged @HEAD |
| C12 | CONFIRMED | `app/Services/Controls/ExpressionDataContext.php:58 @899f7a97` - `$data[$key.'_at'] = (string) ($control->updated_at ?? $control->created_at)?->timestamp`; `OverlayTemplateController.php:666-668 @899f7a97` - `updated_at ? updated_at->timestamp : created_at->timestamp`; both same @HEAD |
| C13 | CONFIRMED | `ExpressionDataContext.php:40 @899f7a97` - `->get(['id', 'key', 'source', 'source_managed', 'value', 'type', 'config', 'recipe_instance_id', 'created_at', 'updated_at'])`; same @HEAD |
| C14 | CONFIRMED | `app/Models/OverlayControl.php:305 @899f7a97` - `return $this->source_managed ? $this->broadcastKey() : $this->key;`; @HEAD :312, body identical (OL-2609-066 added the `color` type above it) |
| C15 | CONTRADICTED | First half holds: `ControlSnapshot.php:46`, `ExpressionControlHydrator.php:118`, `ExpressionDataContext.php:47`, `OverlayTemplateController.php:663`, all `tagIdentifier()` @899f7a97. Second half is false: `OverlayTemplateController.php:689-691 @899f7a97` (`randomControls` key) and `app/Http/Controllers/Settings/BotCommandsController.php:200 @899f7a97` (`availableControlKeys()`) both still compute `source_managed ? broadcastKey() : key` inline. Both unchanged @HEAD |
| C16 | CONFIRMED | `tests/Feature/ExpressionControlHydrationTest.php:56, :71, :81 @899f7a97` - `BotCommandResolver::resolve()`, `LivingTitleService::renderTemplate()`, `AlertMessageRenderer::render()` each expected to yield `5` from a `t.subscribers_total + 1` control. `php artisan test --filter=ExpressionControlHydrationTest` @HEAD: 12 passed |
| C17 | CONFIRMED | `:101-110 @899f7a97` - `Http::assertNothingSent()` for `'no expressions here at all'`; `:149-160` - same under `resolve(..., [], true)`. Both pass @HEAD |
| C18 | CONFIRMED | `:190-191 @899f7a97` - `c:minutes_at` equals `$minutes->updated_at->timestamp`; `:221-222` - `c:kofi:donations_received_at` equals `$control->updated_at->timestamp`. Both pass @HEAD |
| C19 | CONFIRMED | `:244 @899f7a97` - `tagIdentifier()` is `goal` for `source => 'user', source_managed => false`; `:257` - sidecar data has key `c:goal`. Passes @HEAD |
| C20 | UNVERIFIABLE | tagged [unverified] |
| C21 | UNVERIFIABLE | tagged [unverified] |
| C22 | UNVERIFIABLE | tagged [unverified] |
| C23 | UNVERIFIABLE | tagged [unverified] |

### Surface
Complete. Nine paths in `git show --stat 899f7a97` besides the claim file and `docs/changelog/changelog-2026-09.md`; all nine listed. The test file holds 12 `test(` blocks, matching the Surface line.

### Findings
- **F1** contradicted claim (C7) - `app/Services/Controls/ExpressionControlHydrator.php:189 @899f7a97` (same @HEAD) writes a resolved dependency into the sidecar map under `c:<broadcastKey>`, while `ExpressionDataContext.php:47` keys the same map by `c:<tagIdentifier>`; for the row shape C19 tests (`source` set, `source_managed` false) a dependent expression would read the stale row value under `c:goal` and the fresh one under `c:user:goal`. Either change :189 to `tagIdentifier()` or amend C7.
- **F2** contradicted claim (C15) - `app/Http/Controllers/OverlayTemplateController.php:689-691` and `app/Http/Controllers/Settings/BotCommandsController.php:200`, both @899f7a97 and @HEAD, still compute `source_managed ? broadcastKey() : key` inline; the claim says no such site remains under `app/`. Either route both through `tagIdentifier()` or amend C15 to name them.
- **F3** scope - the log tag in the extracted `addTwitchTagData()` changed from `[recompute-expression]` (deleted `RecomputeExpressionControls.php` hunk @899f7a97^) to `[expression-context]` (`ExpressionDataContext.php:111 @899f7a97`); Surface describes the class as "extracted" and no claim mentions the rename. Anyone grepping logs for the old tag should know.

### Notes
- `ExpressionDataContext.php:15 @899f7a97` docblock still says the map is keyed `"c:<broadcastKey>"`; the code at :47 keys by `tagIdentifier()`. Comment only, not a finding.
- The C17 "names no expression control" test (`:101`) uses a source with no `c:` substring at all, so it exercises the `:74` short-circuit; no test covers a source that names a non-expression control and reaches `needed()`.
- `RecomputeExpressionControls.php:159 @899f7a97` writes cascaded values under `broadcastKey()` too, the same shape as F1; that line is not in this diff and the Unchanged section covers the cascade.
- `app/Models/OverlayControl.php` was touched after this commit by 5a1a4165 (OL-2609-066, `color` type); none of C1, C4 or C14's symbols changed, only line numbers.
