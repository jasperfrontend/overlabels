## OL-2609-084 - feat(products): one product per donation service, each connecting its service on the product page, in a band that is no longer a green wall

**Shipped:** 2026-09-15
**Commit:** `git log --grep=OL-2609-084`

### Surface
- `resources/recipes/donation_alert/manifest.json`, `stage.md`, `tip_alert.md` - deleted: the one product wrapping five services
- `resources/recipes/streamlabs_alert/manifest.json` and `tip_alert.md` - new product, Streamlabs Alerts
- `resources/recipes/kofi_alert/manifest.json` and `tip_alert.md` - new product, Ko-fi Alerts
- `resources/recipes/bmac_alert/manifest.json` and `tip_alert.md` - new product, Buy Me a Coffee Alerts
- `resources/recipes/fourthwall_alert/manifest.json` and `tip_alert.md` - new product, Fourthwall Alerts
- `resources/recipes/throne_alert/manifest.json` and `tip_alert.md` - new product, Throne Alerts
- `app/Support/ServiceConnections.php` - new: per-service connect kind and route, and the row the page gets
- `app/Http/Controllers/ProductController.php` - `installedView()` takes the slug and adds `services` and `your_overlays`; new `yourOverlays()` reads the access log
- `app/Http/Controllers/Settings/DonationIntegrationController.php` - `rememberReturnTo()` and `returnTo()`
- `app/Http/Controllers/Settings/StreamLabsIntegrationController.php` - `redirect()` remembers `return_to`; every exit of `callback()` goes through `returnTo()`
- `app/Http/Controllers/Settings/FourthwallIntegrationController.php` - the same for `redirect()` and `callback()`
- `app/Services/Recipes/RecipeInstaller.php` - a comment in `assertNoAlertTriggerCollisions()` no longer names Donation Alerts; no code change
- `resources/js/pages/products/show.vue` - the finished band restyled from the Alert Connected canvas; a services beat, an OBS beat offering the person's own overlays for an alert-only product, one test-tip beat per connected service; the services block above the checklist while steps are left; `PartyPopper` import dropped; `noteLink` constant
- `resources/js/components/ProductServices.vue` - new: one row per service with its connect inline
- `tests/Feature/ProductDonationAlertTest.php` - deleted with the product
- `tests/Feature/ProductDonationServicesTest.php` - new file, 30 tests across the five products
- `tests/Feature/ProductServiceConnectTest.php` - new file, 14 tests
- `tests/Feature/ProductInstallTest.php`, `ProductTowerTest.php`, `ProductBowlingTest.php` - the listed slugs and list indices, now eight products sorted by slug

