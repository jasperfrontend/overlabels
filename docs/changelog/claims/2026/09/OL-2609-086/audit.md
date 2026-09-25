## Audit of OL-2609-086 - fix(products): give product installs their own rate limiter, instead of sharing kit-fork's

**Audited:** 2026-09-25
**Commit:** 3a7e699840ac9d74549b6835c5f7d67467baa6ae
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONTRADICTED (compound) | First half CONFIRMED: `app/Providers/AppServiceProvider.php:199-205 @3a7e699` - `RateLimiter::for('product-install', ...)` returns `Limit::perMinute(20)` and `Limit::perHour(60)`, both `->by('product-install:'.$key)` with `$key = $request->user()?->id ?: $request->ip()`; same at `:199-205 @HEAD`. Second half ("matching every other named limiter in the file") CONTRADICTED: `AppServiceProvider.php @3a7e699` keys `overlay` (:112), `overlay-auth-failed` (:122), `overlay-report` (:143-144) and `bot-internal` (:153) on `$request->ip()` alone, and `image-upload` has a second IP-only limit (:133) |
| C2 | CONFIRMED | `routes/web.php:693-695 @3a7e699` - `products.install` carries `throttle:product-install` (was `throttle:kit-fork` at `:694 @3a7e699~1`); `kits.fork` still `throttle:kit-fork` at `:689 @3a7e699`. @HEAD `:732-735` and `:728`, same middleware; slug pattern later widened to allow hyphens (OL-2609-114) |
| C3 | CONTRADICTED | `tests/Feature/ProductInstallRateLimitTest.php @3a7e699` asserts the 21st request in a minute is 429 (:37), the 61st across three 61-second-spaced batches of 20 is 429 (:47, :53), and `templates.store` still redirects after `product-install` is exhausted (:62-66). It never requests `kits.fork` or any `kit-fork` route, so "separate bucket from `kit-fork`" is not asserted; `templates.store` is on `throttle:template-write` at `routes/web.php:628 @3a7e699~1`, so the test shows separation from `template-write` only. `php artisan test --filter=ProductInstallRateLimitTest` @HEAD: 3 passed (104 assertions) |

### Surface
Complete.

### Findings
- **F1** compound claim, halves differ - C1 bundles the limiter's shape (true, `AppServiceProvider.php:199-205 @3a7e699`) with "matching every other named limiter in the file" (false: `overlay`, `overlay-auth-failed`, `overlay-report`, `bot-internal` are IP-keyed only, `:112`, `:122`, `:143-144`, `:153 @3a7e699`); a correcting claim should drop or narrow the second half, e.g. to the user-keyed limiters `template-write`, `kit-fork`, `twitch-write`, `twitch-read`.
- **F2** test narrower than claim - C3 says `ProductInstallRateLimitTest` shows `product-install` is a separate bucket from `kit-fork`, but the test never hits a `kit-fork` route (`tests/Feature/ProductInstallRateLimitTest.php:56-67 @3a7e699`); add an assertion that `kits.fork` still succeeds after `product-install` is exhausted, or restate C3 without the `kit-fork` half.
- **F3** false statement in shipped test - the comment at `tests/Feature/ProductInstallRateLimitTest.php:65 @3a7e699` says `products.install` and `templates.store` "used to share kit-fork's bucket", but `templates.store` was on `throttle:template-write` before this commit (`routes/web.php:628 @3a7e699~1`); correct the comment so the test does not claim to guard a regression it cannot catch.

### Notes
- The test was run at HEAD only, not at the shipped commit; the only diff between the two versions of the test file is the product slug `kofi_alert` -> `ko-fi-alerts` (OL-2609-114).
- OL-2609-038 audit F5 recorded the undisclosed `throttle:kit-fork` on `products.install` that this change replaces.
