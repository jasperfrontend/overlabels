## Audit of OL-2609-029 - fix(twitch): rate limit the living-title settings endpoints, which each end in a Helix call

**Audited:** 2026-09-25
**Commit:** 102fbf1bf3eefd97496f867c4b7ce81380455211 (sole commit carrying `Changelog: OL-2609-029`)
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Providers/AppServiceProvider.php:215-220 @102fbf1` - `RateLimiter::for('twitch-write')`, `$key = $request->user()?->id ?: $request->ip()`, returns `Limit::perMinute(10)->by('twitch-write:'.$key)` and `Limit::perHour(60)->by('twitch-write:'.$key)`; same lines unchanged @HEAD |
| C2 | CONFIRMED | `app/Providers/AppServiceProvider.php:228-233 @102fbf1` - `RateLimiter::for('twitch-read')`, same key expression, `Limit::perMinute(30)` and `Limit::perHour(300)` by `'twitch-read:'.$key`; same lines unchanged @HEAD |
| C3 | CONFIRMED | `routes/settings.php:79-86 @102fbf1` - `show` has no middleware; `update`, `resume`, `category` carry `throttle:twitch-write`; `preview`, `categories` carry `throttle:twitch-read`. Same at `routes/settings.php:90-97 @HEAD`; `php artisan route:list --name=settings.title -v` @HEAD shows `ThrottleRequests` on the five routes and only `web` + `RedirectIfUnauthenticated` on `settings.title.show` |
| C4 | CONFIRMED | `tests/Feature/LivingTitleRateLimitTest.php:45-53 @102fbf1` - ten PATCHes `assertRedirect()`, 11th `assertStatus(429)`. `php artisan test --filter=LivingTitleRateLimitTest` @HEAD: 4 passed (65 assertions); file unchanged since 102fbf1 |
| C5 | CONFIRMED | `tests/Feature/LivingTitleRateLimitTest.php:55-64 @102fbf1` - five `POST /settings/title/resume` and five `POST /settings/title/category` each `assertRedirect()`, then `PATCH /settings/title` `assertStatus(429)`; passed in the same run |
| C6 | CONFIRMED | `tests/Feature/LivingTitleRateLimitTest.php:77-86 @102fbf1` - first user reaches 429 on the 11th PATCH, a second `titleRlUser()` then PATCHes and `assertRedirect()`; passed in the same run |

### Surface
Complete.

### Findings
None.

### Notes
- Unchanged lines confirmed: neither `LivingTitleService::schedule()` nor `TwitchApiService::updateChannel()` appears in the diff. `LivingTitleController::update()` calls `schedule($user, 0)` at line 80 @102fbf1.
- The third test, "the preview and the category search are limited to 30 a minute per user" (`LivingTitleRateLimitTest.php:66-75 @102fbf1`), has no numbered claim. Surface's "4 tests" covers it, and it passed.
- The 60/hour and 300/hour ceilings and the IP fallback are pinned only by C1/C2 as `[code]`; no test exercises them.
- `beforeEach` calls `RateLimiter::clear('twitch-write')` / `clear('twitch-read')` (`LivingTitleRateLimitTest.php:21-22 @102fbf1`), but the limiter's buckets are keyed `twitch-write:<id>` / `twitch-read:<id>`, so those two calls do not clear the buckets the tests fill. The tests pass anyway.
