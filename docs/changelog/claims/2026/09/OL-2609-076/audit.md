## Audit of OL-2609-076 - feat(recipes): a recipe can ask a question, and the answer lands wherever it says {{key}}

**Audited:** 2026-09-25
**Commit:** f8429e84c019a3b396841dfa5e364e60c9821d62
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/recipes/recipe-manifest.schema.json:119-166 @f8429e8` - item `required` is `["key","question","choices","default"]` (:125), `additionalProperties` false, `choices.minItems` 2 (:141), each choice requires `value` and `label`; unchanged @HEAD (moved to :131 by OL-2609-088/114 additions elsewhere in the file) |
| C2 | CONFIRMED | `recipe-manifest.schema.json:194` and `:213 @f8429e8` are the only two patterns with the `\{\{...\}\}` alternative (grep of every `pattern` in the file); same @HEAD |
| C3 | CONFIRMED | `app/Services/Recipes/RecipeIngredients.php:26 @f8429e8` - `PLACEHOLDER = '/\{\{([a-z][a-z0-9_]{0,49})\}\}/'`; file unchanged @HEAD |
| C4 | CONFIRMED | `RecipeIngredients.php:64-99 @f8429e8` - unknown given key throws `RuntimeException` ("does not ask"), absent key takes `default`, non-choice value throws `RuntimeException`; unchanged @HEAD |
| C5 | CONFIRMED | `RecipeIngredients.php:109-116 @f8429e8` - `resolve()` returns `$manifest` when `$answers === []`, else `walk()` runs `fill()` on every string leaf; unchanged @HEAD |
| C6 | CONFIRMED | `RecipeIngredients.php:164-179 @f8429e8` - starts from `[[]]`, extends by each ingredient's choice values; unchanged @HEAD |
| C7 | CONFIRMED | `app/Services/Recipes/RecipeInstaller.php:83 @f8429e8` fifth param `array $ingredients = []`; `:103-104` answers + resolve precede `assertNoChatCommandCollisions($manifest, ...)`; no `$recipe->manifest` read after :104 in `install()` (grep); same lines @HEAD |
| C8 | CONFIRMED | `RecipeInstaller.php:339 @f8429e8` - `OverlayMarkdown::parse(RecipeIngredients::fill($markdown, $answers))`; no `file_put_contents`/`fwrite` in `installOverlay()`; same @HEAD |
| C9 | CONFIRMED | `RecipeInstaller.php @f8429e8` (diff hunk at old :129) - `RecipeInstance::create([... 'ingredients' => $answers])`; same @HEAD |
| C10 | CONFIRMED | `app/Models/RecipeInstance.php:63-66 @f8429e8` - `RecipeIngredients::resolve($this->recipe?->manifest ?? [], $this->ingredients ?? [])`; @HEAD same body at :79-82 (OL-2609-114 added `instanceSlugFrom()` above it) |
| C11 | CONFIRMED | `app/Support/WiringFacts.php:363`, `app/Support/ProductSetup.php:151`, `RecipeInstaller.php:592`, `app/Http/Controllers/RecipeInstanceController.php:38,113 @f8429e8` all call `resolvedManifest()`; same @HEAD |
| C12 | CONFIRMED | `database/migrations/2026_09_14_120000_add_ingredients_to_recipe_instances_table.php:19 @f8429e8` - `jsonb('ingredients')->default(DB::raw("'{}'::jsonb"))` |
| C13 | CONFIRMED | `RecipeManifestValidator.php:47 @f8429e8` `?string $directory = null`; `:99` `validateFile()` passes `dirname($path)`; `RecipeCatalog.php:106` passes `dirname($path)`; `RecipeInstaller.php:95` passes `self::directoryFor($recipe->slug)`; unchanged @HEAD |
| C14 | CONFIRMED | `RecipeManifestValidator.php:385-452 @f8429e8` - duplicate key, duplicate choice, default-not-in-choices, manifest placeholder naming no ingredient, document placeholder naming no ingredient (:443) |
| C15 | CONFIRMED | `RecipeManifestValidator.php:454 @f8429e8` - unused-ingredient check inside `if ($directory !== null)` |
| C16 | CONFIRMED | `RecipeManifestValidator.php:467-507 @f8429e8` - per combination: placeholder integration vs `ExternalServiceRegistry::has()`, placeholder trigger service vs registry and `SERVICE_EVENT_TYPES`, filled documents through `unprovisionedControlTags()` against `getAutoProvisionedControls()` (see Notes on `twitch`) |
| C17 | CONFIRMED | `RecipeManifestValidator.php:500 @f8429e8` skips documents with no placeholder before any parse; `:468` prefix `With <k> = <v>: ` |
| C18 | CONFIRMED | `RecipeManifestValidator.php:294 @f8429e8` - `continue` before the twitch/registry/event-type checks (see F2 for what else it skips) |
| C19 | CONFIRMED | `RecipeManifestValidator.php:568 @f8429e8` calls `extractTemplateTags([])`; `app/Models/OverlayTemplate.php:205-207 @f8429e8` merges `extractConditionalTags()`; test "checks a control read only inside a condition" passes |
| C20 | CONFIRMED | `app/Http/Controllers/ProductController.php:165-173 @f8429e8` - `'ingredients' => ['sometimes','array']`, `'ingredients.*' => ['string','max:50']`, fifth arg to `install()`; @HEAD :370-378, same rules |
| C21 | CONFIRMED | `ProductController.php:117` (`RecipeIngredients::declared($manifest)`) and `:280` (`$instance->ingredients ?? []`) `@f8429e8`; @HEAD :316, :725 |
| C22 | CONFIRMED | `resources/js/pages/products/show.vue @f8429e8` - `:6` imports `serviceLabel`, `integrationLabels` absent, `:115` posts `{ ingredients: answers.value }`, `:212` one `<select>` per ingredient under `v-if="!installed ..."` with `answers` seeded from `default`, `:220` `answerLabel()` once installed; same @HEAD (:6, :156, :343, :351) |
| C23 | CONTRADICTED | `tests/Feature/RecipeIngredientsTest.php` has 30 tests and all 30 pass (see Notes), but none asserts: C7's ordering (no fixture has chat commands, so nothing shows the resolve precedes `assertNoChatCommandCollisions()`); C11 for `ProductSetup::targetFor()` and the two `RecipeInstanceController` reads (no test calls either), nor `productOwnedIntegrations()` (the fixture declares no `requires_integrations`, so the uninstall test passes with a raw read too); C17's first half (no test shows a placeholder-free document is not parsed) |
| C24 | UNVERIFIABLE | tagged [unverified]; a fail-first run against a tree that no longer exists, a legitimate use |

### Surface
Complete.

### Findings
- **F1** [test] claim overstates coverage - C23 says `RecipeIngredientsTest` covers C3-C11 and C14-C19, but no test reaches `ProductSetup::targetFor()`, `RecipeInstanceController` (`index()`, `fireButton()`), the `requires_integrations` read in `productOwnedIntegrations()`, C7's ordering, or C17's skip of placeholder-free documents; add tests or restate the claim with the coverage it actually has.
- **F2** undisclosed scope / narrows OL-2609-072 C11 without citing it - the `continue` at `app/Services/Recipes/RecipeManifestValidator.php:294 @f8429e8` also skips the duplicate `(service, event_type)` check further down the same loop, and `ingredientErrors()` does not reinstate it per combination. Probe @HEAD (same logic): two triggers `{service: "{{service}}", event_type: "donation"}` validate as `{"valid":true,"errors":[]}`, while the same two with literal `kofi` report `Duplicate alert trigger for "kofi:donation"`. OL-2609-072 C11 records that `installsErrors()` reports a duplicate `(service, event_type)`, and neither C18 nor any other line of this claim cites or discloses the carve-out. Add the duplicate check to the per-combination pass, or record the exception in a new claim.

### Notes
- `RecipeIngredientsTest` uses `DatabaseTransactions`, not `RefreshDatabase`. It failed 7/30 (`column "ingredients" ... does not exist`) until the local `laravel_foxes_test` DB was migrated (`php artisan migrate` against the test DB, 12 pending migrations). Then 30 passed, 69 assertions.
- C16/C11 of OL-2609-072: a placeholder trigger that resolves to `twitch` is checked against `EventTemplateMapping::EVENT_TYPES` and never reported as unregistered, although `twitch` is not in `ExternalServiceRegistry @f8429e8`. This mirrors the raw pass. C16 does not mention it.
- OL-2609-072 C19/C32 describe `productOwnedIntegrations()` and `ProductSetup::targetFor()` as reading the "instance recipe's" manifest. This change moves both to `resolvedManifest()`, which is identical for every instance with `ingredients = {}`. Disclosed in Surface and C11; 072 is not cited inline.
- `app/Listeners/BridgePickerLandedToControl.php:39` still reads the raw `$recipe->manifest` @f8429e8 and @HEAD. It is outside the claim; recorded here in case pickers ever take placeholders.
- Later commits touching the Surface files all carry claims (OL-2609-078..090, 109..129), except `49eca947`, which is `.vue`-only. None changes a symbol this claim names.
