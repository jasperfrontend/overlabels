## OL-2609-086 - fix(products): give product installs their own rate limiter, instead of sharing kit-fork's

**Shipped:** 2026-09-15
**Commit:** `git log --grep=OL-2609-086`

### Surface
- `app/Providers/AppServiceProvider.php` - new `product-install` limiter (20/min, 60/hour, per user)
- `routes/web.php` - `products.install` route middleware switched from `throttle:kit-fork` to `throttle:product-install`
- `tests/Feature/ProductInstallRateLimitTest.php` - new file

### Claims
- **C1** [code] `AppServiceProvider` registers `RateLimiter::for('product-install', ...)` returning `Limit::perMinute(20)` and `Limit::perHour(60)`, both `by()` the authenticated user id with an IP fallback, matching every other named limiter in the file.
- **C2** [code] `routes/web.php`'s `products.install` route no longer carries `throttle:kit-fork`; it carries `throttle:product-install`. `kits.fork` is untouched and still uses `kit-fork`.
- **C3** [test] `ProductInstallRateLimitTest` asserts: the 21st `products.install` request inside one minute returns 429; spread across three separate minute windows (20 + 20 + 20, still inside one hour) the 61st returns 429; and exhausting `product-install` does not block `templates.store` (separate bucket from both `kit-fork` and `template-write`).

### Unchanged
- `kit-fork`'s own limits (3/min, 10/hour) are untouched and still apply to `kits.fork`, which really is the amplified per-request-writes-N-rows path this limiter was sized for.
- `ProductController::install` itself is not in the diff - it was already idempotent (a second install on an already-installed product redirects without writing).

### Risk
The effective ceiling for install requests goes up (from kit-fork's 10/hour to 60/hour). `install` is
a fixed-cost single recipe install with no per-request amplification, so this raises how often a
person can legitimately connect services in one sitting without raising per-request cost to the app.
