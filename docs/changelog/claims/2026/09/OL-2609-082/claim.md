## OL-2609-082 - feat(products): Donation Alerts fires on every donation service, and its stage reads across them

**Shipped:** 2026-09-14
**Commit:** `git log --grep=OL-2609-082`

### Surface
- `resources/recipes/donation_alert/manifest.json` - five literal donation triggers; question reworded; description, changelog and notes rewritten
- `resources/recipes/donation_alert/stage.md` - reads two expression controls of its own instead of the picked service's tags; Controls and Requirements sections written
- `resources/recipes/donation_alert/tip_alert.md` - prose only: one alert serves all five services
- `tests/Feature/ProductDonationAlertTest.php` - five tests added, six rewritten, docblock updated
- `docs/changelog/changelog-2026-09.md` - prose entry

### Claims
- **C1** [code] `installs.alert_triggers` in the manifest lists five entries, services `streamlabs`, `kofi`, `bmac`, `fourthwall`, `throne` in that order, every one `event_type` `donation` on overlay `tip_alert`; `installs.integrations` is still `["{{service}}"]`; the `service` ingredient's question is `Which service should we connect first?`; `max_instances_per_user` is still 1.
- **C2** [code] `stage.md`'s html reads `[[[c:tips_total]]]` and `[[[c:newest_donor]]]` and contains neither a `c:<service>:` tag nor a `{{service}}` placeholder; the css is unchanged.
- **C3** [code] `stage.md` declares two `expression` controls: `tips_total`, `sum()` over the five services' `donations_received`, and `newest_donor`, `latest()` over the five `donations_received_at` / `latest_donor_name` pairs. `OverlayMarkdown::parse()` yields both, and `OverlayControl::extractExpressionDependencies()` gives five dependencies for the first and ten for the second.
- **C4** [code] `RecipeManifestValidator::validateFile()` on the rewritten manifest returns valid, with the directory given.
- **C5** [code] `RecipeInstaller::installAlertTriggers()` creates one `ExternalEventTemplateMapping` per manifest trigger, so an install creates five enabled rows on the alert; `RecipeInstaller::assertNoAlertTriggerCollisions()` therefore refuses the install when an existing alert fires on any of the five services.
- **C6** [test] `ProductDonationAlertTest` asserts: the manifest's five triggers; five mapping rows on the alert with the picked service alone connected; the stage's two expression controls with every service in their dependencies; `fires_on` naming all five; a Throne tip landing on a Ko-fi install; a hand-built Throne alert refusing a Ko-fi install with the alert named; uninstall removing the stage's controls.
- **C7** [unverified] Against the pre-change recipe files (stashed), 8 of the 19 tests failed and 11 passed; `reads the alert wiring live` passes both ways because the one trigger the old install wrote is the Ko-fi one the test keeps enabled.
- **C8** [unverified] The stage re-evaluating its two controls on a live control update, and the finished page's "It landed" flip on a tip from a second service, were not exercised in a browser. The render query in `OverlayTemplateController` ships every user-scoped source-managed control with its `_at` companion regardless of the template's tags, the same path Chat Tower's stage relies on.

### Unchanged
- No PHP or Vue in the diff. `RecipeIngredients`, `RecipeInstaller`, `RecipeManifestValidator`, `ProductController::installedView()` and `products/show.vue` are as OL-2609-080 left them; the page's "Fires on" line reads the mapping rows live, which is why it now names five services with no page change.
- `ServiceTestGuides::firstFor()` still keys on `installs.integrations`, so beat 2 of the finished band is still the picked service's steps.

### Risk
- The catalogue upserts on (slug, version) and the version stays 1, so the next install replaces the stored manifest in place. An instance installed before this change keeps its single trigger and its `c:<service>:` stage; the page reads both live and shows them as they are. Uninstall and install again to get the five triggers and the new stage.
- Refuse-not-merge now covers all five services: a streamer with a hand-built alert on any donation service is refused until that trigger is removed, where before only the picked service collided.
