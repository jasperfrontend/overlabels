## OL-2611-034 - fix(eventsub): a refused recreate stays on record, and failed subscriptions are repaired one by one instead of wiped

**Shipped:** 2026-11-22
**Commit:** `git log --grep=OL-2611-034`

### Surface
- `app/Services/UserEventSubManager.php` - `STATUS_REFUSED` and `DEAD_STATUSES` constants; `setupUserSubscriptions()` drops dead rows of a type after a successful create and marks them refused after a 403 or a missing scope; `verifyUserSubscriptions()` keeps refused rows refused; new `retireFailedSubscriptions()` and `markDeadRowsRefused()`
- `app/Console/Commands/MonitorEventSubHealth.php` - failed pass retires rows instead of dispatching a force recreate, with a null-user guard; not-found pass no longer deletes rows before dispatching setup; new pass naming `creation_refused` rows
- `routes/web.php` - Twitch login callback runs setup when the account has `creation_refused` rows; imports `UserEventSubManager`
- `tests/Feature/EventSubRefusedRecreateTest.php` - new file, nine tests
- `tests/Feature/EventSubMonitorStatsTest.php` - the not-found repair test now asserts the dead row is kept (renamed from "drops" to "keeps")

### Claims
- **C1** [code] `UserEventSubManager::STATUS_REFUSED` is `'creation_refused'`, and `DEAD_STATUSES` is `not_found_on_twitch` plus `STATUS_REFUSED`.
- **C2** [code] In `setupUserSubscriptions()`, after a successful create of a type, rows of that user and type whose status is in `DEAD_STATUSES` are deleted.
- **C3** [code] In `setupUserSubscriptions()`, a create answered with `status` 403 sets that user's rows of the type with a status in `DEAD_STATUSES` to `creation_refused`.
- **C4** [code] In `setupUserSubscriptions()`, a type skipped for a missing scope sets that user's dead rows of the type to `creation_refused`.
- **C5** [code] A create failure with any status other than 403 leaves the dead row's status unchanged.
- **C6** [code] `verifyUserSubscriptions()` leaves a `creation_refused` row absent from Twitch's list at `creation_refused` and updates its `last_verified_at`.
- **C7** [code] `MonitorEventSubHealth::handle()`'s not-found pass dispatches `SetupUserEventSubSubscriptions` without deleting any row.
- **C8** [code] `MonitorEventSubHealth::handle()` has a pass that writes one line per account with `creation_refused` rows, containing `refused by Twitch`, and dispatches nothing for them.
- **C9** [code] With `--fix`, the failed pass calls `UserEventSubManager::retireFailedSubscriptions()` and no longer dispatches `SetupUserEventSubSubscriptions` with `forceRecreate` true.
- **C10** [code] `retireFailedSubscriptions()` calls `TwitchEventSubService::deleteSubscription()` with each row's own `twitch_subscription_id` and never calls `getSubscriptions()`.
- **C11** [code] `retireFailedSubscriptions()` sets each row to `not_found_on_twitch`, and returns without changing any row when no app token is available.
- **C12** [code] The Twitch login callback in `routes/web.php` dispatches `SetupUserEventSubSubscriptions` when the user has any `eventsubSubscriptions()` row with status `UserEventSubManager::STATUS_REFUSED`.
- **C13** [test] `EventSubRefusedRecreateTest` covers C2 (`replaces the dead row when the recreate succeeds`), C3 (`marks the dead row refused when Twitch refuses the recreate`), C4 (`marks the dead row refused when the scope it needs was never granted`), C5 (`leaves the dead row for the next hour on a failure that is not a refusal`, with a 503), C6, C8 and C12.
- **C14** [test] `retires only the failed subscription and recreates it without a force wipe` asserts that for one `authorization_revoked` row among 29, `deleteSubscription()` is called once with that row's id, the row becomes `not_found_on_twitch`, the 28 enabled rows remain, and one `SetupUserEventSubSubscriptions` is dispatched with `forceRecreate` false.
- **C15** [test] `only reports failed subscriptions without --fix` asserts no `deleteSubscription()` call and no dispatch.
- **C16** [unverified] Of the new tests, those for C2, C3, C4, C6, C8, C12 and C14 failed against the tree before this change; the C5 and C15 tests passed before it and are guards.

### Unchanged
- The full wipe (`SetupUserEventSubSubscriptions` with `forceRecreate` true, which calls `removeUserSubscriptions()`) is still dispatched by the integrations page's reconnect (`IntegrationController::connectEventSub()`) and the monitor's `--force` branch, and `removeUserSubscriptions()` is still called by account erasure; none of those call sites is in the diff.
- `WiringFacts` counts every non-`enabled` row as needing repair; `creation_refused` rows are counted by that existing query, which is not in the diff.

### Risk
`creation_refused` rows count against the monitor's health score (enabled / total). If refused rows
pass 20% of all rows, the command exits 1 and the Healthchecks.io check from ed52dd72 receives a
`/fail` ping. For a `user_removed` account, the status Twitch returns on a recreate is unknown; if
it is not 403, that row stays `not_found_on_twitch` and is retried every hour.
