## Audit of OL-2609-016 - fix(settings): one flash toast in AppLayout, confirmations on every settings save

**Audited:** 2026-09-24
**Commit:** dbe53cad2a406cba38dcc39c267c5948a75508d0
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Http/Middleware/HandleInertiaRequests.php:64-69 @dbe53ca` - `message` is `get('message') ?? get('success') ?? get('error')`; `type` is `get('type') ?? (has('success') ? 'success' : null) ?? (has('error') ? 'error' : null)`; same at `:67-72 @HEAD` |
| C2 | CONFIRMED | `resources/js/layouts/AppLayout.vue:33-41,67 @dbe53ca` - `watch(() => page.props.flash, ..., { immediate: true })` increments `flashKey`; the only `RekaToast` in the file is line 67 with `:key="flashKey"`; same at `:36-43,79 @HEAD` |
| C3 | CONFIRMED | `git grep -n flash dbe53ca -- resources/js/pages` returns only a comment in `settings/Chat.vue:41` and `templates/show.vue:173-179` (`flash.fork_wizard`); @HEAD only `templates/show.vue:200` (`fork_wizard`) |
| C4 | CONFIRMED | `resources/js/pages/TwitchData.vue:14,96,102,117,318 @dbe53ca` - `toastMessage` set by the refresh/error paths and rendered by its own `RekaToast`; same at @HEAD (line 322) |
| C5 | CONFIRMED | `resources/js/pages/settings/Chat.vue:29 @dbe53ca` declares `saveError`, no `confirmation`, no `onSuccess`; `routes/settings.php:71 @dbe53ca` returns `back()->with('success', 'Chat display settings saved. ...')`; Chat.vue renders inside `AppLayout` (line 66); same at `routes/settings.php:83 @HEAD` |
| C6 | CONFIRMED | `BotCommandsController.php:100-101,122-123,133-134 @dbe53ca` and `BotAliasesController.php:86-87,105-106,116-117 @dbe53ca` - six `->with('success', "... !{...} ...")`; bang stripped by `ltrim($data['command'], '!')` at `BotCommandValidator.php:76` / `BotAliasValidator.php:54 @dbe53ca`, written back to `$data['command']`; same six lines @HEAD |
| C7 | CONFIRMED | kofi/bmac/streamlabs/throne/fourthwall `.vue @dbe53ca` - no `Test mode is on` box, no `enabled`/`saving` spans; "Turn this off before going live" span kept (e.g. `kofi.vue:265-266`, `throne.vue:235-236`); @HEAD the whole inline block is replaced by `<TestModeToggle>` (OL-2609-019) |
| C8 | CONFIRMED | all five pages @dbe53ca set `toastMessage` after the successful `setSeedCount()` request (e.g. `kofi.vue:86`, `fourthwall.vue:68`) and render `<RekaToast ... type="success">` (e.g. `kofi.vue:349`); same @HEAD |
| C9 | CONFIRMED | `resources/js/pages/overlaytokens/index.vue:113,125,280 @dbe53ca` - toast set after `axios.post(.../revoke)` and `axios.delete(...)`, both naming `t.name`; same at `:129,141,300 @HEAD` |
| C10 | CONTRADICTED | `resources/js/pages/settings/integrations/kofi.vue:53` and `:231 @dbe53ca` each contain an em dash (`main form — toggled`, `Test mode — independent toggle`); @HEAD kofi.vue has none (removed by f8f6b149, OL-2609-019) |
| C11 | UNVERIFIABLE | tagged [unverified]; a browser observation, not checkable in-repo |

### Surface
Complete.

### Findings
- **F1** claim contradicted - C10 is false at the shipped commit: `resources/js/pages/settings/integrations/kofi.vue:53 @dbe53ca` (script comment) and `:231 @dbe53ca` (template comment) still contain an em dash; a remedy claim should restate C10 (the drift to zero is OL-2609-019's doing, not this change's).
- **F2** scope - `resources/js/pages/dashboard/events.vue @dbe53ca` has no `AppLayout` (its template opens with a bare `<div>` at line 171, `DashboardController::recentEvents()` renders it directly), so removing its flash watcher and `RekaToast` means the flash from `AlertMuteController.php:23-25` ("All alerts muted." / "Alerts unmuted."), posted from that page's `toggleMute()`, is shown by nothing; no claim or Risk line records it and it is unchanged @HEAD - decide whether that toast should come back.
- **F3** contradicts CLAUDE.md - by making the `success` key visible (C1), the change surfaces `'Kit forked successfully!'` (`app/Http/Controllers/KitController.php:281 @dbe53ca`) and `'Template forked successfully! ...'` (`app/Http/Controllers/OverlayTemplateController.php:1665 @dbe53ca`) as user-facing toasts, against CLAUDE.md "NEVER call "Fork" in frontend-facing UI. Always use "Copy" instead."; the Risk section lists template/kit copy flashes becoming visible but not their wording; both strings are still present @HEAD (`OverlayTemplateController.php:1708`).

### Notes
- C7/C8 drift: OL-2609-019 (f8f6b149) replaced the inline test-mode block on all five pages with `TestModeToggle.vue`; disclosed there, not a finding.
- `resources/js/pages/gamejam/admin.vue` no longer exists @HEAD (Chat Castle removal, OL-2609-034 per CLAUDE.md).
- No `[test]` claims; no tests were run.
- Unchanged line 1's count holds: 19 `->with('success'` sites @dbe53ca^, none in the diff. `RekaToast.vue`, `KofiIntegrationController.php`, `routes/web.php` and `settings/integrations/index.vue` are not in the diff.
