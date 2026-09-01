## OL-2609-019 - feat(integrations): one TestModeToggle with plain-language copy, state in words, and a help page

**Shipped:** 2026-09-02
**Commit:** `git log --grep=OL-2609-019`

### Surface
- `resources/js/components/TestModeToggle.vue` - new file; the switch, its copy, the go-live warning and the help link
- `resources/js/pages/settings/integrations/kofi.vue` - inline test-mode block and `toggleTestMode()` replaced with `<TestModeToggle>`
- `resources/js/pages/settings/integrations/bmac.vue` - same
- `resources/js/pages/settings/integrations/fourthwall.vue` - same; unused `Label` import dropped
- `resources/js/pages/settings/integrations/streamlabs.vue` - same; unused `Label` import dropped
- `resources/js/pages/settings/integrations/throne.vue` - same
- `resources/help/pages/integration-test-mode.md` - new guide, `/help/integration-test-mode`
- `resources/help/pages/index.md` - links the new guide under "Look something up"

### Claims
- **C1** [code] `TestModeToggle.vue` renders the switch state as text, "Test mode is on" / "Test mode is off", next to a `FlaskConical` icon, so the state does not rely on the switch colour alone.
- **C2** [code] `TestModeToggle.vue` PATCHes `/settings/integrations/{service}/test-mode` with `{ test_mode }` and reverts its local state when the request fails, which is the same request and the same fallback the five deleted `toggleTestMode()` functions made.
- **C3** [code] The body copy in `TestModeToggle.vue` states all three effects of test mode: repeats are accepted, alerts and controls behave as for a real event, and nothing received counts toward usage.
- **C4** [code] While on, `TestModeToggle.vue` shows a warning that switching off resets the service's controls to their starting values, names the total's reset value from the `seedValue` prop, and says the latest supporter details are cleared - matching `DonationIntegrationController::setTestMode()` calling `ExternalControlService::resetServiceManagedControls()` on the off transition.
- **C5** [code] `TestModeToggle.vue` renders an `<a target="_blank" rel="noopener">` to `route('help.integration-test-mode')`.
- **C6** [code] None of the five integration pages defines `testMode`, `testModeLoading` or `toggleTestMode` any more; each passes `integration.test_mode` and `donationsSeedValue` into the component and nothing else about test mode.
- **C7** [code] Each page passes a different `how-to-fire` clause and Throne passes `total-label="gift total"`; the other four pass `"donation total"`.
- **C8** [code] `integration-test-mode.md` declares no `context:` and lists `keywords:` including `test mode`, `test donation`, `duplicate event` and `starting total`.
- **C9** [test] The existing `Help*` tests pass with the new page in place (linked from `index.md`, valid slug, no over-long heading).
- **C10** [unverified] In Chrome against the local dev server, the Ko-fi page rendered the component, toggling it off changed the text to "Test mode is off" and hid the warning, and `/help/integration-test-mode` rendered with a five-entry table of contents.

### Unchanged
- `DonationIntegrationController::setTestMode()` and `ExternalControlService::resetServiceManagedControls()` are not in the diff; the component describes what they already do.
- `ExternalWebhookController` (the `_test_` message-id suffix and the metering skip) is not in the diff.
- The starting-total block on each page, immediately below the new component, is not in the diff beyond the component being inserted above it.
