## OL-2609-078 - fix(products): the OBS button keeps its #tab-obs on a same-tab click

**Shipped:** 2026-09-14
**Commit:** `git log --grep=OL-2609-078`

### Surface
- `resources/js/composables/useUiMode.ts` - `withLastMileHint()` added
- `resources/js/composables/useUiMode.test.ts` - `withLastMileHint` block added
- `resources/js/pages/products/show.vue` - the OBS button href built with `withLastMileHint()` and `urlWithTab()`

### Claims
- **C1** [code] `withLastMileHint(href, slug)` returns `href` with `product=<slug>` and `state=product` set in its query string, every key in alphabetical order, values encoded with `encodeURIComponent`, and the fragment unchanged.
- **C2** [code] `products/show.vue` builds each "Add ... to OBS" href as `urlWithTab(withLastMileHint(route('templates.show', overlay.id), product.slug), 'obs')` and contains no literal `state=product`.
- **C3** [code] Inertia 3.7.0's `setHashIfSameUrl()` (`node_modules/@inertiajs/core/dist/index.js`) copies the request fragment onto the response URL only when the request URL without its fragment equals the response URL as a string, unless the page response carries `preserveFragment`.
- **C4** [code] `inertia-laravel` 3.3.1 builds the page URL from `Request::fullUrl()` (`Response::getUrl()`), whose query string is Symfony's `normalizeQueryString()`, which `ksort`s the parameters.
- **C5** [code] `inertia-laravel` 3.3.1 sets `preserveFragment` on a page response only from a session key flashed by the `RedirectResponse::preserveFragment()` macro; nothing sets it for a GET link.
- **C6** [test] `useUiMode.test.ts` asserts `withLastMileHint('/templates/347', 'donation_alert')` is `/templates/347?product=donation_alert&state=product`, that an existing query and fragment survive with all keys sorted, that an existing hint is replaced rather than doubled, and that the result round-trips through `parseUiMode()`.
- **C7** [unverified] On `overlabels.test`, before this change, a same-tab click on "Add Donation stage to OBS" landed on `/templates/347?product=donation_alert&state=product` with no fragment and the default tab open; a middle-click on the same button kept `#tab-obs`. Reported by Jasper, 2026-09-14.
- **C8** [unverified] The same-tab click was not re-tested in a browser after the change.

### Unchanged
- `useAddressableTabs.ts` is what reads `#tab-obs` on arrival and is what the fragment now reaches; `tabKeyFromHash()`, `urlWithTab()` and `useAddressableTabs()` are not in the diff. Corrects the reach of OL-2609-072 C26, whose href was the literal this replaces.
- `ProductSetup::step()` builds the setup banner's `#el-<target>` links from `route()` with no query string, so those URLs already matched their echo and are not in the diff.
- `parseUiMode()` reads the hint by key, in any order, and is not in the diff.
