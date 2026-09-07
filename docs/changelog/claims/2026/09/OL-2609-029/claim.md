## OL-2609-029 - fix(twitch): rate limit the living-title settings endpoints, which each end in a Helix call

**Shipped:** 2026-09-08
**Commit:** `git log --grep=OL-2609-029`

### Surface
- `app/Providers/AppServiceProvider.php` - `twitch-write` (10/min, 60/hour) and `twitch-read` (30/min, 300/hour) limiters, keyed per user with an IP fallback
- `routes/settings.php` - `throttle:twitch-write` on update, resume and category; `throttle:twitch-read` on preview and categories
- `tests/Feature/LivingTitleRateLimitTest.php` - new, 4 tests

### Claims
- **C1** [code] `RateLimiter::for('twitch-write')` returns `Limit::perMinute(10)` and `Limit::perHour(60)`, both keyed by `twitch-write:` plus the user id, or the IP when there is no user.
- **C2** [code] `RateLimiter::for('twitch-read')` returns `Limit::perMinute(30)` and `Limit::perHour(300)`, keyed the same way.
- **C3** [code] `settings.title.update`, `settings.title.resume` and `settings.title.category` carry `throttle:twitch-write`; `settings.title.preview` and `settings.title.categories` carry `throttle:twitch-read`; `settings.title.show` carries neither.
- **C4** [test] `LivingTitleRateLimitTest` "saving the title is limited to 10 a minute per user" asserts the 11th save in a minute answers 429.
- **C5** [test] `LivingTitleRateLimitTest` "resume and the category picker share the write bucket with the save" asserts five resumes plus five category writes exhaust the bucket for a save.
- **C6** [test] `LivingTitleRateLimitTest` "the buckets are per user, so one account cannot starve another" asserts a second account saves after the first is at 429.

### Unchanged
- `LivingTitleService::schedule()`'s 30-second debounce is not in the diff; it coalesces the event-driven renders and never governed the settings endpoints, which dispatch with delay 0.
- `TwitchApiService::updateChannel()` is not in the diff; the limit sits on the routes, not the helper, because the sync job also calls it and is already bounded by the debounce.
