## Audit of OL-2611-041 - feat(reports): the Overlabels Overload Report

**Audited:** 2026-11-26
**Commit:** 2987a44404fcbcc1bf4517c811b1add5ef7ed94d (HEAD; no later commits, so @2987a444 = @HEAD for every path)
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/OverloadReports/BusyStretch.php:43 @2987a444` - returns unless the kind is ticked and `settings()['enabled']`; `:50-52` the live check (`mayOpen()`) runs only when no stretch state exists; `:152-170` test mode (`test_until` future) or `StreamState::forUser()->isConfidentlyLive()`, a negative stored under `:offline` for `OFFLINE_MEMO_SECONDS` = 30 (`:34`) |
| C2 | CONFIRMED | `BusyStretch.php:84-90 @2987a444` - `closesAt` = `last` + `quiet_seconds`; if future, re-dispatches `CloseBusyStretch` delayed by the remainder; `:94` no report when `n < threshold` (or empty timeline); `last` is set to `now()` at handling, `:65` |
| C3 | CONFIRMED | `app/Services/OverloadReports/ReportKinds.php:45 @2987a444` - `channel.subscribe` with `is_gift === true` maps to null (not counted); `:65` gift weight = `total`; `:66` progress/end weight 0; `:72-75` they cannot open a stretch; `:87-99` they touch only the open hype dot (raise `level`; also move its `last` and set `ended` on end) |
| C4 | CONFIRMED | `ReportKinds.php:24-33 @2987a444` - `merges` true for follows, subs, bits, channel_points, charity; false for gift_subs, raids, hype_trains; `:110` merge into previous dot of same kind; `:36` `MAX_DOTS = 200`, `:120` no new dot past it |
| C5 | CONFIRMED | `ReportKinds.php:103-133 @2987a444` - totals keyed by kind (+ `bits_total`) with ints; dots hold `kind`, ISO `first`/`last`, `count`, `bits`, `tier`, `viewers`, `level`, plus a boolean `ended` (`:93`); no viewer field. `BusyStretch.php:176-184` stores exactly these |
| C6 | CONFIRMED | `BusyStretch.php:200 @2987a444` - returns unless `$user->bot_enabled` and `moderates(...) !== true` is false; `:205` `BotChatOutbox::create` |
| C7 | CONFIRMED | `app/Http/Controllers/OverloadReportController.php:115-126 @2987a444` - `MapSlugService::decode()`, 404 on unknown user, and `OverloadReport::kept()->where('user_id')->where('code')` (`kept()` = `created_at >= now()-90d`, `app/Models/OverloadReport.php:52-55`); `X-Robots-Tag: noindex, nofollow` on show `:29` and markdown `:102` |
| C8 | CONFIRMED | `app/Http/Controllers/Settings/OverloadReportSettingsController.php:72-84 @2987a444` - writes `preference('overload_report')`; test mode off -> null, already on -> existing `test_until`, else `now()+60 min` |
| C9 | CONFIRMED | Ran `php artisan test --filter=OverloadReportTest`: 17 passed (84 assertions). `--filter=BotChatAdminTest`: 30 passed, including the two `!ol report` tests. `--filter=FloodGateTest`: 17 passed, including "the counted events of a flood count toward an Overload Report (#345)" |
| C10 | UNVERIFIABLE | tagged [unverified] (fail-first runs against a tree that no longer exists) |
| C11 | CONFIRMED | `app/Services/Bot/BotChatAdminService.php:77 @2987a444` `'report:stop'`; `reportStop()` sets `overload_report.enabled` false and calls `BusyStretch::forget()` (returns early with "already off" when it is already off); `:80` any other `report:*` -> "do you mean !ol report stop"; `BusyStretch.php:209 @2987a444` line ends "(!ol report stop turns these off)" |
| C12 | CONTRADICTED (one half) | Compound. CONFIRMED: `routes/web.php @2987a444` `.pdf` route with `throttle:6,1`; `OverloadReportController.php:41-44` tz validated against `timezone_identifiers_list()`, else UTC; `resources/views/overload-report/show.blade.php:69,102-111,115-134 @2987a444` print copy has fixed times, no actions div, no `<script>`; `app/Services/OverloadReports/ReportPdf.php:32-35` basic auth POST to `/forms/chromium/convert/html`; `:27-29,36-46` null when unconfigured, unreachable, non-2xx or body not starting `%PDF`; controller `:47-52` 503, `:56` attachment. CONTRADICTED: "the page shows the browser print button instead of the PDF link" holds only when no URL is configured (`OverloadReportController.php:72` `pdfUrl` gated on `ReportPdf::configured()`); when a configured printer fails, the page still renders "Download as PDF" (`show.blade.php:104-105`) and the click gets a plain-text 503 |
| C13 | UNVERIFIABLE | tagged [unverified] (real Gotenberg instance) |

### Surface
Undisclosed: `resources/help/pages/bot/commands.md` - adds the `!ol report stop` row to the `!ol` chat-admin table.

### Findings
- **F1** Undisclosed path - `resources/help/pages/bot/commands.md` is in the diff (one new table row, line 130 @2987a444) and not in Surface; a follow-up claim should list it.
- **F2** Scope - `.github/workflows/deploy.yml:148 @2987a444` contains a literal `\n` inside the `for v in ...` word list, which bash reads as the word `n`, so every deploy writes a stray `n=''` line into `.kamal/secrets` (reproduced with `bash -ec` locally; the deploy run for 2987a444 still succeeded). Surface describes only "three Gotenberg secrets"; replace the `\n` with a backslash line continuation.
- **F3** Compound claim, halves differ - C12's "the page shows the browser print button instead of the PDF link" is true only for an unconfigured URL (`OverloadReportController.php:72 @2987a444`); on a printer failure the page keeps the PDF link (`show.blade.php:104-105 @2987a444`) and the reader lands on a plain-text 503. The new CLAUDE.md section repeats the same statement ("No URL configured or printer down = the page offers the browser's Print"); correct the record or the behaviour.
- **F4** Unchanged line inaccurate - Unchanged line 1 says the note "is called after" alerts, bursts and flood counting, but in `app/Services/Floods/FloodGate.php @2987a444` the `note()` call (`:239`) runs BEFORE the tick's overlay broadcast (`:247`) and `AlertBursts::grow()` / `reannounce()` (`:253`); it is only after them in `processStoredEvent()` (`TwitchEventSubController.php:964`). Failures are caught there too, so the ordering is the only false part; restate it in a follow-up claim.
- **F5** Scope - `config/services.php:161-173 @2987a444` inserts the `gotenberg` block between the `map_slug` comment ("Pure CPU, no DB lookup. The alphabet is the only secret...") and the `map_slug` key, so that comment now sits above `gotenberg`; move the block above the comment.

### Notes
- `BusyStretch::close()` (`BusyStretch.php:75-102 @2987a444`) never checks `enabled`, and the settings update does not forget an open stretch, so switching reports off on the settings page mid-stretch still yields a report and chat line; only `!ol report stop` forgets the stretch. No claim covers this either way.
- The `!ol help` row in `resources/help/pages/bot/commands.md` still lists `[cmd|alias|options|title]`, while `showHelp()` @2987a444 now also answers `report`.
- C3's "only raise the level" omits that progress/end also move the dot's `last` and set `ended`; C5's dots carry a boolean `ended`. Neither carries viewer data.
- Claims are numbered out of order (C11-C13 before C10).
