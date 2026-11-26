## OL-2611-040 - fix(eventsub): a flood tick refreshes the follower caches and schedules the living title (settles OL-2611-038)

**Shipped:** 2026-11-26
**Commit:** `git log --grep=OL-2611-040`

### Surface
- `app/Services/Floods/FloodGate.php` - `apply()` calls `refreshCachesForEvent()` and `LivingTitleService::schedule()` once per tick; import
- `app/Http/Controllers/TwitchEventSubController.php` - `refreshCachesForEvent()` is public
- `tests/Feature/FloodGateTest.php` - new test for the two calls; two job imports
- `CLAUDE.md` - the "Floods become numbers" section names the two calls

### Claims
- **C1** [code] `FloodGate::apply()` calls `TwitchEventSubController::refreshCachesForEvent($eventType, $twitchId)` and `LivingTitleService::schedule($user)` once per tick with a count above zero. This settles OL-2611-039 C3 (from the OL-2611-038 audit, F1): Jasper decided on 2026-11-26 that a tick does both once.
- **C2** [code] `TwitchEventSubController::refreshCachesForEvent()` changed from private to public; its body is unchanged.
- **C3** [test] `FloodGateTest` asserts that one tick over 200 counted follows, for an account with the living title enabled, pushes a `ForgetTwitchCache` for `channel_followers` of that broadcaster and exactly one `SyncLivingTitle`.
- **C4** [unverified] With the two calls removed from `apply()`, the C3 test failed and the other eleven passed.

### Unchanged
- Both calls are rate-limited by their own code (`clearVolatileCache()`'s 10 s window, `LivingTitleService::schedule()`'s `Cache::add` debounce); neither is in the diff.
