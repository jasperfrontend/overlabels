## Audit of OL-2611-032 - fix(eventsub): the hourly subscription monitor runs again, report-only, and can no longer delete other streamers' subscriptions

**Audited:** 2026-11-21
**Commit:** 7a5ca34e44456d6d21fac406553cb64dfd129740 (the only commit carrying the trailer; it is HEAD, so @7a5ca34e and @HEAD are the same tree)

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/UserEventSubManager.php:586-588 @7a5ca34e` - `selectRaw('event_type, count(*) as count')` then `groupBy('event_type')` then `pluck('count', 'event_type')`; same at @HEAD |
| C2 | CONFIRMED | `tests/Feature/EventSubMonitorStatsTest.php:19-30 @7a5ca34e` - two `channel.follow` rows and one `stream.online` row, `toEqual(['channel.follow' => 2, 'stream.online' => 1])`; passed |
| C3 | UNVERIFIABLE | tagged [unverified]; prod log observation |
| C4 | CONFIRMED | `routes/console.php:162-166 @7a5ca34e` - `Schedule::command('eventsub:monitor')->hourly()`, no `--fix`; same at @HEAD |
| C5 | CONFIRMED | `app/Console/Commands/MonitorEventSubHealth.php:188-190 @7a5ca34e` - `verifyUserSubscriptions($user)` inside the stale loop with no `$fix`/`$force` condition; same at @HEAD |
| C6 | CONFIRMED | `tests/Feature/EventSubMonitorStatsTest.php:208-242 @7a5ca34e` - run without `--fix`; `dropped` -> `not_found_on_twitch`, `alive` stays `enabled`, `Bus::assertNotDispatched`, `shouldNotReceive('createSubscription')` and `shouldNotReceive('deleteSubscription')`; passed |
| C7 | UNVERIFIABLE | tagged [unverified]; fail-first run against a tree that no longer exists |
| C8 | CONFIRMED | `app/Services/UserEventSubManager.php:454 @7a5ca34e` - `$listIsComplete = empty($twitchSubs['pagination']['cursor'])`; `:469-471` `continue`s before any `update()` when the row is absent and the list is incomplete; same at @HEAD |
| C9 (compound) | CONTRADICTED (first half) / CONFIRMED (second half) | First half: `tests/Feature/EventSubMonitorStatsTest.php:101-121 @7a5ca34e` asserts only `$sub->fresh()->status` is `enabled`; it does not assert `last_verified_at` (or any other column) is unchanged, so it is narrower than C8's "untouched". Second half: `:123-143` - `pagination => []`, row `gone` becomes `not_found_on_twitch`. Both tests passed |
| C10 | CONFIRMED | `app/Console/Commands/MonitorEventSubHealth.php:148-151 @7a5ca34e` - under `if ($fix)`, `whereIn('id', ...)->delete()` then `SetupUserEventSubSubscriptions::dispatch($user)`; `app/Jobs/SetupUserEventSubSubscriptions.php:30` defaults `forceRecreate` to false; same at @HEAD |
| C11 | CONFIRMED | `tests/Feature/EventSubMonitorStatsTest.php:48-74 @7a5ca34e` - `$dead` gone, `$alive` (`enabled`, same user) present, dispatch with `forceRecreate === false`; passed. The dispatch callback does not check the user; the fixture has one user only |
| C12 | CONFIRMED | `tests/Feature/EventSubMonitorStatsTest.php:76-93 @7a5ca34e` - `expectsOutputToContain('no longer on Twitch')`, row present, `assertNotDispatched`; passed |
| C13 | CONFIRMED | `app/Services/UserEventSubManager.php:394-397 @7a5ca34e` - `broadcaster_user_id ?? to_broadcaster_user_id ?? null`, `continue` unless `(string)` equal to `$user->twitch_id`; same at @HEAD |
| C14 | CONFIRMED | `tests/Feature/EventSubMonitorStatsTest.php:153-174 @7a5ca34e` - subs `bot-own-follow`, `bot-own-raid`, `streamer-chat-notification` (`broadcaster_user_id` 73327367, `user_id` bot); `toEqualCanonicalizing(['bot-own-follow', 'bot-own-raid'])`; passed |
| C15 | UNVERIFIABLE | tagged [unverified]; third-party API behaviour |
| C16 | CONFIRMED | `app/Console/Commands/MonitorEventSubHealth.php:63 @7a5ca34e` - `->where('is_system_user', false)`; same at @HEAD |
| C17 | CONFIRMED | `tests/Feature/EventSubMonitorStatsTest.php:181-201 @7a5ca34e` - `assertDispatchedTimes(..., 1)` and the dispatched job's `user` `is($streamer)`; passed |
| C18 | CONFIRMED | `app/Console/Commands/MonitorEventSubHealth.php:141-143` (not_found pass) and `:178-180` (stale pass) `@7a5ca34e` - `if (! $user) { continue; }`; same at @HEAD |

Tests run: `php artisan test --filter=EventSubMonitorStatsTest` - 9 passed (18 assertions).

### Surface
Complete.

### Findings
- **F1** test narrower than claim - C9 says its first test asserts C8 ("leaves a stored row untouched"), but `tests/Feature/EventSubMonitorStatsTest.php:120 @7a5ca34e` checks only `status`; add an assertion that `last_verified_at` is unchanged, or restate C9 as "status stays `enabled`".
- **F2** scope - `app/Console/Commands/MonitorEventSubHealth.php:191-192 @7a5ca34e` adds a branch that prints `Failed to verify: {$status['error']}` when `verifyUserSubscriptions()` returns its error array (before, `@7a5ca34e^:...:142` read `$status['active']` from that array); no claim or Surface line names it - record it in a follow-up claim.
- **F3** scope - the stale-pass status line changed from `{$status['renewed']} renewed` (`MonitorEventSubHealth.php:142 @7a5ca34e^`) to `{$status['missing']} missing` (`:194 @7a5ca34e`); no claim or Surface line names it - record it in a follow-up claim.
- **F4** contradicts CLAUDE.md - `CLAUDE.md:396-397 @HEAD` says "`eventsub:monitor --fix` only repairs accounts with NO subscriptions", while the new pass at `MonitorEventSubHealth.php:148-151 @7a5ca34e` dispatches `SetupUserEventSubSubscriptions` for accounts that hold subscription rows (and `setupUserSubscriptions()` creates every supported type without an `enabled` row, `UserEventSubManager.php:226-236`); update that CLAUDE.md line. (It was already loose before this change: the failed pass at `MonitorEventSubHealth.php:112` dispatches for connected accounts.)
- **F5** reverses an earlier claim's record without citing it - `docs/changelog/claims/2026/09/OL-2609-023/claim.md:38` records that `MonitorEventSubHealth` "only dispatches setup for accounts with `eventsub_connected_at` null ... it does not add a new event type to an already-connected account"; the new `not_found_on_twitch` pass with `--fix` can do exactly that for a connected account, and OL-2611-032 does not cite OL-2609-023. A follow-up claim should cite it inline.

### Notes
- C12's output assertion `no longer on Twitch` also matches the section header printed unconditionally at `MonitorEventSubHealth.php:131 @7a5ca34e` ("Checking for subscriptions no longer on Twitch..."), so it would pass even if the per-user report line at `:146` were removed. The claim states the assertion accurately; the assertion itself does not tell the two lines apart.
- C13 also narrows what `UserDeletionService` erasure deletes on Twitch (OL-2609-097 C14 calls `removeUserSubscriptions()`); every condition built by `buildCondition()` (`UserEventSubManager.php:501-519`) carries the account as `broadcaster_user_id` or `to_broadcaster_user_id`, so an ordinary account's own subscriptions are still all removed.
- Unchanged lines checked: the failed pass `dispatch($user, true)` at `MonitorEventSubHealth.php:112`, `SetupUserEventSubSubscriptions`, `setupUserSubscriptions()` (`UserEventSubManager.php:196`) and `UserEventsubSubscription::needsRenewal()` (no callers in `app/`, `routes/`, `tests/`, `database/`, `resources/js/`) are all outside the diff.
