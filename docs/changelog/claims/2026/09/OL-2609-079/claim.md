## OL-2609-079 - fix(products): the finished band adds only static overlays to OBS and says how an alert is wired

**Shipped:** 2026-09-14
**Commit:** `git log --grep=OL-2609-079`

### Surface
- `app/Http/Controllers/ProductController.php` - `installedView()` adds `type` to every installed overlay, and `fires_on` and `targets` to an alert
- `resources/js/pages/products/show.vue` - the finished band offers "Add ... to OBS" for static overlays only and lists each alert's trigger and target as done steps; "Your overlay" pluralises
- `tests/Feature/ProductDonationAlertTest.php` - the installed-page test asserts the overlay shapes; one test added for a switched-off trigger

### Claims
- **C1** [code] `ProductController::installedView()` returns each installed overlay with a `type` taken from `OverlayTemplate::$type`.
- **C2** [code] For an overlay whose `type` is `alert`, `installedView()` returns `fires_on` as the display labels of every `EventTemplateMapping` with `template_id` equal to the alert's id and every `ExternalEventTemplateMapping` with `overlay_template_id` equal to it, both filtered on `enabled` true, and `targets` as the names from `OverlayTemplate::targetStaticOverlays()`.
- **C3** [code] For an overlay whose `type` is not `alert`, `installedView()` returns no `fires_on` and no `targets` key.
- **C4** [code] In `products/show.vue`, the "Add ... to OBS" links in the finished band iterate `stages`, a computed that excludes overlays whose `type` is `alert`.
- **C5** [code] In `products/show.vue`, each alert in the finished band renders its name as a `Link` to `templates.show` carrying the last-mile hint and no `#tab-` fragment, one line for `fires_on` (or "Has no trigger switched on yet" when empty) and one line for `targets` (or "Shows inside every static overlay of yours" when empty).
- **C6** [code] The heading above the installed overlay list reads "Your overlay" for one overlay and "Your overlays" otherwise.
- **C7** [test] `ProductDonationAlertTest` asserts, after a `throne` install, that overlay 0 is `Donation stage` of type `static` with no `fires_on`, and overlay 1 is `Donation alert` of type `alert` with `fires_on` `['Throne Gift or Contribution']` and `targets` `['Donation stage']`.
- **C8** [test] `ProductDonationAlertTest` asserts that disabling the install's `ExternalEventTemplateMapping` row makes the page's `fires_on` for the alert an empty array.
- **C9** [unverified] On `overlabels.test`, before this change, the finished band showed "Add Donation alert to OBS", whose destination warned against adding an alert to OBS. Reported by Jasper, 2026-09-14.
- **C10** [unverified] The finished band was not re-checked in a browser after the change.

### Unchanged
- `WiringFacts::productSubject()`'s `product.token` wire tests for any active `OverlayAccessToken` on the account, not one per overlay, so the alert never held the checklist open; the wire and `WiringCatalog` copy are not in the diff.
- The "Your overlays" list under the checklist still lists every installed overlay, alert included, as a link to its page.
- The pre-install "You still do" list still says the overlay goes into OBS as a browser source, which is true of the stage.
- `RecipeInstaller::installAlertTriggers()` and `installAlertTargets()` write the rows this page now reads; neither is in the diff.
