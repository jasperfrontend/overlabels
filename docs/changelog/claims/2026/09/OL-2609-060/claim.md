## OL-2609-060 - fix(controls): Expression Controls resolve in bot replies, alert messages and the living title

**Shipped:** 2026-09-10
**Commit:** `git log --grep=OL-2609-060`

### Surface
- `app/Services/Controls/ExpressionControlHydrator.php` - new file; evaluates the expression controls a template names, at read time
- `app/Services/Controls/ExpressionDataContext.php` - new file; the sidecar's data map, extracted from the listener, now carrying `_at` companions
- `app/Listeners/RecomputeExpressionControls.php` - context building removed in favour of `ExpressionDataContext`; constructor swapped
- `app/Services/Bot/BotCommandResolver.php` - hydrates controls before rendering; takes the hydrator
- `app/Services/Messages/AlertMessageRenderer.php` - same, in `resolveWith()`; gains a constructor
- `app/Models/OverlayControl.php` - new `tagIdentifier()`
- `app/Support/ControlSnapshot.php` - uses `tagIdentifier()`
- `app/Http/Controllers/OverlayTemplateController.php` - uses `tagIdentifier()` for the render payload's control keys
- `tests/Feature/ExpressionControlHydrationTest.php` - new file, 12 tests

### Claims
- **C1** [code] `OverlayControl::extractExpressionDependencies()` matches `c.` references only, so an expression of the form `t.subscribers_total + 1` yields an empty `dependencies` array.
- **C2** [code] `RecomputeExpressionControls::walk()` selects dependents with `whereJsonContains('config->dependencies', $key)`, so a control whose `dependencies` is empty is never recomputed by it.
- **C3** [code] `OverlayControlController::store()` creates an expression control with `'value' => null` and computes no initial value, so before this change a `t.`-only expression control's stored value stayed null for the life of the row.
- **C4** [code] `OverlayControl::resolveDisplayValue()` returns `$this->value ?? ''` for `type === 'expression'`; it has branches for `timer` and `random` only. It is unchanged by this diff.
- **C5** [code] `ExpressionControlHydrator::hydrate()` returns `$controls` unchanged, without querying, when the source contains no `c:` substring.
- **C6** [code] `hydrate()` evaluates only expression controls whose `c:<identifier>` appears in the source, resolved via `needed()`.
- **C7** [code] `hydrate()` resolves an expression control's expression-control dependencies depth-first before evaluating it, writing each result into the sidecar data map under `c:<tagIdentifier>`.
- **C8** [code] When `ExpressionEngineClient::evaluate()` returns null, `ExpressionControlHydrator::evaluate()` leaves the control's stored value in place rather than emptying it.
- **C9** [code] `BotCommandResolver::resolve()` calls `$this->expressions->hydrate()` only when `$dryRun` is false.
- **C10** [code] `AlertMessageRenderer::resolveWith()` calls `$this->expressions->hydrate()`, so both `renderAlert()` and `resolve()` are covered by one call site.
- **C11** [code] `LivingTitleService` is not in this diff; it renders through `BotCommandResolver::resolve()` and inherits the fix.
- **C12** [code] `ExpressionDataContext::for()` writes a `c:<tagIdentifier>_at` entry for every control, valued `(string) ($control->updated_at ?? $control->created_at)?->timestamp` - the same rule and fallback `OverlayTemplateController` uses for the overlay payload.
- **C13** [code] `ExpressionDataContext::for()`'s column list includes `source_managed`, `created_at` and `updated_at`, all three of which `tagIdentifier()` and C12 read.
- **C14** [code] `OverlayControl::tagIdentifier()` returns `broadcastKey()` when `source_managed`, and `key` otherwise.
- **C15** [code] `ControlSnapshot::for()`, `ExpressionControlHydrator::expressionControls()`, `ExpressionDataContext::for()` and `OverlayTemplateController`'s render query all derive their control key from `tagIdentifier()`. No other call site in `app/` computes `source_managed ? broadcastKey() : key` inline.
- **C16** [test] `ExpressionControlHydrationTest` asserts a `t.`-only expression control renders its evaluated value through `BotCommandResolver`, `LivingTitleService` and `AlertMessageRenderer`.
- **C17** [test] The same file asserts no HTTP request is made when the template names no expression control, and none under `dryRun`.
- **C18** [test] The same file asserts the `_at` companion arrives in the sidecar payload with the control's `updated_at` timestamp, for both a bare-key control and a service-managed one (`c:kofi:donations_received_at`).
- **C19** [test] The same file asserts a control with `source` set and `source_managed` false is named `c:goal`, not `c:user:goal`.
- **C20** [unverified] Against the live local sidecar with real Helix data, `t.subscribers_total + 1` on an account with `subscribers_total` 0 rendered `0/1 subscribers like this stream. Promised!` through both `LivingTitleService` and `BotCommandResolver`.
- **C21** [unverified] Against the live local sidecar, `max(0, (now_ms() - c.e2e_spin_at * 1000) / 1000)` on a control created seconds earlier returned `5.667`, and `max(c.e2e_spin_at, 0)` returned that control's exact `updated_at` timestamp.
- **C22** [unverified] Five of the pre-`_at` tests and both `_at` tests were run against the tree without their respective fixes and failed there.
- **C23** [unverified] At the time of this change, no row in the local database had `source` set with `source_managed` false, or a `recipe_instance_id` with `source_managed` false, across 529 controls - so C14 changes no key for any existing row.

### Unchanged
- `extractExpressionDependencies()` still ignores `t.` references and still strips the `_at` suffix, and `detectExpressionCycle()`, `OverlayControlController::dependencies()` and the expression save path are not in the diff. The fix does not depend on the dependency list being complete, because it evaluates on read rather than waiting for a trigger.
- `OverlayControl::resolveDisplayValue()` and the `overlay_controls` schema are not in the diff. No stored value is written by this change and no migration accompanies it.
- The overlay is not affected either way: it evaluates expressions in the browser against the render payload, which has always carried both the control values and their `_at` companions. The only overlay-side edit is C15's substitution, which C23 shows resolves to the same string for every existing row.
- `RecomputeExpressionControls`'s cascade, depth cap, `alreadyRecomputed` short-circuit and `handleBatch` entry point are untouched; only where it gets its data map changed.

### Risk
An expression control that reads `_at` has been storing a wrong value (a `max()` over absent
timestamps evaluates to 0 rather than erroring, and was persisted). Those rows correct themselves
on the next recompute or read; nothing rewrites them eagerly. Where the sidecar is unreachable
these surfaces behave exactly as before, printing the stored scalar.
