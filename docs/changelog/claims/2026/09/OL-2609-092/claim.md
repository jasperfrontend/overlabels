## OL-2609-092 - fix(controls): a resolved expression dependency lands under the key its dependents read

**Shipped:** 2026-09-17
**Commit:** `git log --grep=OL-2609-092`

Corrects OL-2609-060 C7, found CONTRADICTED by its audit (F1). Extends the same fix to the cascade
listener, which the 060 audit noted as the same shape outside that diff.

### Surface
- `app/Services/Controls/ExpressionControlHydrator.php` - `evaluate()` writes the resolved value under `'c:'.$control->tagIdentifier()` instead of `broadcastKey()`
- `app/Listeners/RecomputeExpressionControls.php` - `walk()` writes a recomputed value under `'c:'.$expr->tagIdentifier()` and recurses with `$expr->tagIdentifier()` instead of `broadcastKey()`; the dispatched `ControlValueUpdated` still carries `broadcastKey()`
- `tests/Feature/ExpressionControlHydrationTest.php` - two new tests and a `sourcedExpressionControl()` helper (`source => 'user'`, `source_managed => false`); imports for the listener and the event

### Claims
- **C1** [code] `ExpressionControlHydrator::evaluate()` contains exactly one write into `$data` and its key is `'c:'.$control->tagIdentifier()`.
- **C2** [code] `RecomputeExpressionControls::walk()` contains exactly one write into `$data` and its key is `'c:'.$expr->tagIdentifier()`.
- **C3** [code] `RecomputeExpressionControls::walk()` recurses with `$expr->tagIdentifier()` as the next key, matching what `OverlayControl::extractExpressionDependencies()` stores in `config->dependencies` for a `c.<key>` reference.
- **C4** [code] The `ControlValueUpdated` dispatched from `walk()` still passes `$expr->broadcastKey()` as its key; the broadcast contract is not in the diff.
- **C5** [code] For a control with `source` null or `source_managed` true, `tagIdentifier()` and `broadcastKey()` return the same string (`OverlayControl::tagIdentifier()`), so every row shape that exists on prod today is unaffected; only a sourced, non-managed control changes behaviour.
- **C6** [test] `ExpressionControlHydrationTest` "a resolved dependency lands under the key its dependents read, not the broadcast key" asserts that when a `source => 'user'` expression control `mid` is a dependency of `top`, the engine call for `top` receives `c:mid` equal to `mid`'s freshly evaluated value and receives no `c:user:mid`.
- **C7** [test] `ExpressionControlHydrationTest` "a cascaded recompute lands under the key its dependents read, not the broadcast key" asserts the same through `RecomputeExpressionControls::handle()` for a `seed -> mid -> top` chain where `mid` is sourced.
- **C8** [unverified] Both tests were run against the pre-fix tree and failed: C6 on `c:mid` holding the stale stored value, C7 on `top` never being evaluated because the recursion walked `user:mid`, which no `config->dependencies` entry contains.

### Unchanged
- `ExpressionDataContext::for()` keys the map by `tagIdentifier()` since OL-2609-060 C15 and is not in the diff; this change makes the two writers agree with that reader, not the other way round.
- `OverlayControl::tagIdentifier()` and `broadcastKey()` are not in the diff.
- `ExpressionDataContext.php:15`'s docblock still says the map is keyed by `broadcastKey`, as the 060 audit noted; a comment, left for a docs pass.
