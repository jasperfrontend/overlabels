## Audit of OL-2609-049 - feat(products): the flow frame names itself, the banner says what to do, and the next step's control lights up on its page

**Audited:** 2026-09-25
**Commit:** 7bcc61fe, 747554d1 (two commits carry the trailer; audited as one diff - the second adds `resources/js/types/index.d.ts` and the Surface line for it)
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Support/ProductSetup.php:34-43 @7bcc61f` - `STEPS` has one entry per wire in `WiringCatalog.php:203 @7bcc61f` (bot_on, bot_hears, bot_modded, integration, overlay, list, command, token); `:98` `todo` falls back to `strtolower($next['label'])`, `:99` `target` falls back to `null`. @HEAD `banner()` delegates to `step()`, same fallbacks at `:104`; `target` now comes from `targetFor()` and `product.integration` todo reworded (OL-2609-072) |
| C2 | CONFIRMED | `app/Support/ProductSetup.php:35-37 @7bcc61f` - three bot wires `bot-toggle`; `:42` token `token-create`; `:38-41` integration, overlay, list, command `null`. @HEAD integration target resolved per install by `targetFor()` (OL-2609-072 C32) |
| C3 | CONFIRMED | `resources/js/composables/useUiMode.ts:74 @7bcc61f` - `target: setup.next?.target ?? null` with a flow; `:77` `target: null` without; `:102-105` `useProductTarget` returns `mode.value === 'product' && target.value === key`. @HEAD `useProductTarget` removed (OL-2609-072 C27) |
| C4 | CONFIRMED | `resources/css/app.css:19-25 @7bcc61f` - `outline: 2px solid var(--color-fuchsia-500)`, `outline-offset: 3px`, `animation: product-target-glow`; `:42-44` `animation: none` under `prefers-reduced-motion: reduce`. `git grep product-target 7bcc61f -- resources` binds it at `settings/integrations/index.vue:339` and `overlaytokens/index.vue:158` only. @HEAD the rule is `[data-product-focus]` (OL-2609-072 C30) and the bot card moved to `settings/integrations/bot.vue` (OL-2609-069 C11) |
| C5 | CONFIRMED | `resources/js/components/ProductFlowFrame.vue:21-25 @7bcc61f` - `absolute bottom-0 left-0`, `bg-green-500` when ready else `bg-fuchsia-500` (border uses the same pair at `:17`), text "Product installation complete" / "Product installation mode enabled"; same text @HEAD `:25` |
| C6 | CONFIRMED | `resources/js/components/ProductSetupBanner.vue:21 @7bcc61f` - `Next: ${s.next.todo}.`; no `label` reference in the file @7bcc61f. @HEAD `todo` rendered as a link (OL-2609-072) |
| C7 | CONTRADICTED | Compound. Pest half CONFIRMED: `tests/Feature/ProductSetupFlowTest.php:65-66 @7bcc61f` asserts `next.todo` and `next.target` for a fresh checkin install with `bot_enabled` false; `php artisan test --filter=ProductSetupFlowTest` passed 9/9 @747554d (after `npm run build` and a shipped-era `composer install`) and 11/11 @HEAD. Vitest half CONTRADICTED: `resources/js/composables/useUiMode.test.ts @7bcc61f` asserts `resolveUiMode()` `target` for a flow with a target (`:6-11`), a flow with `next: null` (`:12-17`) and no flow (`:27-34`), but imports only `applyUiMode, parseUiMode, resolveUiMode` (`:2`) and never calls `useProductTarget`, so the `useProductTarget` half of C3 is not asserted; the file passed 8/8 @747554d and 15/15 @HEAD |
| C8 | UNVERIFIABLE | tagged [unverified]; local browser observation |

### Surface
Complete.

### Findings
- **F1** test narrower than claimed - C7 says `useUiMode.test.ts` asserts C3, but at `resources/js/composables/useUiMode.test.ts @7bcc61f` nothing calls `useProductTarget()`, so its "true only when mode is `product` and target equals key" rule was untested; since OL-2609-072 removed `useProductTarget`, a correcting claim should restate C7 as covering `resolveUiMode()` only.
- **F2** Unchanged line inaccurate - "The other five product wires have no target" is false: `app/Support/ProductSetup.php:38-41 @7bcc61f` has four null-target wires (C2 itself names four), and the destinations it gives ("the product page, the lists page, the bot commands page") do not match `app/Support/WiringCatalog.php @7bcc61f`, where those wires route to `settings.integrations.index`, `templates.index`, `lists.index` and `settings.bot.commands.index` and none route to the product page; a correcting claim should restate the count and the routes.

### Notes
- `product.bot_hears` targets `bot-toggle` but its wire routes to `settings.bot.commands.index` (`WiringCatalog.php:117 @7bcc61f` and @HEAD), a page that binds no target; since OL-2609-072 its step URL is `/settings/bot/commands#el-bot-toggle`, and `bot-toggle` exists only in `settings/integrations/bot.vue` @HEAD. C2 is true as written; this is a question for the maintainer, not a contradiction.
- `ProductSetup::banner()`'s `@return` docblock `ProductSetup.php:71 @7bcc61f` still typed `next` as `{label, message}`; updated @HEAD `:74`.
- Pest at the shipped tree needed `composer install` (HEAD vendor lacks `stevebauman/location`, removed in OL-2609-097) and `npm run build` (Vite manifest) in a temporary worktree, now removed.
- The second Unchanged line also carries a judgment ("on purpose", "no single control that is the step") that the guide asks to put in Claims with a tag.
