## Audit of OL-2609-077 - feat(products): Donation Alerts, the first product that asks which service

**Audited:** 2026-09-25
**Commit:** 50f668203fbdce22c55105b57eb76a01b21c8846
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/recipes/donation_alert/manifest.json:13,14,21 @50f6682` - `requires_bot: false`, `listed: true`, `max_instances_per_user: 1`; no `requires_integrations` key anywhere in the file. @HEAD the file is deleted (OL-2609-084) |
| C2 | CONFIRMED | `manifest.json:22-35 @50f6682` - one ingredient, `key` `service`, choices `streamlabs`, `kofi`, `bmac`, `fourthwall`, `throne`, `default` `streamlabs` (line 33). Deleted @HEAD (OL-2609-084) |
| C3 | CONFIRMED | `manifest.json:41-43 @50f6682` - `integrations: ["{{service}}"]`; one trigger `{overlay: tip_alert, service: {{service}}, event_type: donation}`; `alert_targets: [{alert: tip_alert, overlays: [stage]}]`. Changed by OL-2609-080 and OL-2609-082, deleted by OL-2609-084 |
| C4 | CONFIRMED | `resources/recipes/donation_alert/stage.md:3 @50f6682` `type: static`; `:33-35` read `[[[c:{{service}}:donations_received]]]`, `[[[if:c:{{service}}:latest_donor_name]]]`, `[[[c:{{service}}:latest_donor_name]]]`. Changed by OL-2609-082, deleted by OL-2609-084 |
| C5 | CONFIRMED | `stage.md:79-99 @50f6682` - `.alert`, `.alert p`, `.alert .donation-message` rules in the css block; `tip_alert.md:42 @50f6682` css field reads "Empty." |
| C6 | CONFIRMED | `resources/recipes/donation_alert/tip_alert.md:3 @50f6682` `type: alert`; html `:32-36` reads only `event.from_name`, `event.formatted_amount`, `event.message`, no `{{`; `:50` sound URL, `:51` TTS after 3000ms, `:52` chat line identical to the TTS line. Changed by OL-2609-082, deleted by OL-2609-084 |
| C7 | CONFIRMED | `app/Models/ExternalEventTemplateMapping.php:109-145 @50f6682` - all five keys carry `donation`; `getAutoProvisionedControls()` in `BMACServiceDriver.php:165-166`, `FourthwallServiceDriver.php:121-122`, `KofiServiceDriver.php:135-136`, `StreamLabsServiceDriver.php:130-131`, `ThroneServiceDriver.php:163-164` @50f6682 list `donations_received` and `latest_donor_name`; same keys present @HEAD |
| C8 | CONFIRMED | `git ls-tree 50f6682 public/products` - `chat-checkin-hero.svg`, `chat-tower-hero.svg`, `follower-bowling-hero.svg` only; manifest has no `hero` key @50f6682 |
| C9 | CONFIRMED | `tests/Feature/ProductDonationAlertTest.php @50f6682` holds 10 `it()` blocks (lines 37, 47, 67, 73, 100, 110, 123, 135, 149, 162) matching the ten cases listed; `php artisan test --filter=ProductDonationAlertTest` on a checkout of 50f6682: 10 passed. File deleted @HEAD (OL-2609-084) |
| C10 | CONFIRMED | `tests/Feature/ProductInstallTest.php:49 @50f6682` `toBe(['chat_checkin', 'chat_tower', 'donation_alert', 'follower_bowling'])`, `:151` `has('products', 4)`; 18 passed on 50f6682. Both lines changed since by OL-2609-084, OL-2609-088, OL-2609-089, OL-2609-114 |
| C11 | UNVERIFIABLE | tagged [unverified]; see F1 - the substance is checkable in-repo |
| C12 | UNVERIFIABLE | tagged [unverified] (browser behaviour) |

### Surface
Complete.

### Findings
- **F1** mistagged, checkable as [code]/[test] - C11 claims the manifest passes `RecipeManifestValidator::validateFile()` and both documents parse after `RecipeIngredients::fill()` with `service` = `kofi`; both halves are in-repo facts (`validateFile()` on `resources/recipes/donation_alert/manifest.json` returned `valid => true, errors => []` in tinker on a checkout of 50f6682, and `ProductDonationAlertTest.php:73 @50f6682` installs with `kofi`, which parses both documents), so only "run in tinker before the tests were written" is unverifiable; a corrective claim should restate the validation as [code] or [test] and keep the timing as [unverified].

### Notes
- Tests were run on a `git archive` of 50f6682 in the scratchpad, with `composer install` at that commit's lock (HEAD's vendor lacks `stevebauman/location`, removed by OL-2609-097) and a `public/hot` stub in place of a Vite build. `ProductDonationAlertTest|ProductInstallTest|ProductBowlingTest`: 38 passed, 320 assertions.
- The whole product - three recipe files and `ProductDonationAlertTest.php` - was deleted by OL-2609-084 (split into five per-service products); none of C1-C9 describe HEAD.
- C9's "a refused `paypal` creating nothing": `ProductDonationAlertTest.php:118-120 @50f6682` asserts no `RecipeInstance`, `OverlayTemplate` or `ExternalIntegration` row; it does not assert the absence of an `ExternalEventTemplateMapping` row.
- Unchanged line 3 says "The other three manifests under `resources/recipes/`"; @50f6682 there are five others (`chat_checkin`, `chat_tower`, `coin_flip`, `dice`, `follower_bowling`), none in the diff. "Three" matches only the listed products.
- OL-2609-076 Unchanged "No manifest in `resources/recipes/` declares `ingredients`" is superseded by this change, as expected; `IntegrationController::productIntegrations()` (`app/Http/Controllers/Settings/IntegrationController.php:159-170 @50f6682`) is not in the diff and reads only `requires_integrations`.
