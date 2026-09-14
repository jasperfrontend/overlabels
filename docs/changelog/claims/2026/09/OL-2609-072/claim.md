## OL-2609-072 - feat(recipes): a recipe can wire an alert, and a product says what is actually left

**Shipped:** 2026-09-14
**Commit:** `git log --grep=OL-2609-072`

### Surface
- `resources/recipes/recipe-manifest.schema.json` - `installs.alert_triggers` and `installs.alert_targets` added
- `app/Services/Recipes/RecipeInstaller.php` - writes both, refuses collisions, removes them on uninstall, integration removal re-keyed
- `app/Services/Recipes/RecipeManifestValidator.php` - `installsErrors()` added
- `app/Contracts/AuthenticatedExternalServiceDriver.php` - new file
- `app/Services/External/Drivers/StreamLabsServiceDriver.php` - implements `requiredCredentials()`
- `app/Services/External/Drivers/KofiServiceDriver.php` - implements `requiredCredentials()`
- `app/Services/External/Drivers/BMACServiceDriver.php` - implements `requiredCredentials()`
- `app/Services/External/Drivers/FourthwallServiceDriver.php` - implements `requiredCredentials()`
- `app/Models/ExternalIntegration.php` - `isAuthenticated()` added
- `app/Support/WiringFacts.php` - the `product.integration` wire counts authenticated connections
- `app/Support/WiringCatalog.php` - `product.integration` missing-state copy reworded
- `app/Support/ProductSetup.php` - `step()` and `targetFor()` added, `product.integration` todo reworded
- `resources/js/composables/useAddressableTabs.ts` - new file
- `resources/js/composables/useUiMode.ts` - `useProductTarget()` removed, `elementKeyFromHash()` and `useProductFocus()` added
- `resources/js/layouts/AppLayout.vue` - mounts `useProductFocus()`
- `resources/js/components/ProductSetupBanner.vue` - next step rendered as a link
- `resources/js/types/index.d.ts` - `productSetup.next.url` added
- `resources/css/app.css` - glow selector moved from `.product-target` to `[data-product-focus]`
- `resources/js/pages/templates/show.vue` - main strip addressable, `obsFirst` removed
- `resources/js/pages/templates/edit.vue` - main strip addressable
- `resources/js/pages/dashboard/index.vue` - main strip addressable
- `resources/js/pages/products/show.vue` - OBS link carries `#tab-obs`
- `resources/js/pages/overlaytokens/index.vue` - `data-product-target="token-create"`
- `resources/js/pages/settings/integrations/bot.vue` - `data-product-target="bot-toggle"`
- `resources/js/pages/settings/integrations/index.vue` - each row carries `data-product-target="integration-<key>"`
- `tests/Feature/RecipeAlertWiringTest.php` - new file
- `tests/Feature/IntegrationReadinessTest.php` - new file
- `tests/Feature/ProductSetupFlowTest.php` - two tests added
- `resources/js/composables/useAddressableTabs.test.ts` - new file
- `resources/js/composables/useUiMode.test.ts` - `elementKeyFromHash` block added

