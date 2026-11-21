## OL-2611-033 - docs(claims): restate what OL-2611-032 tests and prints, and cite the OL-2609-023 record it reverses

Remedies OL-2611-032 audit F1, F2, F3, F5; F4 skipped.

**Shipped:** 2026-11-21
**Commit:** `git log --grep=OL-2611-033`

### Surface
- `docs/changelog/claims/2026/11/OL-2611-032/remedy.md` - new file, the remedy table for the OL-2611-032 audit

### Claims
- **C1** [test] `does not mark rows missing when Twitch returned only the first page` in `tests/Feature/EventSubMonitorStatsTest.php` asserts the stored row's `status` stays `enabled` after `verifyUserSubscriptions()` reads a response carrying `pagination.cursor` (corrects OL-2611-032 C9, audit F1).
- **C2** [code] That test makes no assertion on `last_verified_at` or any column other than `status`, so it covers the status half of OL-2611-032 C8's "untouched" and not the rest (corrects OL-2611-032 C9, audit F1).
- **C3** [code] In the stale pass of `MonitorEventSubHealth::handle()`, when `verifyUserSubscriptions()` returns an array with an `error` key, the command prints `Failed to verify: {error}` and does not read `active`, `failed` or `missing` from it (audit F2).
- **C4** [code] The stale pass's success line prints `{active} active, {failed} failed, {missing} missing`; it no longer prints a `renewed` count (audit F3).
- **C5** [code] With `--fix`, the `not_found_on_twitch` pass of `MonitorEventSubHealth::handle()` dispatches `SetupUserEventSubSubscriptions` for the row's user without any condition on `eventsub_connected_at` (corrects the OL-2609-023 Unchanged line that `MonitorEventSubHealth` only dispatches setup for accounts with `eventsub_connected_at` null, audit F5).
- **C6** [code] `UserEventSubManager::setupUserSubscriptions()`, which that dispatch runs, attempts every `SUPPORTED_EVENTS` type the user has no `enabled` row for, so the pass in C5 can add an event type to an already-connected account (corrects the OL-2609-023 Unchanged line that the monitor does not add a new event type to an already-connected account, audit F5).
