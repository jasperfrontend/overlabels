## OL-2609-017 - fix(kofi): re-saving the settings page no longer demands the stored verification token

**Shipped:** 2026-09-02
**Commit:** `git log --grep=OL-2609-017`

### Surface
- `app/Http/Controllers/Settings/KofiIntegrationController.php` - `verification_token` required only on first connect; empty keeps the stored token
- `resources/js/pages/settings/integrations/kofi.vue` - `save()` resets the token field on success
- `tests/Feature/KofiResaveKeepsTokenTest.php` - new file

### Claims
- **C1** [code] `KofiIntegrationController::save()` validates `verification_token` with `Rule::requiredIf(! $hadToken)` plus `nullable`, where `$hadToken` is whether the user's existing Ko-fi integration already holds a decrypted `verification_token`.
- **C2** [code] `save()` calls `setCredentialsEncrypted()` only when the submitted token is non-empty, so an empty field leaves the stored credential untouched.
- **C3** [code] `save()` still merges `enabled_events` into `settings` and still forces `enabled` on first connect, exactly as before the change.
- **C4** [code] `kofi.vue` `save()` passes `onSuccess: () => form.reset('verification_token')`, mirroring `bmac.vue`, so the "(token saved - enter new to replace)" placeholder shows after a save.
- **C5** [test] `KofiResaveKeepsTokenTest` asserts the first connect with an empty token errors on `verification_token`, a re-save with an empty token keeps the stored token and applies `enabled_events`, and a re-save with a new token replaces it.
- **C6** [unverified] The second of those three tests was run against the pre-fix controller and failed; the other two passed on both trees.

### Unchanged
- `BmacIntegrationController::save()`, whose nullable-secret shape this copies, is not in the diff.
- `DonationIntegrationController::connectIntegration()` and `provision()` are not in the diff; C3 relies on them being called the same way.