### Claims
- **C1** [code] `recipe-manifest.schema.json` defines `installs.alert_triggers[]` with required `overlay`, `service`, `event_type` and optional `duration_ms`, and `installs.alert_targets[]` with required `alert` and `overlays` (`minItems` 1).
- **C2** [code] `RecipeInstaller::installAlertTriggers()` creates an `EventTemplateMapping` when `service` is `twitch` and an `ExternalEventTemplateMapping` otherwise.
- **C3** [code] A trigger omitting `duration_ms` is written with `RecipeInstaller::DEFAULT_ALERT_DURATION_MS`, which is 5000.
- **C4** [code] `RecipeInstaller::installAlertTriggers()` passes no `condition_type` or `condition_value`.
- **C5** [code] `RecipeInstaller::installAlertTargets()` calls `OverlayTemplate::targetStaticOverlays()->sync()` with the ids of the overlays named in the entry.
- **C6** [code] `RecipeInstaller::overlayOfType()` throws when a trigger's overlay is not `type` `alert` and when a target's listed overlay is not `type` `static`.
- **C7** [code] `RecipeInstaller::assertNoAlertTriggerCollisions()` is called before `DB::transaction()` in `install()` and throws when the account already holds a mapping for the same event whose template still exists.
- **C8** [code] `assertNoAlertTriggerCollisions()` does not throw for a mapping row whose `template` relation resolves to null.
- **C9** [code] `RecipeInstaller::uninstall()` deletes the ids in `primitive_map.alert_triggers.twitch` and `.external` before the overlays are deleted.
- **C10** [code] `RecipeInstaller::removals()` returns one line per recorded alert trigger, using the display label from `EventTemplateMapping::EVENT_TYPES` or `ExternalEventTemplateMapping::SERVICE_EVENT_TYPES`.
- **C11** [code] `RecipeManifestValidator::installsErrors()` reports a duplicate `installs.overlays[].ref`, an `alert_triggers[].overlay` or `alert_targets` ref that names no declared overlay, an unregistered `service`, an `event_type` absent from the matching catalogue, a duplicate `(service, event_type)`, and a duplicate `alert_targets[].alert`.
- **C12** [test] `RecipeAlertWiringTest` contains 23 tests covering C1 through C11.
- **C13** [unverified] The install-write tests in `RecipeAlertWiringTest` were run against a tree with `installAlertTriggers()`/`installAlertTargets()` disabled and 10 failed; the two collision tests failed with `assertNoAlertTriggerCollisions()` disabled; two failed with the uninstall deletes disabled; seven failed with `installsErrors()` disabled.
- **C14** [code] `AuthenticatedExternalServiceDriver` declares one method, `requiredCredentials(): array`.
- **C15** [code] `StreamLabsServiceDriver::requiredCredentials()` returns `['socket_token', 'listener_secret']`, `KofiServiceDriver` returns `['verification_token']`, `BMACServiceDriver` returns `['webhook_secret']`, `FourthwallServiceDriver` returns `['access_token']`.
- **C16** [code] `CheckinServiceDriver`, `TowerServiceDriver`, `ThroneServiceDriver` and `GpsServiceDriver` do not implement `AuthenticatedExternalServiceDriver`.
- **C17** [code] `ExternalIntegration::isAuthenticated()` returns true for a service whose driver does not implement `AuthenticatedExternalServiceDriver`, and otherwise true only when every key from `requiredCredentials()` is non-empty in the decrypted credentials.
- **C18** [code] `WiringFacts::productSubject()` resolves `product.integration` to SATISFIED only when the number of enabled integrations passing `isAuthenticated()` equals the count of services the manifest declares.
- **C19** [code] `RecipeInstaller::productOwnedIntegrations()` returns a service only when `primitive_map.integrations[service].created` is true AND the service appears in the instance recipe's `requires_integrations`.
- **C20** [code] `RecipeInstaller::uninstall()` and `removals()` both decide integration removal through `productOwnedIntegrations()`.
- **C21** [test] `IntegrationReadinessTest` contains 20 tests covering C15 through C20 and C27.
- **C22** [unverified] The wire test in `IntegrationReadinessTest` failed against a tree where the wire counted enabled rows without `isAuthenticated()`; the two crouton tests failed against a tree where `productOwnedIntegrations()` tested `created` alone.
- **C23** [code] `useAddressableTabs.ts` exports `tabKeyFromHash()`, which returns a key only when the fragment carries the `tab-` prefix and the key is in the supplied list, and `urlWithTab()`, which replaces the fragment and preserves the query string.
- **C24** [code] `useAddressableTabs()` writes the fragment with `window.history.replaceState` on change and writes nothing on initialisation.
- **C25** [code] `templates/show.vue`, `templates/edit.vue` and `dashboard/index.vue` each bind their main `TabStrip` to a ref from `useAddressableTabs()`, and `show.vue` contains no `obsFirst`.
- **C26** [code] `products/show.vue` appends `#tab-obs` to the `templates.show` href it builds for the OBS button.
- **C27** [code] `useUiMode.ts` exports no `useProductTarget`, and no file under `resources/js` references it.
- **C28** [code] `useProductFocus()` sets `data-product-focus` on the element whose `data-product-target` matches the flow's current target, and calls `scrollIntoView` only when `elementKeyFromHash(window.location.hash)` equals that target.
- **C29** [code] `elementKeyFromHash()` returns null for a `tab-` prefixed fragment and for a key not matching `/^[a-z0-9_-]+$/i`.
- **C30** [code] `app.css` styles `[data-product-focus]` and contains no `.product-target` rule.
- **C31** [code] `ProductSetup::step()` returns `url` as `route(WiringCatalog::wire($key)['route'])`, with `#el-<target>` appended when a target is resolved and omitted when it is null.
- **C32** [code] `ProductSetup::targetFor()` returns `integration-<service>` for the first service in the INSTANCE recipe's `installs.integrations` that is absent, disabled, or fails `isAuthenticated()`.
- **C33** [test] `ProductSetupFlowTest` asserts the bot step's url is `settings.integrations.bot.show` plus `#el-bot-toggle`, and that a step with a null target carries no fragment.
- **C34** [test] `useAddressableTabs.test.ts` and the `elementKeyFromHash` block in `useUiMode.test.ts` cover C23 and C29.

### Unchanged
- `OverlayTemplateController::updateTriggers()` still resolves a collision on a non-amount event by deleting the other template's mapping row. The installer refuses instead of doing that, and the controller is not in the diff.
- `ProductSetup::banner()` still returns the product page as its top-level `url`; only `next` gained one.
- `primitive_map.integrations[<service>].created` is still written by `install()`. It is one of the two tests `productOwnedIntegrations()` applies, not dead state.
- The `alert_template_static_overlays` pivot cascades from either overlay, so `uninstall()` has no explicit delete for it and none was added.
- `EventTemplateMapping::resolveForEvent()` and `ExternalEventTemplateMapping::resolveForEvent()` decide which alert fires for an incoming event and are what the new mapping rows feed; neither is in the diff.
- No recipe manifest in `resources/recipes/` declares `alert_triggers` or `alert_targets`. The keys ship with no first-party caller.
- `ProductController` reads `installs` key by key, so it does not render the two new keys; it is not in the diff.

### Risk
An installed product declaring a service that requires credentials now reports `product.integration`
as MISSING until those credentials exist, where it previously reported SATISFIED once the row was
enabled. Chat Checkin and Chat Tower declare `checkin` and `tower`, which require none, so no
currently installed product changes state.

Uninstalling a product no longer disconnects a third-party integration or deprovisions its controls,
even when that install created the row.

Selecting a tab on `/templates/{id}`, the template editor or `/dashboard` now rewrites the browser
URL fragment.
