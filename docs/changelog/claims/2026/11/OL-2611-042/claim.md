## OL-2611-042 - fix(reports): no stray secret line from the deploy loop, gotenberg config above the map slug comment

**Shipped:** 2026-11-26
**Commit:** `git log --grep=OL-2611-042`

Remedies OL-2611-041 audit F1, F2, F3, F4, F5.

### Surface
- `.github/workflows/deploy.yml` - the literal `\n` in the `.kamal/secrets` word list replaced with a backslash line continuation (line 148)
- `config/services.php` - the `gotenberg` block moved above the `map_slug` comment
- `docs/changelog/claims/2026/11/OL-2611-041/remedy.md` - new file, the remedy of the OL-2611-041 audit

### Claims
- **C1** [code] OL-2611-041's diff also changed `resources/help/pages/bot/commands.md`: it adds the `!ol report stop` row (line 130) to the `!ol` chat-admin table, a path its Surface does not list (audit F1).
- **C2** [code] In `.github/workflows/deploy.yml` the `for v in ...` word list that writes `.kamal/secrets` ends line 148 with `PADDLE_WEBHOOK_SECRET \` and continues on line 149 with the three Gotenberg names; it contains no literal `\n`, so no variable named `n` is written (audit F2).
- **C3** [unverified] The `{ for v ... done }` block extracted from `deploy.yml` and run with `bash -e` printed an `n=''` line before the change and none after; after the change it prints `BR_GOTENBERG_URL`, `GOTENBERG_API_BASIC_AUTH_USERNAME` and `GOTENBERG_API_BASIC_AUTH_PASSWORD` lines (audit F2).
- **C4** [code] The overload report page's "Download as PDF" link is shown whenever `ReportPdf::configured()` is true (`OverloadReportController::page()`, `pdfUrl`); the page has no knowledge of whether the printer is up, so when a configured Gotenberg fails the link stays and the click answers 503. The browser print button replaces the link only when no Gotenberg URL is configured (corrects OL-2611-041 C12, audit F3).
- **C5** [code] In `FloodGate::apply()` the `BusyStretch::note()` call runs after the stream session count, the meter, `refreshCachesForEvent()` and `LivingTitleService::schedule()`, and BEFORE the tick's `TwitchEventReceived` broadcast and `AlertBursts::grow()` / `reannounce()`; only in `TwitchEventSubController::processStoredEvent()` does it run after the event's other handling. In both places a failure of `note()` is caught and logged (corrects OL-2611-041 Unchanged line 1, audit F4).
- **C6** [code] In `config/services.php` the `gotenberg` block sits above the Sqids comment, and that comment sits directly above the `map_slug` key it describes (audit F5).

### Unchanged
- The audit's F3 offers correcting the behaviour instead of the record; the page and `ReportPdf` are not in this diff, so a reader whose printer fails still gets the PDF link and a plain-text 503.
- The `CLAUDE.md` "Overlabels Overload Report" section still says "printer down = the page offers the browser's Print"; it is not in this diff.
