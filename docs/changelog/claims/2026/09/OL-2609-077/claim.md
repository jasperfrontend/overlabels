## OL-2609-077 - feat(products): Donation Alerts, the first product that asks which service

**Shipped:** 2026-09-14
**Commit:** `git log --grep=OL-2609-077`

### Surface
- `resources/recipes/donation_alert/manifest.json` - new file
- `resources/recipes/donation_alert/stage.md` - new file, the static overlay
- `resources/recipes/donation_alert/tip_alert.md` - new file, the alert overlay
- `tests/Feature/ProductDonationAlertTest.php` - new file
- `tests/Feature/ProductInstallTest.php` - the listed key list and the product count gain `donation_alert`
- `tests/Feature/ProductBowlingTest.php` - Follower Bowling's listing index moves from 2 to 3

### Claims
- **C1** [code] `donation_alert/manifest.json` declares `listed: true`, `requires_bot: false`, `max_instances_per_user: 1`, and no `requires_integrations` key.
- **C2** [code] The manifest declares exactly one ingredient, key `service`, with five choices whose values are `streamlabs`, `kofi`, `bmac`, `fourthwall`, `throne`, and default `streamlabs`.
- **C3** [code] `installs.integrations` is `["{{service}}"]`, `installs.alert_triggers` is one entry with `overlay` `tip_alert`, `service` `{{service}}` and `event_type` `donation`, and `installs.alert_targets` targets `tip_alert` at `stage`.
- **C4** [code] `stage.md` is `type: static` and its html reads `[[[c:{{service}}:donations_received]]]` and `[[[c:{{service}}:latest_donor_name]]]`, the latter also inside an `[[[if:...]]]`.
- **C5** [code] `stage.md` carries the `.alert` rules in its css; `tip_alert.md` has an empty css field.
- **C6** [code] `tip_alert.md` is `type: alert`, reads only `event.from_name`, `event.formatted_amount` and `event.message`, contains no placeholder, and declares a sound URL, a 3000ms TTS delay, and identical TTS and chat messages.
- **C7** [code] Each of the five choice values is a key in `ExternalEventTemplateMapping::SERVICE_EVENT_TYPES` with a `donation` entry, and each driver's `getAutoProvisionedControls()` includes `donations_received` and `latest_donor_name`.
- **C8** [code] `public/products/` contains no `donation-alert` hero; the manifest declares no `hero`.
- **C9** [test] `ProductDonationAlertTest` contains 10 tests: the listing at index 2 with a null hero, the page's ingredient and overlays, the absent `requires_integrations`, an install answering `kofi` writing the integration, the trigger, the targeting pivot and the filled stage, an install with no answer picking `streamlabs`, a refused `paypal` creating nothing, the answer shown once installed, the setup banner's next step carrying target `integration-kofi` and a URL ending `#el-integration-kofi`, an install onto a pre-existing disabled `kofi` row re-enabling it with `created` false, and an uninstall leaving the `bmac` connection.
- **C10** [test] `ProductInstallTest` asserts the listed keys are `chat_checkin`, `chat_tower`, `donation_alert`, `follower_bowling` and the listing has 4 products.
- **C11** [unverified] The manifest passed `RecipeManifestValidator::validateFile()` and both documents parsed after `RecipeIngredients::fill()` with `service` = `kofi`, run in tinker before the tests were written.
- **C12** [unverified] The product page's select, the install, and the setup banner's `#el-integration-<service>` scroll and glow were not exercised in a browser.

### Unchanged
- `RecipeInstaller`, `RecipeManifestValidator`, `RecipeIngredients` and `ProductController` are the OL-2609-076 code and are not in the diff; this change is content plus tests.
- `IntegrationController::productIntegrations()` groups a service under a product only when that product's `requires_integrations` names it; with none declared here, all five donation services stay under External Integrations.
- The other three manifests under `resources/recipes/` are untouched.
- The alert prints no `[[[event.source]]]`, so the `StreamLabs` casing contract on that tag (OL-2609-074) is not exercised by this product.

### Risk
`/products` lists a fourth product. Installing it with a service the streamer has never connected creates
an enabled but unauthorized integration row for that service, which the product page and the setup
banner report as the one thing left to do.
