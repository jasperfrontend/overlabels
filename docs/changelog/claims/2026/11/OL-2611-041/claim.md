## OL-2611-041 - feat(reports): the Overlabels Overload Report

**Shipped:** 2026-11-26
**Commit:** `git log --grep=OL-2611-041`

### Surface
- `database/migrations/2026_11_26_200000_create_overload_reports_table.php` - new `overload_reports` table
- `app/Models/OverloadReport.php` - new model, `KEEP_DAYS`, `kept()` scope, `expiresAt()`, `url()`
- `app/Services/OverloadReports/ReportKinds.php` - new: kinds, weights, dot building, wording, summary
- `app/Services/OverloadReports/BusyStretch.php` - new: stretch state, close, store, chat line, settings
- `app/Jobs/CloseBusyStretch.php` - new job on the streamer's lane
- `app/Http/Controllers/TwitchEventSubController.php` - `processStoredEvent()` notes the event last; import
- `app/Services/Floods/FloodGate.php` - `apply()` notes the tick's count; imports
- `app/Http/Controllers/OverloadReportController.php` - new public page and Markdown export
- `resources/views/overload-report/show.blade.php` - new page with print stylesheet
- `app/Http/Controllers/Settings/OverloadReportSettingsController.php` - new settings show/update
- `resources/js/pages/settings/OverloadReport.vue` - new settings page
- `resources/js/layouts/settings/Layout.vue` - menu entry
- `app/Http/Controllers/DashboardController.php` - `overloadReports` prop
- `resources/js/pages/dashboard/index.vue` - Overload Reports section, shown only when there is one
- `routes/web.php` - `overload-report.show` and `overload-report.markdown` (`throttle:60,1`), `overload-report.pdf` (`throttle:6,1`)
- `routes/settings.php` - `settings.overload-report` and `settings.overload-report.update`
- `routes/console.php` - daily `prune:overload-reports`
- `tests/Feature/OverloadReportTest.php` - new, 17 tests
- `tests/Feature/FloodGateTest.php` - one new test for the flood tick hook
- `app/Services/OverloadReports/ReportPdf.php` - new: Gotenberg client
- `app/Services/Bot/BotChatAdminService.php` - `report:stop` verb, help line; import
- `tests/Feature/BotChatAdminTest.php` - two tests for `!ol report stop`
- `resources/js/components/SettingSwitch.vue` - new switch in the list page's chat-action look
- `config/services.php` - `gotenberg` block
- `.github/workflows/deploy.yml`, `config/deploy.yml` - three Gotenberg secrets
- `CLAUDE.md` - new "Overlabels Overload Report" section
- `docs/changelog/changelog-2026-11.md` - prose entry

### Claims
- **C1** [code] A stretch opens only when the event's kind is ticked, the feature is enabled, and the streamer is confidently live or `test_until` is in the future; the live check happens only when a stretch opens, and a negative answer is remembered 30 s.
- **C2** [code] A stretch closes when `quiet_seconds` have passed since the last event was handled; before that, `close()` re-dispatches `CloseBusyStretch` for the remaining time. It becomes an `OverloadReport` only if its weighted count reached `threshold`.
- **C3** [code] Gifted `channel.subscribe` events are not counted; a gift counts as its `total`; a hype train counts 1 at `begin`, and progress/end only raise the level of the open hype dot.
- **C4** [code] Follows, subs, bits, channel points and charity merge into the previous dot when it is the same kind; raids, gift bombs and hype trains always get their own dot; at most `MAX_DOTS` (200) dots.
- **C5** [code] The stored timeline and totals hold kinds, ISO timestamps and numbers only.
- **C6** [code] The chat line is written to `BotChatOutbox` only when `bot_enabled` and `BotModeratedChannels::moderates()` returns true.
- **C7** [code] The public page and Markdown resolve the user through `MapSlugService::decode()`, 404 on an unknown slug, wrong code, another streamer's code or a report older than 90 days, and send `X-Robots-Tag: noindex, nofollow`.
- **C8** [code] Settings save to `preferences.overload_report`; turning test mode on sets `test_until` an hour ahead, and saving with it still on keeps the existing time.
- **C9** [test] `OverloadReportTest` (17 tests), the new `FloodGateTest` test and the two new `BotChatAdminTest` tests pass.
- **C11** [code] `!ol report stop` (subject `report`, action `stop`) sets `preferences.overload_report.enabled` to false and forgets any open stretch; any other `report` verb answers "do you mean !ol report stop" and changes nothing. The chat line ends with "(!ol report stop turns these off)".
- **C12** [code] `GET /report/{slug}/{code}.pdf` (`throttle:6,1`) renders the page with `tz` (a valid zone from `timezone_identifiers_list()`, else UTC) as fixed times, no `<script>` and no buttons, posts it with basic auth to Gotenberg's `/forms/chromium/convert/html`, and returns it as an attachment; without a configured URL, or when the printer fails or answers something that is not a PDF, it returns 503 and the page shows the browser print button instead of the PDF link.
- **C13** [unverified] On 2026-11-26 a local report printed through the real Gotenberg instance: 200, `application/pdf`, ~30 KB, ~1.6 s, times in Europe/Amsterdam.
- **C10** [unverified] With the `note()` call removed from `processStoredEvent()`, "it notes events handled by the lane" failed; with it removed from `FloodGate::apply()`, the new FloodGateTest test failed. Both restored.

### Unchanged
- Alerts, bursts, flood counting and the stream session counters: the note is called after them and its failures are caught and logged.
- The abuse report on an overlay (`OverlayReport`, `reports.store`).
