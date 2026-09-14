## OL-2609-080 - feat(products): after install, three beats to a landed alert - OBS, a test tip from the service, and the page noticing it

**Shipped:** 2026-09-14
**Commit:** `git log --grep=OL-2609-080`

### Surface
- `app/Support/ServiceTestGuides.php` - new file, per-service "send a test event" guides
- `app/Http/Controllers/ProductController.php` - `installedView()` adds `test_guide`, `landed` and per-alert `more_events`; `landed()` added
- `resources/js/pages/products/show.vue` - the finished band is three numbered beats; listens for `alert.triggered` on the account's alerts channel
- `resources/recipes/donation_alert/manifest.json` - `ready_message` and the first note reworded
- `tests/Feature/ServiceTestGuidesTest.php` - new file
- `tests/Feature/ProductDonationAlertTest.php` - four tests added
- `tests/Feature/ProductInstallTest.php` - the Chat Checkin installed-page test asserts no guide and no landing

### Claims
- **C1** [code] `ServiceTestGuides::for()` returns a guide for exactly `kofi`, `streamlabs`, `bmac`, `fourthwall` and `throne`, and null for any other key.
- **C2** [code] Every guide carries `service`, `service_label` from `ExternalServiceRegistry::displayName()`, an `https://` `url`, a non-empty `label`, a non-empty `steps` list, and `settings_url` equal to `route('settings.integrations.<service>.show')`.
- **C3** [code] The `bmac` and `throne` guides name this install's own webhook prefix, `url('/api/webhooks/<service>').'/'`, substituted for `{webhook_prefix}` in `for()`; no returned step contains the placeholder.
- **C4** [code] `ServiceTestGuides::firstFor()` returns the guide of the first service in the list that has one, else null.
- **C5** [code] `ProductController::installedView()` sets `test_guide` to `ServiceTestGuides::firstFor()` of the instance's resolved `installs.integrations`.
- **C6** [code] For each alert overlay, `installedView()` sets `more_events` to the labels in `ExternalEventTemplateMapping::SERVICE_EVENT_TYPES` for every resolved integration service whose event type has no enabled mapping to that alert.
- **C7** [code] `ProductController::landed()` returns the latest `ExternalEvent` of the instance's user with `created_at` on or after the instance's `created_at` whose `(service, event_type)` is one an installed alert has an enabled `ExternalEventTemplateMapping` for, as `from_name` and `formatted_amount` read from `normalized_payload['event.from_name']` and `['event.formatted_amount']`; null when there is none or no alert fires on anything.
- **C8** [code] In `products/show.vue` the finished band renders an `<ol class="product-beats">` whose items are: the OBS beat when a static overlay exists, the test-tip beat when `installed.test_guide` is set, and the "Watch it land" beat when an alert exists; numbering is a CSS counter.
- **C9** [code] The test-tip beat's service link carries `target="_blank"` and `rel="noopener"`, lists `test_guide.steps` as an ordered list, and links `test_guide.settings_url` in the sentence about test mode.
- **C10** [code] The "Watch it land" beat shows `landed` from the server on load and, on mount, calls `window.Echo.private('alerts.<twitch_id>').listen('.alert.triggered', ...)` when the page is installed, has an alert, and `page.props.auth.user.twitch_id` and `window.Echo` exist; the handler sets `landed` only when the broadcast's `alert.alert_template_slug` is one of the product's alert slugs; unmount calls `stopListening`, not `leave`.
- **C11** [code] Under each alert, a line naming `more_events` links to `templates.show` for the alert with the last-mile hint and `#tab-triggers`, and renders only when `more_events` is non-empty.
- **C12** [code] `products/show.vue` disables `.product-pulse` and `.product-landed` animation under `prefers-reduced-motion: reduce`.
- **C13** [test] `ServiceTestGuidesTest` contains 4 tests covering C1 through C4.
- **C14** [test] `ProductDonationAlertTest` asserts, after a `kofi` install, `test_guide.service` `kofi`, `test_guide.url` `https://ko-fi.com/manage/webhooks`, three steps, `landed` null, and `more_events` `['Ko-fi Subscription', 'Ko-fi Shop Order', 'Ko-fi Commission']`; after a `streamlabs` install, `more_events` `[]`.
- **C15** [test] `ProductDonationAlertTest` asserts a `kofi` `subscription` event after install leaves `landed` null, a `kofi` `donation` event after install sets `landed.from_name` `Jo` and `landed.formatted_amount` `EUR 5,00`, and a `donation` event dated before the install leaves `landed` null.
- **C16** [test] `ProductInstallTest` asserts `installed.test_guide` and `installed.landed` are null for Chat Checkin.
- **C17** [unverified] The guide steps were walked by hand by Jasper on 2026-09-14 at 22:55 from a desktop browser; Streamlabs was also checked on mobile.
- **C18** [unverified] The live flip of the "Watch it land" beat on a real broadcast was not exercised in a browser.

### Unchanged
- `TestModeToggle.vue` and its `how-to-fire` prop on the five settings pages still carry their own one-line hint; they do not read `ServiceTestGuides` and are not in the diff.
- `AlertTriggered::broadcastWith()` is what the page listens to and already carries `alert_template_slug` and `data`; it is not in the diff.
- `ExternalWebhookController` writes `normalized_payload` as the event's template tags (OL-2609-073 put `event.formatted_amount` in it); `landed()` reads that and nothing was added to the write.
- The integration test-mode switch is not touched by an install; a second identical test press is still dropped as a retry unless the streamer switches test mode on.

### Risk
The product page now opens a websocket subscription on the account's alerts channel while an installed product with an alert is shown. Any alert firing on the account while the page is open is inspected by slug and ignored unless it is the product's.