### Claims
- **C1** [code] `RecipeCatalog::listed()` yields eight slugs in this order: `bmac_alert`, `chat_checkin`, `chat_tower`, `follower_bowling`, `fourthwall_alert`, `kofi_alert`, `streamlabs_alert`, `throne_alert`; `donation_alert` is not among `all()`.
- **C2** [code] Each of the five `*_alert` manifests declares one overlay (`tip_alert`, type alert), `installs.integrations` of exactly its service, one `alert_triggers` entry for that service and `donation`, no `alert_targets`, no `ingredients`, no `requires_integrations`, no `hero`, `max_instances_per_user` 1.
- **C3** [code] Each `tip_alert.md` carries the alert's stylesheet in its own `css` block (`.donation-alert` rules); the stage that used to carry it is gone with `donation_alert`.
- **C4** [code] `ProductController::installedView()` returns `services`: one entry per distinct `service` among the enabled external triggers of the product's alerts, in trigger order, kept only when `ServiceConnections::has()` knows the service.
- **C5** [code] `ProductController::yourOverlays()` returns `{loaded, overlays, total}`: for a product with a static overlay of its own, or a person with none, `loaded` false and `overlays` empty; otherwise `loaded` is whether any of the person's static overlays has a slug in `overlay_access_logs` rows of the person's tokens with `accessed_at` in the last 30 days, `overlays` is those when there are any and else the five most recently updated, and `total` is the count of all their static overlays.
- **C6** [code] `ServiceConnections::for()` reports `connected` true only when a row exists, is `enabled` and `isAuthenticated()`; `received` true only when `last_received_at` is set; `webhook_url` null for `streamlabs` and `fourthwall`; `connect_url` the service's `redirect` route with `return_to=/products/<slug>` for those two, and `kofi.save`, `bmac.save` or `throne.connect` for the other three.
- **C7** [code] `DonationIntegrationController::rememberReturnTo()` stores `return_to` under `integration_return_to.<service>` only when it matches `^/(?![/\\])\S*$`, and forgets that key otherwise; `returnTo()` pulls it and falls back to the service's show route.
- **C8** [code] Neither `StreamLabsIntegrationController::callback()` nor `FourthwallIntegrationController::callback()` contains `redirect()->route('settings.integrations.<service>.show')`; every exit is `$this->returnTo()`.
- **C9** [code] `ProductServices.vue` renders per row: an `<a>` to `connect_url` for `oauth` while not connected; a POST button for `none` and `secret` while there is no `webhook_url`; a field posting `verification_token` for `token` while `has_credential` is false; the link with a Copy button while `webhook_url` is set and `received` is false; a field posting `webhook_secret` for `secret` while a row exists and `has_credential` is false.
- **C10** [code] In `show.vue` the finished band's beats are, in order and each only when it applies: the services block (`services.length`), the product's own stage into OBS (`stages.length`), the person's own overlays into OBS (`!stages.length && alerts.length`), one test-tip beat per entry of `testGuides`, and "Watch it land" (`alerts.length`). The own-overlays beat says the alert already shows inside up to three named overlays plus a count when `your_overlays.loaded`, offers an OBS-tab link per listed overlay plus "and N more" otherwise, and a "Make an overlay" link when there are none.
- **C11** [code] The services block's heading reads `Connect <label>` when there is one service and "Connect the services you take tips on" otherwise; the pill reads `<names> connected`; with steps left the same block renders above the checklist when `services.length`.
- **C12** [code] The finished `<section class="product-ready">` carries no background or border class; each beat is a square card with `border-sidebar-border`, `bg-sidebar` in light and `bg-black/35` in dark; the OBS links are blue outline pills and every other button a neutral outline pill; the landed line is a green pill with `product-glow`, the waiting line a neutral pill with a `bg-blue-300` dot on `product-pulse`; the landed line's text is unchanged.
- **C13** [code] On the not-installed view, "You still do" says "Add the overlay to OBS" only for a product with a static overlay, and "Have one of your overlays in OBS" for a product whose overlays are all alerts.
- **C14** [test] `ProductDonationServicesTest` asserts, for each of the five products: the page's name, no question, one alert overlay and its service; the manifest shape of C2; the install creating one alert with `.donation-alert` in its css, one enabled trigger, its integration row and no targets; the refusal sentence naming the product and its own event when an alert on that service exists switched off. It also asserts two products installing side by side next to a hand-made Throne alert, the installed view with `your_overlays`, one service row, the test guide and `more_events`, the own-overlays answer switching from the five newest of seven to the one a link served three days ago while a forty-day-old serve is ignored, `landed` ignoring another service and another event type and a pre-install tip, a switched-off trigger emptying `fires_on` and `services`, the setup banner targeting `integration-kofi`, an existing row not duplicated, and uninstall leaving the integration.
- **C15** [test] `ProductServiceConnectTest` asserts Ko-fi's row before and after a token posted from the product page (redirect back, row ticked, integration wire satisfied), the empty-token refusal, Throne connected by the install and reconnected by one POST after its row is deleted, Buy Me a Coffee's link from the install and its secret posted from the page, the Streamlabs `return_to` round trip on success and on cancel with the key consumed, four rejected `return_to` values, the unconfigured Fourthwall return, and `services == []` for Chat Tower.
- **C16** [unverified] On `overlabels.test` on 2026-09-15, before the split, the band rendered five rows with Connect buttons and a Ko-fi test webhook rewrote the landed line without a reload. The split itself was not viewed in a browser.

### Unchanged
- `WiringFacts::productSubject()` still builds the integration wire from `installs.integrations`; with one service per product that is the product's own service, and the wire is not in the diff.
- `ServiceTestGuides`, `ProductSetup`, `RecipeInstaller`'s logic, `RecipeManifestValidator` and the five settings pages' own forms are not in the diff; the product page posts to the save routes those pages already use.
- The refusal sentence in `RecipeInstaller::assertNoAlertTriggerCollisions()` is the OL-2609-083 sentence; only its comment changed.
- Chat Checkin, Chat Tower and Follower Bowling manifests are untouched; their pages gain nothing but the restyled band.

### Risk
- `/products/donation_alert` is gone. An account with it installed keeps its instance, its alert, its stage and its five triggers, and those triggers refuse the new per-service products until removed. Uninstall Donation Alerts from its page before this deploys, or delete its triggers afterwards.
- The save flashes the settings pages set now also show as toasts on the product page.
- A Streamlabs or Fourthwall round trip started from a product page ends there, also on an error; started from the settings page it ends on the settings page as before.
