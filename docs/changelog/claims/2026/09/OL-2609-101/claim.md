## OL-2609-101 - fix(viewers): assert the list_append_history deletion, and restate the erasure boundary

Remedies OL-2609-099 audit F1, F2, F3, F4.

**Shipped:** 2026-09-18
**Commit:** `git log --grep=OL-2609-101`

### Surface
- `tests/Feature/ViewerErasureTest.php` - the first test now creates a `ListAppendHistory` row for the erased chatter id and asserts none survive
- `docs/changelog/claims/2026/09/OL-2609-099/remedy.md` - new: the outcome of each finding in that ID's audit

### Claims
- **C1** [test] `ViewerErasureTest` "deletes the viewer from every table that is keyed to them" creates a `ListAppendHistory` row with `chatter_id` `555000` via a `ListAppender::factory()` row, and asserts `ListAppendHistory::where('chatter_id', '555000')->count()` is 0 after `!forgetme`. All five tables `ViewerErasureService::erase()` deletes from are now asserted (corrects OL-2609-099 C15, audit F2).
- **C2** [unverified] That assertion was run with `ViewerErasureService.php:73` replaced by `$removed['list_history'] = 0;` and failed at the new line with "Failed asserting that 1 is identical to 0"; with line 73 restored the file's 12 tests pass (29 assertions).
- **C3** [code] `/api/internal/bot/forgetme` is not the only bot command endpoint without a `{login}` segment: `routes/api.php:237-239` registers `/manage`, `/followage` and `/accountage` with none either (corrects OL-2609-099 C1, audit F1).
- **C4** [code] `App\Models\OverlayControl` declares no `update()` method. The `source_managed` refusal is `OverlayControlController::update()` at line 197 and `OverlayControlController::setValue()` at line 357, both `abort(403, ...)` on HTTP entry (corrects OL-2609-099 C5, audit F3).
- **C5** [code] The same mis-citation is in the tree as a comment: `ViewerErasureService.php:111-112` reads "OverlayControl::update() refuses those by design". The call it explains, `writeValue('')`, is unaffected.
- **C6** [code] `isSuppressed()` is called from exactly three places in `app/`: `BotCheckinController.php:83`, `BotTowerController.php:92` and `ListAppendService.php:134`. No other write path on the platform consults `viewer_erasures` (audit F4).
- **C7** [code] `TwitchEventSubController.php:546` creates a `TwitchEvent` whose `event_data` is the raw payload, including `user_id`, with no suppression check, so the next follow, sub, cheer, raid or redemption from an erased viewer writes a row matching the `event_data->>'user_id'` predicate `erase()` deletes on (corrects OL-2609-099 Risk, audit F4).
- **C8** [code] `BotChatStatsController::store()` validates `message_count`, `chatters`, `latest_chatter_name` and `latest_chat_message` and receives no Twitch id at all, so an erased viewer's display name can be written back into `latest_chatter_name`, which is in `ViewerErasureService::NAME_CONTROL_KEYS` (audit F4).
- **C9** [code] `BotForgetMeController::replyFor()` says "we won't store you again" and `resources/help/pages/bot/commands.md:81` says "stops storing them in future". C6, C7 and C8 are the limits of that promise as the tree stands (audit F4).

### Unchanged
- `app/Services/ViewerErasureService.php` is the subject of C1, C2, C5 and C6 and is not in this diff. The deletion the new assertion pins was already there and already correct; the audit found the claim short of the code, not the code short of the claim.
- `app/Http/Controllers/TwitchEventSubController.php` and `app/Http/Controllers/Api/Internal/BotChatStatsController.php` are the subjects of C7 and C8 and are not in this diff. Adding a suppression check to either changes what the platform stores for every account on its busiest path.
- `app/Http/Controllers/Api/Internal/BotForgetMeController.php` and `resources/help/pages/bot/commands.md` are the subjects of C9 and are not in this diff. The wording narrows only if the boundary in C7 and C8 stays where it is, which is a decision, not a correction.
- `docs/changelog/claims/2026/09/OL-2609-099/claim.md` and `audit.md` are not in this diff. A shipped claim is not rewritten; the correction is this file.
