## Audit of OL-2609-078 - fix(products): the OBS button keeps its #tab-obs on a same-tab click

**Audited:** 2026-09-25
**Commit:** 4a53ba8f154f5913215ee8eac31e7becccc328a2
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/composables/useUiMode.ts:59-74 @4a53ba8` - splits off `#...` as `hash`, `params.set('product', slug)`, `params.set('state', 'product')`, `params.sort()`, joins each pair with `encodeURIComponent(key)=encodeURIComponent(value)`, returns `${path}?${query}${hash}`. Function body is identical @HEAD; the only later change to the file is `SLUG` on line 32 (OL-2609-114 C14) |
| C2 | CONFIRMED | `resources/js/pages/products/show.vue:246 @4a53ba8` - the one "Add {{ overlay.name }} to OBS" `Link` (line 250) has `:href="urlWithTab(withLastMileHint(route('templates.show', overlay.id), product.slug), 'obs')"`; `grep state=product` finds nothing in the file @4a53ba8. @HEAD both OBS buttons (lines 422, 461) use the same expression and the literal is still absent |
| C3 | CONFIRMED | `package-lock.json @4a53ba8` pins `@inertiajs/core` 3.7.0 (same @HEAD, same installed). `node_modules/@inertiajs/core/dist/index.js:1004-1008` - `setHashIfSameUrl` copies `originUrl.hash` only when origin has a hash, destination has none and `urlWithoutHash(originUrl).href === destinationUrl.href`; `:2649-2655` - `pageUrl()` takes the request hash unconditionally when `pageResponse.preserveFragment`, otherwise calls `setHashIfSameUrl` |
| C4 | CONFIRMED | `composer.lock @4a53ba8` pins `inertiajs/inertia-laravel` v3.3.1. `src/Response.php:262-270` at tag v3.3.1 (fetched from GitHub) - `getUrl()` builds from `$request->fullUrl()`. Laravel `Http/Request.php:140-147` uses `getQueryString()`; Symfony `Request.php:1159-1163` calls `normalizeQueryString()`, which `ksort`s at `:735` (vendor as installed now) |
| C5 | CONFIRMED | v3.3.1 `src/Response.php:113` - `preserveFragment` is only `session()->pull(SessionKey::PRESERVE_FRAGMENT, false)`, emitted at `:256`; the key is written only at `src/ResponseFactory.php:195-197`, which the `RedirectResponse::preserveFragment` macro calls (`src/ServiceProvider.php:130-131`); `grep preserveFragment` over `app/`, `routes/`, `resources/js/` returns nothing |
| C6 | CONFIRMED | `resources/js/composables/useUiMode.test.ts:4-24 @4a53ba8` - four `it`s asserting exactly the four cases named. Ran `npm test -- resources/js/composables/useUiMode.test.ts` @HEAD: 1 file, 15 tests passed. @HEAD the slugs in two cases are hyphenated (OL-2609-114) and the assertions are otherwise unchanged |
| C7 | UNVERIFIABLE | tagged [unverified]; a browser observation on a local install |
| C8 | UNVERIFIABLE | tagged [unverified]; a statement about testing that was not done |

### Surface
Complete.

### Findings
None.

### Notes
- Unchanged lines hold: `useAddressableTabs.ts` and `app/Support/ProductSetup.php` are not in `git show --stat 4a53ba8`, and the `useUiMode.ts` hunk begins after `parseUiMode()` ends (line 45 @4a53ba8).
- `vendor/` now holds inertia-laravel v3.3.4 (bumped by 87ba1ea8, a lockfile-only commit with no trailer; `composer.lock` is outside the claim path rule). The lines cited for C4 and C5 are the same in v3.3.4.
- C5 names the macro as what flashes the key; the writer is `ResponseFactory::preserveFragment()`, which is also reachable directly as `Inertia::preserveFragment()`. Nothing in the app calls either, so the claim's conclusion stands.
- The literal this replaces was introduced by OL-2609-045 (Surface line 13, C5: `?state=product&product=<slug>`), which the claim does not cite; it cites OL-2609-072 C26. OL-2609-045 C5's key order is no longer true of the tree; its scope (hint only on the OBS button) still is.
- `encodeURIComponent` leaves `!'()*` unescaped where Symfony's RFC 3986 `http_build_query` escapes them, so a pre-existing query value holding one of those would not match the echo. Product slugs cannot contain them (`SLUG` in `useUiMode.ts`); no claim covers other values.
