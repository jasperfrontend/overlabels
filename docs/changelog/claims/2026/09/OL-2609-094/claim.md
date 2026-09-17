## OL-2609-094 - fix(controls): the two remaining inline tag-key computations route through tagIdentifier()

**Shipped:** 2026-09-18
**Commit:** `git log --grep=OL-2609-094`

Remedies OL-2609-060 audit F2, F3; F1 skipped (already resolved by OL-2609-092).

### Surface
- `app/Http/Controllers/OverlayTemplateController.php` - the `randomControls` entry's `key` is `$control->tagIdentifier()` instead of the inline ternary
- `app/Http/Controllers/Settings/BotCommandsController.php` - `availableControlKeys()` maps each control to `$c->tagIdentifier()` instead of the inline ternary
- `tests/Unit/TagIdentifierCallSitesTest.php` - new file, 1 test
- `docs/changelog/claims/2026/09/OL-2609-060/remedy.md` - new file, the remedy record for the OL-2609-060 audit

### Claims
- **C1** [code] `OverlayTemplateController`'s render query builds each `randomControls` entry with `'key' => $control->tagIdentifier()` (corrects OL-2609-060 C15, audit F2).
- **C2** [code] `BotCommandsController::availableControlKeys()` maps each control through `$c->tagIdentifier()` (corrects OL-2609-060 C15, audit F2).
- **C3** [code] Under `app/`, the only occurrence of the shape `source_managed ? $x->broadcastKey() : $x->key` is the body of `OverlayControl::tagIdentifier()` (restates OL-2609-060 C15 as now true, audit F2).
- **C4** [test] `TagIdentifierCallSitesTest` "no call site outside OverlayControl::tagIdentifier computes the tag key inline" scans every `.php` file under `app/` for that shape, asserts `Models/OverlayControl.php` holds exactly one occurrence and every other file holds none.
- **C5** [unverified] C4's test was run against the tree before C1 and C2 and failed naming `Http/Controllers/OverlayTemplateController.php` and `Http/Controllers/Settings/BotCommandsController.php`, then passed after.
- **C6** [code] The log tag on the Twitch-data fetch warning in `ExpressionDataContext::addTwitchTagData()` is `[expression-context]`; the same warning read `[recompute-expression]` when the method lived in `RecomputeExpressionControls` before OL-2609-060, and the remaining `RecomputeExpressionControls::walk()` depth warning still reads `[recompute-expression]` (records the rename OL-2609-060 did not disclose, audit F3).
- **C7** [code] `OverlayControl::tagIdentifier()` returns the same string as `$control->source_managed ? $control->broadcastKey() : $control->key` for every row, so C1 and C2 change no emitted key.

### Unchanged
- `OverlayTemplateController`'s `$expressionsByKey[$control->broadcastKey()]` entry a few lines above the `randomControls` block is not in the diff; the audit finding names the inline ternary shape only, and that line is not one.
- `OverlayControl::tagIdentifier()` and `broadcastKey()` are not in the diff.
- OL-2609-060 audit F1 (`ExpressionControlHydrator::evaluate()` keying by `broadcastKey()`) is not touched here; OL-2609-092 C1 and C2 already resolved it.
