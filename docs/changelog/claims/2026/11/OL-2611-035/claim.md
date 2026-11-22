## OL-2611-035 - docs(claims): cite the OL-2611-032 claims OL-2611-034 reversed, and restate what the refused-rows test asserts

Remedies OL-2611-034 audit F1, F2.

**Shipped:** 2026-11-22
**Commit:** `git log --grep=OL-2611-035`

### Surface
- `docs/changelog/claims/2026/11/OL-2611-034/remedy.md` - new file, remedy of the OL-2611-034 audit

### Claims
- **C1** [code] With `--fix`, the `not_found_on_twitch` pass of `MonitorEventSubHealth::handle()` dispatches `SetupUserEventSubSubscriptions` with `forceRecreate` false and deletes no row, which reverses OL-2611-032 C10 (corrects OL-2611-034 C7, audit F1).
- **C2** [test] `keeps not_found_on_twitch rows and re-runs a non-destructive setup` in `tests/Feature/EventSubMonitorStatsTest.php` is the test OL-2611-032 C11 named as `drops not_found_on_twitch rows and re-runs a non-destructive setup`; it now asserts the `not_found_on_twitch` row and the same user's `enabled` row both survive and that one `SetupUserEventSubSubscriptions` is dispatched with `forceRecreate` false, reversing OL-2611-032 C11 (corrects OL-2611-034 C13, audit F1).
- **C3** [test] `names an account with refused rows and never retries it` in `tests/Feature/EventSubRefusedRecreateTest.php` seeds one account with one `creation_refused` row and asserts that the `--fix` output contains `refused by Twitch` and that no `SetupUserEventSubSubscriptions` is dispatched (corrects OL-2611-034 C13, audit F2).
- **C4** [code] No test asserts the "one line per account" half of OL-2611-034 C8; that half rests on the `[code]` reading of `MonitorEventSubHealth::handle()` alone (corrects OL-2611-034 C13, audit F2).
