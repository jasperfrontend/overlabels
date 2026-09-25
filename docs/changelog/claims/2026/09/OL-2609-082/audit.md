## Audit of OL-2609-082 - feat(products): Donation Alerts fires on every donation service, and its stage reads across them

**Audited:** 2026-09-25
**Commit:** b1a9e46eeb0892cdcebdda1c750d8a3aa3f57e9a
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/recipes/donation_alert/manifest.json:43-48 @b1a9e46` - five triggers, `streamlabs`, `kofi`, `bmac`, `fourthwall`, `throne` in that order, each `overlay` `tip_alert`, `event_type` `donation`; `:42` `"integrations": ["{{service}}"]`; `:26` question `Which service should we connect first?`; `:22` `max_instances_per_user: 1`. File deleted @HEAD (OL-2609-084) |
| C2 | CONFIRMED | `resources/recipes/donation_alert/stage.md:31-38 @b1a9e46` - html reads `[[[c:tips_total]]]` (`:33`) and `[[[c:newest_donor]]]` (`:34-35`), no `c:<service>:` tag and no `{{service}}`; the css block `:44-100` has no hunk in `git show b1a9e46`. File deleted @HEAD (OL-2609-084) |
| C3 | CONFIRMED | Ran `OverlayMarkdown::parse()` on `stage.md @b1a9e46`: `controls` holds `tips_total` (expression `sum(...)` over the five `donations_received`) and `newest_donor` (expression `latest(...)` over five `donations_received_at` / `latest_donor_name` pairs); `OverlayControl::extractExpressionDependencies()` returned 5 and 10 entries |
| C4 | CONFIRMED | Ran `RecipeManifestValidator::validateFile()` on `manifest.json @b1a9e46` - `{"valid":true,"errors":[]}`; `validateFile()` passes `dirname($path)` (`app/Services/Recipes/RecipeManifestValidator.php:99 @b1a9e46`) |
| C5 | CONFIRMED | `app/Services/Recipes/RecipeInstaller.php:440-465 @b1a9e46` - one enabled `ExternalEventTemplateMapping::create` per non-twitch trigger; `:534-563 @b1a9e46` - `assertNoAlertTriggerCollisions()` loops every trigger and throws on an existing row with a live template. @HEAD the refusal sentence differs (OL-2609-083) |
| C6 | CONFIRMED | `tests/Feature/ProductDonationAlertTest.php @b1a9e46` - `:80` manifest's five triggers, `:90` five enabled rows with only `kofi` connected, `:115` two expression controls with all five services in `dependencies`, `:185` `fires_on` naming all five, `:272` Throne tip landing on a Ko-fi install, `:163` hand-built Throne alert refusing a Ko-fi install naming `My Throne alert`, `:349` uninstall removing stage controls. `php artisan test --filter=ProductDonationAlertTest` on the `@b1a9e46` tree: 19 passed. File deleted @HEAD (OL-2609-084) |
| C7 | UNVERIFIABLE | tagged [unverified] (fail-first run against a stashed tree) |
| C8 | UNVERIFIABLE | tagged [unverified]; compound, second sentence is a code statement (F1) |

### Surface
Complete.

### Findings
- **F1** mistagged, checkable as [code] - C8's second sentence ("The render query in `OverlayTemplateController` ships every user-scoped source-managed control with its `_at` companion regardless of the template's tags") names a controller and a query in-repo and could be checked by reading it; split it off as its own `[code]` claim in a new claim.
- **F2** scope - `stage.md:9 @b1a9e46` (the paragraph under `# Donation stage`) was rewritten, and `OverlayMarkdown::parse()` returns that paragraph as the stage's `description`, so an install now writes a different description onto the stage overlay; the Surface line for `stage.md` and no claim mention it. Record it in a new claim.
- **F3** Unchanged line inaccurate - Unchanged line 1 says "No PHP or Vue in the diff", but `tests/Feature/ProductDonationAlertTest.php` is PHP and is in the diff (and in Surface); the named symbols (`RecipeIngredients`, `RecipeInstaller`, `RecipeManifestValidator`, `ProductController::installedView()`, `products/show.vue`) are correctly absent. The line should say no application PHP or Vue.
- **F4** contradicts the record - the change reverses OL-2609-077 C3 ("`installs.alert_triggers` is one entry with `overlay` `tip_alert`, `service` `{{service}}`") and OL-2609-077 C4 ("its html reads `[[[c:{{service}}:donations_received]]]` and `[[[c:{{service}}:latest_donor_name]]]`") without citing OL-2609-077 anywhere in the claim; a new claim should record "supersedes OL-2609-077 C3, C4".

### Notes
- The tests were run on a `git archive` of b1a9e46 with HEAD's `vendor/` (autoload regenerated) and HEAD's `public/build`; without a Vite manifest, 9 of 19 failed with "Vite manifest not found", which is environmental.
- @HEAD, all three `donation_alert` recipe files and `ProductDonationAlertTest.php` are gone, deleted by OL-2609-084 (08298f12), which discloses it in its Surface. OL-2609-083 (ceb4530d) earlier reworded the refusal sentence and set the C6 collision test's row to `enabled => false`. Both are disclosed, so neither is a finding.
- Surface says the test file got "five tests added, six rewritten". The file went from 15 `it(` blocks @b1a9e46~1 to 19 @b1a9e46, a net gain of four. The Surface wording only adds up if one old test is counted as replaced.
