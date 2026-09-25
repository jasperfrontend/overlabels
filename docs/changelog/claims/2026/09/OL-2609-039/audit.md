## Audit of OL-2609-039 - feat(products): a product can be uninstalled from its page, from the install's own ledger

**Audited:** 2026-09-25
**Commit:** db35d703415794bde30606600c545aa17a432a92 (sole commit carrying `Changelog: OL-2609-039`)
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/Recipes/RecipeInstaller.php:198 @db35d70` - `$primitiveMap['integrations'][$service] = ['created' => $this->connectIntegration(...)]`; `:361` `firstOrCreate`, `:373` `return $integration->wasRecentlyCreated`; same at `:229` / `:417 @HEAD` |
| C2 | CONFIRMED | `RecipeInstaller.php:427-436 @db35d70` - loop over `primitive_map.overlays` calls `$template->kits()->first()` and throws `RuntimeException` with `{$template->name}` and `{$kit->title}` before `DB::transaction` at `:438`; `Kit` has `title` (`app/Models/Kit.php:22,80 @db35d70`); unchanged @HEAD `:672-680` |
| C3 | CONFIRMED | `RecipeInstaller.php:439-461 @db35d70` - `$template->delete()` then `$this->images->deleteByUrl($screenshotUrl)`, then `BotCommand`, `BotAlias`, `ListAppender`, `OptionSet` (`lists` ids) `whereIn->delete()`, then `$instance->delete()`; `OverlayTemplate` has a `deleting` hook (`app/Models/OverlayTemplate.php:144 @db35d70`); `option_sets`/`pickers`/`overlay_controls` `recipe_instance_id` are `cascadeOnDelete` (`database/migrations/2026_05_13_140001_create_recipe_instances_table.php:37-49`), `recipe_chat_triggers` likewise (`2026_05_13_160000_...:13`). @HEAD also deletes alert-trigger mappings first (OL-2609-072) |
| C4 | CONFIRMED | `RecipeInstaller.php:450-458 @db35d70` - skips unless `$info['created']`, then deprovisions and deletes only if the row is found. @HEAD the gate is `productOwnedIntegrations()` (`:600 @HEAD`), which adds `requires_integrations` membership (OL-2609-072 C19) |
| C5 | CONFIRMED | `RecipeInstaller.php:383-409 @db35d70` - one line per surviving overlay, list, appender, alias, command; integration line at `:404` requires `created` and `exists()`. @HEAD narrowed via `productOwnedIntegrations()` (OL-2609-072) and extended with alert-trigger lines |
| C6 | CONFIRMED | `app/Http/Controllers/ProductController.php:154-156 @db35d70` - no instance redirects to `products.show` with no error; `:161` `withErrors(['uninstall' => $e->getMessage()])` on `RuntimeException`. @HEAD adds `canonical()` (OL-2609-114), `ProductSetup::end` (OL-2609-042) and a success flash on the success path (OL-2609-043) |
| C7 | CONFIRMED | `resources/js/pages/products/show.vue:138 @db35d70` - button `v-if="installed"`; `:103-105` comment and `<ConfirmDialog />` mount; `:87-93` `confirm()` with a message built from `installed.removes`, `router.post(route('products.uninstall'))` only after `if (!ok) return`. @HEAD the `ConfirmDialog` mount is gone (`49eca947`, no claim - F2); button `:323 @HEAD` and post `:264 @HEAD` unchanged |
| C8 | CONTRADICTED (one part) | Compound claim. Ran `php artisan test --filter=ProductUninstallTest` @HEAD: 9 passed (61 assertions). Parts confirmed against `tests/Feature/ProductUninstallTest.php @db35d70`: C1 both directions (`:55`, `:68`), C3 for overlay, list, `!bowl` appender, `fbfirst`/`fbdraw` aliases and instance (`:40`; the Follower Bowling manifest installs no `bot_commands`), C4 with `settings.pin_lifetime` kept (`:68-82`), C5 after two hand deletions (`:84`), clean reinstall (`:117`), both C6 page paths (`:148`, `:162`). Part contradicted: "C2 with nothing removed on refusal" - `:103-115` asserts the exception message contains `My bowling kit` and that the instance, the `lane` list and the `fbfirst` alias still exist; it does not assert the overlay, the `!bowl` appender or the `fbdraw` alias survive, nor that the message names the overlay |

### Surface
Complete.

### Findings
- **F1** test narrower than claim - `tests/Feature/ProductUninstallTest.php:103-115 @db35d70` (same assertions @HEAD) checks only the instance, the `lane` list and the `fbfirst` alias after a kit refusal, while C8 says it asserts "nothing removed"; add `not->toBeNull()` checks for the overlay, the `!bowl` `ListAppender` and the `fbdraw` alias, or restate C8 in a new claim.
- **F2** changed without a claim - C7's `<ConfirmDialog />` mount in `resources/js/pages/products/show.vue:105 @db35d70` was removed by `49eca947` ("the products pages live inside the dashboard chrome"), which has no `Changelog:` trailer and no claim (it is `.vue`-only and so claim-exempt under the path rule); the dialog now comes from `resources/js/layouts/AppLayout.vue:74 @HEAD`. A reader of C7 should treat its ConfirmDialog half as superseded; record it in a new claim if the record is meant to track it.
- **F3** scope - the diff changes the action column in `show.vue` from `gap-1` to `gap-2` (hunk at `@@ -106,11 +130,15 @@ @db35d70`), which no Surface line or claim mentions.
- **F4** earlier claim reversed without inline citation - OL-2609-038's Risk records "Uninstalling is manual: delete the overlay, disconnect the integration, delete the list and the command; the `recipe_instances` row stays." This change reverses that and builds on 038's `primitive_map` ledger (038 C6, C30) and `connectIntegration()` (038 C5, whose return type it changes from `void` to `bool`), but claim.md does not cite OL-2609-038 anywhere.

### Notes
- The test was run only @HEAD. The shipped tree could not boot against the current `vendor/` (`Stevebauman\Location\LocationServiceProvider` not found; package removed in OL-2609-097). Changes to the test since then: slug and URL renames (OL-2609-114) and one `assertSessionHas('success', ...)` (OL-2609-043). No assertion was removed.
- The narrowing of C4/C5 by `productOwnedIntegrations()` is disclosed by OL-2609-072 C19. That claim does not cite OL-2609-039 inline; its audit already carries this as its F3.
- Unchanged lines hold: `OverlayTemplateController`, `CheckinIntegrationController` and `WiringFacts` are not in the diff. `checkins` has an FK only to `users` (`2026_09_01_140000_create_checkins_table.php:26`), so its pins outlive the integration row.
- Controls that `BotCounterService::provision()` created for a command's `counter:` tag at install are not removed by `uninstall()`. No claim says they are.
