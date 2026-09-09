## OL-2609-039 - feat(products): a product can be uninstalled from its page, from the install's own ledger

**Shipped:** 2026-09-09
**Commit:** `git log --grep=OL-2609-039`

### Surface
- `app/Services/Recipes/RecipeInstaller.php` - `connectIntegration()` returns whether it created the row and the install records that under `primitive_map.integrations.<service>.created`; new `removals()` and `uninstall()`; `ImageUploadService` injected
- `app/Http/Controllers/ProductController.php` - new `uninstall()` action; `installedView()` adds `removes`
- `routes/web.php` - `products.uninstall` inside the auth group
- `resources/js/pages/products/show.vue` - Uninstall button on an installed product, a `ConfirmDialog` mount, `useConfirm` listing `installed.removes`, and an `uninstall` error line
- `tests/Feature/ProductUninstallTest.php` - new file, 9 tests

### Claims
- **C1** [code] `RecipeInstaller::install()` stores `['created' => bool]` under `primitive_map.integrations.<service>`, from `ExternalIntegration::wasRecentlyCreated` after the `firstOrCreate` in `connectIntegration()`.
- **C2** [code] `RecipeInstaller::uninstall()` checks every overlay in `primitive_map.overlays` for kit membership through `OverlayTemplate::kits()` before opening a transaction, and throws a `RuntimeException` naming the overlay and the kit's `title` if any is found.
- **C3** [code] Inside the transaction `uninstall()` deletes, by the ids in `primitive_map`, the overlays (through the model so the `deleting` hook runs, then `ImageUploadService::deleteByUrl()` on the screenshot), the `bot_commands`, `bot_aliases`, `list_appenders` and `option_sets` rows, then the instance; picker primitives and chat triggers go with the instance through their `recipe_instance_id` cascades.
- **C4** [code] `uninstall()` deprovisions and deletes an `external_integrations` row only when `primitive_map.integrations.<service>.created` is true and the row still exists; an integration the install found is not touched.
- **C5** [code] `RecipeInstaller::removals()` returns one sentence per row in `primitive_map` that still exists, and lists an integration only under the same `created` condition as C4.
- **C6** [code] `ProductController::uninstall()` redirects to `products.show` without error when the user has no instance of the slug, and with an `uninstall` error carrying the exception message when `RecipeInstaller::uninstall()` throws.
- **C7** [code] `products/show.vue` renders the Uninstall button only when `installed` is set, mounts `ConfirmDialog` because the page is outside `AppLayout`, and posts to `products.uninstall` only after `confirm()` resolves true with a message built from `installed.removes`.
- **C8** [test] `ProductUninstallTest` asserts C1 in both directions (created true on a fresh account, false with a pre-existing connection), C2 with nothing removed on refusal, C3 for every Follower Bowling row, C4 with a pre-existing checkin connection keeping its `settings`, C5 after two rows are deleted by hand, a clean reinstall after uninstall, and the two page paths of C6.

### Unchanged
- `OverlayTemplateController::destroy()` is the reference for the overlay half of C3 (kit check, model delete, screenshot cleanup) and is not in the diff.
- `CheckinIntegrationController::disconnect()` is the reference for C4 (deprovision, then delete) and is not in the diff. Pins in `checkins` survive an uninstall the way they survive a disconnect.
- `WiringFacts::productSubject()` is not in the diff; an uninstalled product simply has no instance to be a subject.

### Risk
Accounts that installed a product before this change have no `integrations.<service>.created` entry in their ledger, so an uninstall on those never disconnects the integration, which is the safe direction. Uninstall deletes the list with whatever a stream put in it, and the overlay with any edits made since the install; the dialog says so before the click.
