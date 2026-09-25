## Audit of OL-2609-075 - test(products): pin that every setup step target has an element to land on

**Audited:** 2026-09-25
**Commit:** edbc3e355606748ffc846d623d6e273e9687e3e7
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `tests/Feature/ProductTargetCoverageTest.php:32-38 @edbc3e3` - loops `ProductSetup::STEPS`, skips null `target`, requires `str_contains($sources, 'data-product-target="'.$target.'"')`; sources are `.vue`/`.ts` under `resource_path('js')` (`:23`), a subset of the claimed "file under resources/js", so the test is stricter, not narrower. File unchanged @HEAD. `php artisan test --filter=ProductTargetCoverageTest` @HEAD: 3 passed (4 assertions). |
| C2 | CONFIRMED | `tests/Feature/ProductTargetCoverageTest.php:56-59 @edbc3e3` - `.vue` files only, true if one line contains both `data-product-target` and `integration-`; matched by `resources/js/pages/settings/integrations/index.vue:79 @edbc3e3` (`:data-product-target="\`integration-${row.key}\`"`), same @HEAD. Passed in the run above. The "only way" clause is rationale and is not asserted. |
| C3 | CONTRADICTED | `tests/Feature/ProductTargetCoverageTest.php:82 @edbc3e3` - collects values with `/data-product-target="([a-z0-9_-]+)"/` over `.vue`/`.ts` files only (`:23`). A double-quoted value containing any character outside `[a-z0-9_-]` (e.g. uppercase, `.`), a single-quoted value, or any value in the `.js`/`.mjs` files under `resources/js` (`overlay/app.js`, `lib/expression-engine/engine.mjs` @HEAD) is never compared against `STEPS`. It does assert the claim for lowercase-hyphen double-quoted values in `.vue`/`.ts`, and passed in the run above. |
| C4 | UNVERIFIABLE | tagged [unverified]; a fail-first run against a tree that no longer exists, which the guide assigns to this tag |

### Surface
Complete.

### Findings
- **F1** [test] narrower than claimed - C3 says "every statically written `data-product-target=\"...\"` value in `resources/js`", but `tests/Feature/ProductTargetCoverageTest.php:82 @edbc3e3` only captures `[a-z0-9_-]+` values and `:23` only reads `.vue`/`.ts`; either widen the regex/extensions or restate C3 in a new claim to match what is asserted.
- **F2** scope - `tests/Feature/ProductTargetCoverageTest.php:71 @edbc3e3` also asserts `expect(ExternalServiceRegistry::services())->not->toBeEmpty()`, which no claim or Surface line accounts for; record it in a new claim or drop it.

### Notes
- Test file has no commits after edbc3e3, so the HEAD run is a run of the shipped test.
- `ProductSetup::targetFor()` changed after ship (reads `RecipeInstance::resolvedManifest()`), disclosed by OL-2609-076 C11; `ProductSetup::STEPS` is identical @HEAD.
- Unchanged lines hold: `useProductFocus()`, `ProductSetup::STEPS` and `targetFor()` are not in the diff (`resources/js/composables/useUiMode.ts:138-151 @edbc3e3` retries 60 animation frames, then returns silently).
