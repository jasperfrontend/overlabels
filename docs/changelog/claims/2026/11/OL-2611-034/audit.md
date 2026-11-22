## Audit of OL-2611-034 - fix(eventsub): a refused recreate stays on record, and failed subscriptions are repaired one by one instead of wiped

**Audited:** 2026-11-22
**Commit:** bc87978f (the only commit carrying `Changelog: OL-2611-034`; it is also HEAD, so every @bc87978f reference below is identical @HEAD)

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/UserEventSubManager.php:25 @bc87978f` - `STATUS_REFUSED = 'creation_refused'`; `:28` - `DEAD_STATUSES = ['not_found_on_twitch', self::STATUS_REFUSED]` |
| C2 | CONFIRMED | `app/Services/UserEventSubManager.php:317-321 @bc87978f` - after `UserEventsubSubscription::create()`, deletes rows of `user_id` + `event_type` with `whereIn('status', self::DEAD_STATUSES)` |
| C3 | CONFIRMED | `app/Services/UserEventSubManager.php:330-332 @bc87978f` - `($response['status'] ?? null) === 403` calls `markDeadRowsRefused()`, which (`:389-395`) sets DEAD_STATUSES rows of the user and type to `STATUS_REFUSED`; `TwitchEventSubService::createSubscription()` returns `'status' => $response->status()` on a non-2xx (`app/Services/TwitchEventSubService.php:77 @bc87978f`) |
| C4 | CONFIRMED | `app/Services/UserEventSubManager.php:255-257 @bc87978f` - missing `required_scope` branch calls `markDeadRowsRefused($user, $eventType)` |
| C5 | CONFIRMED | `app/Services/UserEventSubManager.php:325-339 @bc87978f` - only a 403 marks rows; the exception branch (`:287-297`) and the bot-id branch (`:263-268`) touch no row; the trailing `verifyUserSubscriptions()` writes `not_found_on_twitch` over a `not_found_on_twitch` row and skips a `creation_refused` one (`:535-539`) |
| C6 | CONFIRMED | `app/Services/UserEventSubManager.php:535-539 @bc87978f` - `! $twitchSub && status === STATUS_REFUSED` updates only `last_verified_at` and `continue`s, before the incomplete-list and not-found branches |
| C7 | CONFIRMED | `app/Console/Commands/MonitorEventSubHealth.php:159-162 @bc87978f` - `--fix` branch dispatches `SetupUserEventSubSubscriptions::dispatch($user)`; the `whereIn('id', ...)->delete()` line is removed in the diff |
| C8 | CONFIRMED | `app/Console/Commands/MonitorEventSubHealth.php:178-194 @bc87978f` - rows grouped by `user_id`, one `warn()` per group containing `refused by Twitch`, no dispatch in the pass |
| C9 | CONFIRMED | `app/Console/Commands/MonitorEventSubHealth.php:118-121 @bc87978f` - `--fix` calls `$this->manager->retireFailedSubscriptions($userFailedSubs)`; `dispatch($user, true)` removed from this pass (remaining `dispatch($user, true)` at `:251` is the `--force` branch) |
| C10 | CONFIRMED | `app/Services/UserEventSubManager.php:368-383 @bc87978f` - `deleteSubscription($appToken, $row->twitch_subscription_id)` per row; no `getSubscriptions()` call in the method |
| C11 | CONFIRMED | `app/Services/UserEventSubManager.php:370-374 @bc87978f` - returns before the loop when `getAppAccessToken()` is falsy; `:381` sets `status` `not_found_on_twitch` |
| C12 | CONFIRMED | `routes/web.php:537 @bc87978f` - `|| $user->eventsubSubscriptions()->where('status', UserEventSubManager::STATUS_REFUSED)->exists()` feeds `$shouldSetup`, which dispatches at `:549` |
| C13 | CONTRADICTED (in part) | Compound. CONFIRMED for C2, C3, C4, C5, C6, C12: `tests/Feature/EventSubRefusedRecreateTest.php @bc87978f` tests at `:97`, `:77`, `:136`, `:118` (503), `:152`, `:180` assert them, and all pass. NARROWER for C8: `names an account with refused rows and never retries it` (`:169-178`) seeds one account with one refused row and asserts only that output contains `refused by Twitch` and that no `SetupUserEventSubSubscriptions` is dispatched; it does not assert one line per account (with one row, a per-row line is indistinguishable) |
| C14 | CONFIRMED | `tests/Feature/EventSubRefusedRecreateTest.php:217-243 @bc87978f` - 29 rows (`count(SUPPORTED_EVENTS)` = 29), `$deleted` is `['sub-channel.follow']`, row `not_found_on_twitch`, enabled count `SUPPORTED_EVENTS - 1`, `assertDispatchedTimes(..., 1)` with `forceRecreate === false`; passes |
| C15 | CONFIRMED | `tests/Feature/EventSubRefusedRecreateTest.php:245-257 @bc87978f` - `shouldNotReceive('deleteSubscription')`, `assertNotDispatched`; passes |
| C16 | UNVERIFIABLE | tagged [unverified]; a fail-first run against the pre-change tree |

### Surface
Complete.

### Findings
- **F1** contradiction with the record - C7 reverses OL-2611-032 C10 ("With `--fix`, the monitor deletes a user's `not_found_on_twitch` rows and dispatches ...", `docs/changelog/claims/2026/11/OL-2611-032/claim.md:22`) and renames the test OL-2611-032 C11 names (`drops not_found_on_twitch rows ...` is now `keeps ...` at `tests/Feature/EventSubMonitorStatsTest.php:48 @bc87978f`), but the claim never cites OL-2611-032; a later claim should state "reverses OL-2611-032 C10, C11" inline.
- **F2** claim narrower than its test - C13 says `EventSubRefusedRecreateTest` covers C8, but `tests/Feature/EventSubRefusedRecreateTest.php:169-178 @bc87978f` does not assert C8's "one line per account"; either add a second refused row of the same account and assert one line, or restate C13 to the two halves it does assert.

### Notes
- Ran `php artisan test tests/Feature/EventSubRefusedRecreateTest.php tests/Feature/EventSubMonitorStatsTest.php`: 18 passed (44 assertions). (`--filter` with a `|` alternation broke under the Windows shell; the file paths were used instead.)
- C9 also reverses the OL-2611-032 Unchanged line that the failed pass "still dispatches `SetupUserEventSubSubscriptions` with `forceRecreate` true" (`OL-2611-032/claim.md:33`); that line described its own diff, so it is noted here rather than filed.
- Unchanged lines confirmed: `IntegrationController::connectEventSub()` (`app/Http/Controllers/Settings/IntegrationController.php:152`), `UserDeletionService.php:73` and `WiringFacts.php:110-111` are not in the diff; the `--force` branch (`MonitorEventSubHealth.php:238-255`) is in a touched file but outside every hunk.
- `retireFailedSubscriptions()` discards `deleteSubscription()`'s boolean (`UserEventSubManager.php:379 @bc87978f`), so a delete that fails for a reason other than 404 still marks the row `not_found_on_twitch`.
