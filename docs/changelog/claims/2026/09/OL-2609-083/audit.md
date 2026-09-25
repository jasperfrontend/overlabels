## Audit of OL-2609-083 - fix(products): the trigger-collision refusal names the product's rule, not the person's pick

**Audited:** 2026-09-25
**Commit:** ceb4530ddc9ea26c68becae97e4e17bccae8d752
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/Recipes/RecipeInstaller.php:563-566 @ceb4530` - throws `"{$manifest['name']} fires on {$label} too, and your alert '{$existing->template->name}' already does. "` . `'Delete that trigger on its Triggers tab, switching it off is not enough, then install again.'`; the diff's only hunk in this file (`@@ -555,9 +555,14 @@`) adds a comment and replaces the two message lines, so the queries (`:541-554`) and the `foreach` (`:536`) are untouched. Same sentence @HEAD `:563-564`; comment reworded by OL-2609-084 |
| C2 | CONFIRMED | `app/Services/Recipes/RecipeInstaller.php:541-553 @ceb4530` - both queries filter only `user_id`, `event_type` (and `service` for external) and `->first(fn ($m) => $m->template !== null)`; no `enabled` condition. Neither `EventTemplateMapping` nor `ExternalEventTemplateMapping` registers a global scope @ceb4530 (their `enabled` filters at `:168` / `:179` are inside their own query methods). Same @HEAD `:542-553` |
| C3 | CONFIRMED | `tests/Feature/ProductDonationAlertTest.php:163-183 @ceb4530` - Throne `donation` mapping on the user's own alert with `'enabled' => false` (`:172`), POST install with `service` `kofi` (`:176`), `assertSessionHasErrors` with the exact new sentence (`:178`), no `RecipeInstance`, no `ExternalIntegration`, mapping count 1 (`:180-182`). Ran `php artisan test tests/Feature/ProductDonationAlertTest.php --filter='even switched off'` on a scratch checkout of ceb4530: 1 passed (7 assertions). File deleted @HEAD by OL-2609-084 |
| C4 | UNVERIFIABLE | tagged [unverified]; a prod observation, correctly tagged |

### Surface
Complete.

### Findings
None.

### Notes
- @HEAD `ProductDonationAlertTest.php` no longer exists and the comment in `assertNoAlertTriggerCollisions()` no longer names Donation Alerts; both are disclosed in OL-2609-084's Surface. Its successor assertion is `tests/Feature/ProductDonationServicesTest.php:121,127 @HEAD` (not run here).
- The C3 run was on a scratch worktree with no `public/build`; 9 other tests in that file failed there with `ViteManifestNotFoundException`, an artefact of the missing build, not of this change. The audited test does not render a page and passed.
- Unchanged line confirmed: the diff touches only the throw's message and a comment; which installs are refused (OL-2609-082 C5) is unchanged.
