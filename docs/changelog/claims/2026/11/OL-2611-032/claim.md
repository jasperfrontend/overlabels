## OL-2611-032 - fix(eventsub): the hourly subscription monitor runs again, report-only, and can no longer delete other streamers' subscriptions

**Shipped:** 2026-11-21
**Commit:** `git log --grep=OL-2611-032`

### Surface
- `app/Services/UserEventSubManager.php` - `getGlobalStats()` selects the count it plucks; `verifyUserSubscriptions()` skips marking rows missing when Twitch's list has a next page; `removeUserSubscriptions()` deletes only Twitch subscriptions the user is the broadcaster of
- `app/Console/Commands/MonitorEventSubHealth.php` - system users excluded from the auto-connect pass; new pass for `not_found_on_twitch` rows; stale verification runs without `--fix`; null-user guard on the stale pass
- `routes/console.php` - hourly schedule is `eventsub:monitor` without `--fix`
- `tests/Feature/EventSubMonitorStatsTest.php` - new file, nine tests

### Claims
- **C1** [code] `UserEventSubManager::getGlobalStats()` builds `subscriptions_by_type` with `selectRaw('event_type, count(*) as count')` before `pluck('count', 'event_type')`.
- **C2** [test] `counts subscriptions by type` asserts `subscriptions_by_type` maps each event type to its row count.
- **C3** [unverified] Before C1, every run of `eventsub:monitor` on prod threw `Undefined property: stdClass::$count` from `getGlobalStats()` (observed in the scheduler container's `eventsub-monitor.log` on 2026-11-21).
- **C4** [code] The schedule in `routes/console.php` runs `eventsub:monitor` hourly with no `--fix` flag.
- **C5** [code] In `MonitorEventSubHealth::handle()`, the stale-subscription pass calls `verifyUserSubscriptions()` regardless of `--fix` and `--force`.
- **C6** [test] `verifies stale subscriptions without --fix and repairs nothing` asserts a run without `--fix` marks a row Twitch does not list `not_found_on_twitch`, keeps a listed row `enabled`, dispatches no `SetupUserEventSubSubscriptions`, and calls neither `createSubscription` nor `deleteSubscription`.
- **C7** [unverified] The C6 test failed with the verify call replaced by an error result.
- **C8** [code] `verifyUserSubscriptions()` leaves a stored row untouched when it is absent from Twitch's response and that response carries a non-empty `pagination.cursor`.
- **C9** [test] `does not mark rows missing when Twitch returned only the first page` asserts C8; `marks a row missing when Twitch returned the complete list` asserts a row absent from a response with empty `pagination` becomes `not_found_on_twitch`.
- **C10** [code] With `--fix`, the monitor deletes a user's `not_found_on_twitch` rows and dispatches `SetupUserEventSubSubscriptions` with `forceRecreate` false for that user.
- **C11** [test] `drops not_found_on_twitch rows and re-runs a non-destructive setup` asserts C10 and that the same user's `enabled` row survives.
- **C12** [test] `only reports not_found_on_twitch rows without --fix` asserts the row survives, nothing is dispatched, and the output contains `no longer on Twitch`.
- **C13** [code] `removeUserSubscriptions()` skips a Twitch-side subscription unless its condition's `broadcaster_user_id`, or failing that `to_broadcaster_user_id`, equals the user's `twitch_id`.
- **C14** [test] `removes only subscriptions the user is the broadcaster of` gives a bot account two subscriptions of its own and one whose condition is a streamer's `broadcaster_user_id` with the bot as `user_id`, and asserts only the bot's two are deleted.
- **C15** [unverified] Twitch's `GET eventsub/subscriptions?user_id=` matches that id in any condition field, so the bot account's list includes every streamer's `channel.chat.notification` subscription.
- **C16** [code] The auto-connect pass of `MonitorEventSubHealth::handle()` filters `is_system_user = false`.
- **C17** [test] `never queues a setup for a system user` asserts one dispatch, for the non-system user only.
- **C18** [code] The stale pass and the `not_found_on_twitch` pass both `continue` when a row's `user` relation is null.

### Unchanged
- The "failed subscriptions" pass in `MonitorEventSubHealth::handle()` still dispatches `SetupUserEventSubSubscriptions` with `forceRecreate` true, which removes and recreates every subscription of that account; C13 narrows what that removal deletes on Twitch, and the dispatch itself is not in the diff.
- `SetupUserEventSubSubscriptions` and `setupUserSubscriptions()`, which C10 dispatches into, are not in the diff.
- `UserEventsubSubscription::needsRenewal()` lists `not_found_on_twitch` and is still called from nowhere; not in the diff.

### Risk
From the first hourly run after deploy, every subscription unverified for 24 hours is checked
against Twitch and its stored status rewritten to Twitch's answer, so a subscription Twitch dropped
is no longer stored as `enabled`. No subscription is created or deleted until
`--fix` is restored on the schedule (GitHub issue #342).
