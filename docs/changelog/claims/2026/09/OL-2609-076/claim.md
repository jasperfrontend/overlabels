## OL-2609-076 - feat(recipes): a recipe can ask a question, and the answer lands wherever it says {{key}}

**Shipped:** 2026-09-14
**Commit:** `git log --grep=OL-2609-076`

### Surface
- `resources/recipes/recipe-manifest.schema.json` - top-level `ingredients` added; `installs.integrations[]` and `installs.alert_triggers[].service` patterns admit a `{{key}}` placeholder
- `app/Services/Recipes/RecipeIngredients.php` - new file
- `app/Services/Recipes/RecipeManifestValidator.php` - `validate()` takes a directory; `ingredientErrors()`, `overlayDocuments()` and `unprovisionedControlTags()` added; `installsErrors()` skips placeholder services
- `app/Services/Recipes/RecipeCatalog.php` - `read()` validates with the manifest's directory
- `app/Services/Recipes/RecipeInstaller.php` - `install()` takes answers, resolves the manifest, fills documents, records answers; `productOwnedIntegrations()` reads the resolved manifest
- `app/Models/RecipeInstance.php` - `ingredients` fillable and cast; `resolvedManifest()` added
- `database/migrations/2026_09_14_120000_add_ingredients_to_recipe_instances_table.php` - new file
- `app/Support/WiringFacts.php` - `productSubject()` reads the resolved manifest
- `app/Support/ProductSetup.php` - `targetFor()` reads the resolved manifest
- `app/Http/Controllers/RecipeInstanceController.php` - both manifest reads go through `resolvedManifest()`
- `app/Http/Controllers/ProductController.php` - `show()` exposes the questions and the answers; `install()` accepts answers
- `resources/js/pages/products/show.vue` - one select per question before install, the answer after; integration labels via `serviceLabel()`
- `tests/Feature/RecipeIngredientsTest.php` - new file

