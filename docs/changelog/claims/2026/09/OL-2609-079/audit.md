## Audit of OL-2609-079 - fix(products): the finished band adds only static overlays to OBS and says how an alert is wired

**Audited:** 2026-09-25
**Commit:** d2c088319a8a2f38cc04bc1e0b5b899390393d0e
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Http/Controllers/ProductController.php:278 @d2c0883` - overlay array carries `'type' => $template->type`; same at `:669 @HEAD` (the method gained a `$slug` parameter in OL-2609-084) |
| C2 | CONFIRMED | `app/Http/Controllers/ProductController.php:281-293 @d2c0883` - `EventTemplateMapping::where('template_id', ...)->where('enabled', true)` mapped to `event_type_display` (`app/Models/EventTemplateMapping.php:142 @d2c0883`), concatenated with `ExternalEventTemplateMapping::where('overlay_template_id', ...)->where('enabled', true)` mapped through `SERVICE_EVENT_TYPES` (fallback `"{service} {event_type}"` for an unlisted pair); `targets` = `targetStaticOverlays()->pluck('name')`. Same logic at `:672-683 @HEAD` |
| C3 | CONFIRMED | `app/Http/Controllers/ProductController.php:280 @d2c0883` - both keys are set only inside `if ($template->type === 'alert')`; same at `@HEAD` (alerts additionally get `more_events` since OL-2609-080) |
| C4 | CONFIRMED | `resources/js/pages/products/show.vue:135 @d2c0883` - `stages` filters `overlay.type !== 'alert'`; `:266` `v-for="overlay in stages"` on the "Add ... to OBS" link. `@HEAD` the band is restructured into beats (OL-2609-080, OL-2609-084) and a second "Add ... to OBS" loop at `:420-465` iterates `yourOverlays.overlays` for alert-only products, disclosed by OL-2609-084 C10 |
| C5 | CONFIRMED | `resources/js/pages/products/show.vue:280-298 @d2c0883` - `Link :href="withLastMileHint(route('templates.show', alert.id), product.slug)"` with no `urlWithTab` (the only `#tab-` source, `useAddressableTabs.ts:39`); `fires_on` line with "Has no trigger switched on yet" fallback at `:293`; `targets` line with "Shows inside every static overlay of yours" fallback at `:298`. Same content at `:553-578 @HEAD`, restyled by OL-2609-080/084 |
| C6 | CONFIRMED | `resources/js/pages/products/show.vue:352 @d2c0883` - `installed.overlays.length === 1 ? 'Your overlay' : 'Your overlays'`; same at `:686 @HEAD` |
| C7 | CONFIRMED | `tests/Feature/ProductDonationAlertTest.php:132-138 @d2c0883` asserts exactly the stated names, types, `missing('installed.overlays.0.fires_on')`, `fires_on` and `targets`. Ran `php artisan test --filter=ProductDonationAlertTest` on an export of d2c0883: 11 passed (119 assertions). File rewritten by OL-2609-082, deleted @HEAD by OL-2609-084 |
| C8 | CONFIRMED | `tests/Feature/ProductDonationAlertTest.php:145-149 @d2c0883` - after a `kofi` install sets every `ExternalEventTemplateMapping` of the user to `enabled => false` and asserts `installed.overlays.1.fires_on` is `[]`; passed in the same run. Deleted @HEAD (OL-2609-084) |
| C9 | UNVERIFIABLE | tagged [unverified] |
| C10 | UNVERIFIABLE | tagged [unverified] |

### Surface
Complete.

### Findings
- **F1** narrows an earlier claim without citing it - OL-2609-044 C2 records that the finished band renders "one `Link` to `route('templates.show', overlay.id)` per entry of `installed.overlays`"; this change iterates `stages` instead (`resources/js/pages/products/show.vue:266 @d2c0883`, which drops alerts), and the claim does not cite OL-2609-044 anywhere. A follow-up claim should state "narrows OL-2609-044 C2" so the record reads backward correctly.

### Notes
- The C7/C8 test run needed an export of d2c0883 outside the repo (HEAD's `vendor` hardlinked in, stale tracked `bootstrap/cache/*.php` removed, HEAD's `public/build` copied in). Before `public/build` was added, the four page-GET tests failed with "Not a valid Inertia response". No repo file was touched.
- `ProductDonationAlertTest.php` does not exist @HEAD. OL-2609-082 rewrote its `fires_on` assertions (five services), and OL-2609-084 deleted it with the product. The equivalent coverage now lives in `ProductDonationServicesTest` (OL-2609-084 C14), which passes @HEAD (31 tests).
- The C8 test disables every `ExternalEventTemplateMapping` row the user has, not one chosen by install. With a single install, those are the same rows.
