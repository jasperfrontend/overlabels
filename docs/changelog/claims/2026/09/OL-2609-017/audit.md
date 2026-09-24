## Audit of OL-2609-017 - fix(kofi): re-saving the settings page no longer demands the stored verification token

**Audited:** 2026-09-24
**Commit:** c2ea820a704920002266346b815dc3c9a7ce92ee
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Http/Controllers/Settings/KofiIntegrationController.php:25-26 @c2ea820` - `$existing = $this->integration($user); $hadToken = ! empty($existing?->getCredentialsDecrypted()['verification_token']);`; `:33 @c2ea820` - `[Rule::requiredIf(! $hadToken), 'nullable', 'string', 'max:255']`; `git diff c2ea820 HEAD` on the file is empty, same @HEAD |
| C2 | CONFIRMED | `KofiIntegrationController.php:43-48 @c2ea820` - `setCredentialsEncrypted()` is inside `if ($newToken !== null && $newToken !== '')`; no other call in `save()`; same @HEAD |
| C3 | CONFIRMED | `KofiIntegrationController.php:51-57 @c2ea820` - `array_merge` of `enabled_events` (default `['donation','subscription','shop_order']`) and `$integration->enabled = $isNew \|\| (...)` are byte-identical to `:40-46 @c2ea820^`; `$isNew = ! $existing` (`:39`) evaluates the same `$this->integration($user)` lookup the parent did at `:31 @c2ea820^`, before `connectIntegration()`; same @HEAD |
| C4 | CONFIRMED | `resources/js/pages/settings/integrations/kofi.vue:110 @c2ea820` - `onSuccess: () => form.reset('verification_token')`; placeholder `'(token saved - enter new to replace)'` at `:190 @c2ea820`; `bmac.vue:112 @c2ea820` - `onSuccess: () => form.reset('webhook_secret')`. @HEAD the line is `kofi.vue:107`, unchanged in content (file later touched by OL-2609-019) |
| C5 | CONFIRMED | `tests/Feature/KofiResaveKeepsTokenTest.php @c2ea820` - three tests: empty token on first connect `assertSessionHasErrors('verification_token')` and no row created; empty re-save keeps `'first-token'` and `settings['enabled_events']` is `['donation']`; new token re-save stores `'second-token'`. `php artisan test --filter=KofiResaveKeepsTokenTest` @HEAD: 3 passed (10 assertions); file unchanged since c2ea820 |
| C6 | UNVERIFIABLE | tagged [unverified] (fail-first run against a pre-fix tree) |

### Surface
Complete.

### Findings
None.

### Notes
- Unchanged lines confirmed: `BmacIntegrationController.php` and `DonationIntegrationController.php` are absent from `git show --stat c2ea820`. `DonationIntegrationController.php` was later modified by OL-2609-084 (08298f12), which carries its own claim.
- `kofi.vue` was later modified by OL-2609-019 (f8f6b149, TestModeToggle extraction); the `save()` body asserted by C4 is unchanged at HEAD.
