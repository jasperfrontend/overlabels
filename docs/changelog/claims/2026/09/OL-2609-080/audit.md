## Audit of OL-2609-080 - feat(products): after install, three beats to a landed alert - OBS, a test tip from the service, and the page noticing it

**Audited:** 2026-09-25
**Commit:** b87d723a3ae8cdec85f8d7e854ba9956be35a5e6 (sole commit carrying the trailer)
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Support/ServiceTestGuides.php:32-71 @b87d723` - `GUIDES` keys are exactly `kofi`, `streamlabs`, `bmac`, `fourthwall`, `throne`; `for()` returns null on `self::GUIDES[$service] ?? null` (:87-91). File identical @HEAD (`git diff b87d723 HEAD` empty) |
| C2 | CONFIRMED | `app/Support/ServiceTestGuides.php:96-103 @b87d723` - `service`, `service_label` from `ExternalServiceRegistry::displayName()`, `url` (all five literals start `https://`, :34-71), `label`, `steps`, `settings_url` = `route('settings.integrations.'.$service.'.show')`; all five `.show` routes exist in `routes/settings.php:142-182 @b87d723`. Same @HEAD |
| C3 | CONFIRMED | `app/Support/ServiceTestGuides.php:94,101 @b87d723` - `$prefix = url('/api/webhooks/'.$service).'/'`, `str_replace('{webhook_prefix}', ...)` over every step; placeholder appears only in the `bmac` and `throne` steps (:57, :74). Same @HEAD |
| C4 | CONFIRMED | `app/Support/ServiceTestGuides.php:112-122 @b87d723` - loops the list, returns the first non-null `for()`, else null. Same @HEAD |
| C5 | CONFIRMED | `app/Http/Controllers/ProductController.php:280,339 @b87d723` - `$services = array_values($instance->resolvedManifest()['installs']['integrations'] ?? [])`; `'test_guide' => ServiceTestGuides::firstFor($services)`. Same @HEAD (:656, :727) |
| C6 | CONFIRMED | `app/Http/Controllers/ProductController.php:296-318 @b87d723` - per service, labels from `SERVICE_EVENT_TYPES[$service]` whose event type is not among the alert's enabled `ExternalEventTemplateMapping` rows. Same @HEAD (:672-694) |
| C7 | CONFIRMED | `app/Http/Controllers/ProductController.php:354-381 @b87d723` - null on empty `$firing`; `user_id`, `created_at >= $instance->created_at`, OR over `(service, event_type)` pairs, `->latest()->first()`; `from_name`/`formatted_amount` from `normalized_payload['event.from_name']`/`['event.formatted_amount']`. Also returns `at` (see F1). Same @HEAD (:790-816) |
| C8 | CONFIRMED | `resources/js/pages/products/show.vue:325,329,353,381,557-562 @b87d723` - `<ol class="product-beats">`, beats gated on `stages.length`, `installed.test_guide`, `alerts.length`; `counter-reset`/`counter-increment: beat`. @HEAD the band has more beats and the test-tip beat iterates `testGuides` (OL-2609-084 C10) |
| C9 | CONFIRMED | `resources/js/pages/products/show.vue:359-360,367,371 @b87d723` - `target="_blank"`, `rel="noopener"`, `<ol>` over `installed.test_guide.steps`, `test mode` link to `installed.test_guide.settings_url`. @HEAD same shape on `guide.*` inside the `testGuides` loop (:504-521, OL-2609-084) |
| C10 | CONFIRMED | `resources/js/pages/products/show.vue:166,178,189-191,196 @b87d723` - `landed` ref seeded from `props.installed?.landed`; guard `!props.installed \|\| !alerts.value.length \|\| !twitchId \|\| !echo`; `echo.private(\`alerts.${twitchId}\`)`, `.listen('.alert.triggered', ...)`; handler returns unless `alertSlugs.value.has(slug)`; unmount `stopListening`, no `leave`. Same @HEAD (:217-247) |
| C11 | CONFIRMED | `resources/js/pages/products/show.vue:414-420 @b87d723` - `v-if="alert.more_events?.length"`, `urlWithTab(withLastMileHint(route('templates.show', alert.id), product.slug), 'triggers')`; `TAB_PREFIX = 'tab-'` in `useAddressableTabs.ts:21 @b87d723`. Same @HEAD (:581-583) |
| C12 | CONFIRMED | `resources/js/pages/products/show.vue:622-629 @b87d723` - `@media (prefers-reduced-motion: reduce)` sets `animation: none` on `.product-pulse` and `.product-landed`. Same @HEAD (:869-881) |
| C13 | CONFIRMED | `tests/Feature/ServiceTestGuidesTest.php @b87d723` - 4 tests (C1: five non-null, `checkin`/`tower`/`gps`/`nope` null; C2: every field; C3: bmac/throne contain prefix, no step contains placeholder; C4: `firstFor`). `php artisan test --filter=ServiceTestGuidesTest` passed 4/4 @b87d723 and @HEAD |
| C14 | CONFIRMED | `tests/Feature/ProductDonationAlertTest.php @b87d723` - "hands the finished page the test guide..." asserts service, url, 3 steps, `landed` null, `overlays.1.more_events` list; "has nothing more to offer..." asserts streamlabs `more_events` `[]`. Passed @b87d723. File deleted @HEAD by OL-2609-084 |
| C15 | CONFIRMED | `tests/Feature/ProductDonationAlertTest.php @b87d723` - "says the tip landed..." (subscription -> null; donation -> `Jo`, `EUR 5,00`) and "does not count a tip that arrived before the install". Passed @b87d723 (15/15 in the file). Deleted @HEAD by OL-2609-084 |
| C16 | CONFIRMED | `tests/Feature/ProductInstallTest.php:199-200 @b87d723` - `installed.test_guide` and `installed.landed` null on `/products/chat_checkin`. Passed @b87d723 and @HEAD |
| C17 | UNVERIFIABLE | tagged [unverified] |
| C18 | UNVERIFIABLE | tagged [unverified] |

### Surface
Complete.

### Findings
- **F1** scope - `ProductController::landed()` returns a third key, `'at' => $event->created_at?->toIso8601String()` (`app/Http/Controllers/ProductController.php:379 @b87d723`, `:813 @HEAD`), and `show.vue` types it (`Landed.at`) and sets it from the broadcast, but C7 names only `from_name` and `formatted_amount` and no claim or Surface line mentions `at`; a follow-up claim should record the field, or it should be dropped if nothing reads it.

### Notes
- Shipped-revision tests were run in a temporary worktree at b87d723 with `composer install` and HEAD's `public/build` copied in; the first run failed 11 tests on a missing Vite manifest, and all 37 tests in the three files passed once the manifest was there.
- The `fires_on` external-mapping query was moved into `$external` so `more_events` and `$firing` could reuse it. The output is the same, and OL-2609-079 C2 still holds at `ProductController.php:296-306 @b87d723`.
- OL-2609-084 deleted `resources/recipes/donation_alert/manifest.json` and `ProductDonationAlertTest.php` and disclosed both. The equivalent tests now live in `ProductDonationServicesTest`, which passed 31/31 @HEAD.
- Unchanged lines hold: `TestModeToggle.vue`, `AlertTriggered.php` (carries `data` and `alert_template_slug`, :106/:114 @b87d723) and `ExternalWebhookController.php` are not in the diff.
