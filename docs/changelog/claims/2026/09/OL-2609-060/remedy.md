## Remedy of OL-2609-060 - fix(controls): Expression Controls resolve in bot replies, alert messages and the living title

**Remedied:** 2026-09-18
**Claim:** OL-2609-094

| Finding | Outcome | What |
|---------|---------|------|
| F1 | SKIPPED | already resolved by OL-2609-092 C1 (`ExpressionControlHydrator::evaluate()` keys by `tagIdentifier()`) and C2 (the cascade in `RecomputeExpressionControls::walk()`) |
| F2 | FIXED | `app/Http/Controllers/OverlayTemplateController.php` `randomControls` `key` and `app/Http/Controllers/Settings/BotCommandsController.php` `availableControlKeys()` now call `tagIdentifier()`; `TagIdentifierCallSitesTest` "no call site outside OverlayControl::tagIdentifier computes the tag key inline" failed naming both files, then passed |
| F3 | RECORD | OL-2609-094 C6 records the `[recompute-expression]` to `[expression-context]` log tag rename in the extracted `addTwitchTagData()` |
