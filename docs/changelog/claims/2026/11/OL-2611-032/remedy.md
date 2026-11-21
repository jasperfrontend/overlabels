## Remedy of OL-2611-032 - fix(eventsub): the hourly subscription monitor runs again, report-only, and can no longer delete other streamers' subscriptions

**Remedied:** 2026-11-21
**Claim:** OL-2611-033

| Finding | Outcome | What |
|---------|---------|------|
| F1 | RECORD | OL-2611-033 C1 and C2 restate that `EventSubMonitorStatsTest` "does not mark rows missing when Twitch returned only the first page" asserts `status` only, not `last_verified_at` |
| F2 | RECORD | OL-2611-033 C3 records the `Failed to verify: {error}` branch at `app/Console/Commands/MonitorEventSubHealth.php:191-192` |
| F3 | RECORD | OL-2611-033 C4 records the stale-pass line printing `missing` in place of `renewed` at `MonitorEventSubHealth.php:194` |
| F4 | SKIPPED | the stale line is in `CLAUDE.md` (External Integrations, chat notices), which Remy does not edit; it needs a hand edit by the settling session, and OL-2611-033 C5 and C6 state the true behaviour |
| F5 | RECORD | OL-2611-033 C5 and C6 cite the OL-2609-023 Unchanged line inline and state that the `--fix` `not_found_on_twitch` pass can add an event type to an already-connected account |