### Claims
- **C1** [code] `recipe-manifest.schema.json` defines `ingredients[]` with required `key`, `question`, `choices` (`minItems` 2, each with required `value` and `label`) and `default`, `additionalProperties` false.
- **C2** [code] The `pattern` for `installs.integrations[].items` and for `installs.alert_triggers[].service` each accept `{{key}}` as an alternative to a service key, and no other pattern in the schema does.
- **C3** [code] `RecipeIngredients::PLACEHOLDER` is `/\{\{([a-z][a-z0-9_]{0,49})\}\}/`.
- **C4** [code] `RecipeIngredients::answers()` returns the ingredient's `default` for a key absent from the given answers, throws `RuntimeException` for a value not among that ingredient's choice values, and throws `RuntimeException` for a given key the manifest declares no ingredient for.
- **C5** [code] `RecipeIngredients::resolve()` replaces placeholders in every string leaf of the manifest and returns the manifest unchanged when the answers are empty.
- **C6** [code] `RecipeIngredients::combinations()` returns the cartesian product of the choice values across ingredients, and `[[]]` for a manifest with no ingredients.
- **C7** [code] `RecipeInstaller::install()` has a fifth parameter `array $ingredients = []`, computes the answers and the resolved manifest before `assertNoChatCommandCollisions()`, and every manifest read after that point in `install()` is of the resolved manifest.
- **C8** [code] `RecipeInstaller::installOverlay()` passes the document text through `RecipeIngredients::fill()` before `OverlayMarkdown::parse()` and contains no write to the file.
- **C9** [code] The `RecipeInstance::create()` call in `install()` writes the answers to the `ingredients` attribute.
- **C10** [code] `RecipeInstance::resolvedManifest()` returns `RecipeIngredients::resolve()` of the recipe's manifest and the instance's `ingredients`.
- **C11** [code] `WiringFacts::productSubject()`, `ProductSetup::targetFor()`, `RecipeInstaller::productOwnedIntegrations()` and both manifest reads in `RecipeInstanceController` obtain the manifest through `RecipeInstance::resolvedManifest()`.
- **C12** [code] The migration adds `recipe_instances.ingredients` as `jsonb` with default `'{}'::jsonb`.
- **C13** [code] `RecipeManifestValidator::validate()` has an optional second parameter `?string $directory`; `validateFile()` passes `dirname($path)`, `RecipeCatalog::read()` passes `dirname($path)`, and `RecipeInstaller::install()` passes `RecipeInstaller::directoryFor($recipe->slug)`.
- **C14** [code] `RecipeManifestValidator::ingredientErrors()` reports a duplicate ingredient key, a duplicate choice value, a default not among the choices, a placeholder in the manifest naming no ingredient, and a placeholder in an overlay document naming no ingredient.
- **C15** [code] `ingredientErrors()` reports an ingredient referenced by no manifest string and no overlay document only when `$directory` is not null.
- **C16** [code] For every combination from `RecipeIngredients::combinations()`, `ingredientErrors()` reports a placeholder `installs.integrations[]` entry whose resolved value `ExternalServiceRegistry::has()` rejects, a placeholder trigger `service` whose resolved value is unregistered or lacks the trigger's `event_type` in `ExternalEventTemplateMapping::SERVICE_EVENT_TYPES`, and each `c:<service>:<key>` tag in a filled overlay document whose driver's `getAutoProvisionedControls()` lists no such `key`.
- **C17** [code] `ingredientErrors()` parses only overlay documents that contain a placeholder, and its per-combination messages begin with `With <key> = <value>: `.
- **C18** [code] `installsErrors()` `continue`s past the service and event-type checks for a trigger whose `service` satisfies `RecipeIngredients::isPlaceholder()`.
- **C19** [code] `unprovisionedControlTags()` obtains tags from `OverlayTemplate::extractTemplateTags()`, so a control read only inside `[[[if:...]]]` is included.
- **C20** [code] `ProductController::install()` validates `ingredients` as `sometimes|array` with string items of max 50 and passes it as the fifth argument to `RecipeInstaller::install()`.
- **C21** [code] `ProductController::show()` returns `product.ingredients` from `RecipeIngredients::declared()` and `installed.ingredients` from the instance's `ingredients`.
- **C22** [code] `products/show.vue` posts `{ ingredients: answers }` on install, renders one `<select>` per ingredient with its `default` preselected while not installed, renders the chosen choice's label once installed, and imports `serviceLabel` from `@/utils/services` in place of the removed local `integrationLabels` map.
- **C23** [test] `RecipeIngredientsTest` contains 30 tests covering C3 through C11 and C14 through C19.
- **C24** [unverified] With `RecipeIngredients::fill()` removed from `installOverlay()` and `ingredientErrors()` returning `[]`, 12 of the 30 tests failed: the ten validator tests and the two install tests that read the installed overlay's html.

### Unchanged
- No manifest in `resources/recipes/` declares `ingredients` or writes a placeholder. The structure ships with no first-party caller; the three listed products and the two picker recipes are not in the diff.
- `installsErrors()` checked no literal `installs.integrations[]` entry against `ExternalServiceRegistry` before this change, and its raw pass still does not; the new per-combination check covers placeholder entries only.
- `WiringFacts::productSubjects()` still filters instances on `isset($instance->recipe?->manifest['installs'])`, a presence check on the catalogue row that a placeholder does not change.
- `ProductSetup::banner()` still takes the product name and steps from the catalogue file via `$catalog->find($slug)`, as recorded under OL-2609-072.
- `OverlayTemplate::extractTemplateTags()` is what the validator now calls for a filled document; it is not in the diff.
- The two catalogue assertions in `tests/Feature/ProductInstallTest.php` (the listed key list and `has('products', 3)`) are untouched, since no listed product changed.

### Risk
A migration adds one column. Every existing instance reads `ingredients` as `{}`, so `resolvedManifest()`
equals the catalogue manifest for it.

The product install form now posts an `ingredients` object with every install. For a product with no
questions it is empty and the request validates as before.
