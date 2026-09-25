## Audit of OL-2609-101 - fix(viewers): assert the list_append_history deletion, and restate the erasure boundary

**Audited:** 2026-09-25
**Commit:** 85f97f94520d6ed41964a1ff1096de4dd8e084d8
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `tests/Feature/ViewerErasureTest.php @85f97f9` - first test creates `ListAppender::factory()` then `ListAppendHistory::create([... 'chatter_id' => '555000' ...])` and asserts `ListAppendHistory::where('chatter_id', '555000')->count()` is 0 after `forgetMe()`; with checkins, tower_blocks, twitch_events and external_events that is all five deletes at `app/Services/ViewerErasureService.php:71-80 @85f97f9`. The test body is byte-identical @HEAD (line 39). `php artisan test --filter=ViewerErasureTest` @HEAD: 20 passed, 72 assertions (8 tests added later by OL-2609-103/104). |
| C2 | UNVERIFIABLE | tagged [unverified]; a fail-first run against a modified tree |
| C3 | CONFIRMED | `routes/api.php:237-239 @85f97f9` - `Route::post('/manage', ...)`, `/followage`, `/accountage` inside the `/internal/bot` prefix with no `{login}`; `/forgetme` at line 242 |
| C4 | CONFIRMED | `app/Models/OverlayControl.php @85f97f9` - no `function update` among its declared methods; `app/Http/Controllers/OverlayControlController.php:197` (`update()`) and `:357` (`setValue()`) `@85f97f9` both `abort(403, ...)` on `$control->source_managed` |
| C5 | CONFIRMED | `app/Services/ViewerErasureService.php:111-112 @85f97f9` - "OverlayControl::update() refuses those by design", above `$control->writeValue('')` at 113; the comment is still present @HEAD at 119-120 |
| C6 | CONFIRMED | `git grep isSuppressed 85f97f9 -- app` - `BotCheckinController.php:83`, `BotTowerController.php:92`, `ListAppendService.php:134`, plus the definition at `ViewerErasureService.php:53`; `viewer_erasures` is read only at `ViewerErasureService.php:59`. @HEAD there are five call sites (OL-2609-103 added `BotChatStatsController.php:62` and `TwitchEventSubController.php:535`) |
| C7 | CONFIRMED / CONTRADICTED | a) CONFIRMED: `app/Http/Controllers/TwitchEventSubController.php:546-553 @85f97f9` - `TwitchEvent::create([... 'event_data' => $event ...])` with no `isSuppressed` call anywhere in the file. b) CONTRADICTED: "the raw payload" - `$event` is `TwitchPayloadScrubber::scrub($event)` at line 524 and avatar-enriched at 535 before the create. c) CONTRADICTED: "raid" - a `channel.raid` payload names the raider `from_broadcaster_user_id` (`tests/Feature/EventSubAvatarEnrichmentTest.php:71 @85f97f9`), not `user_id`, so a raid row does NOT match the `event_data->>'user_id'` predicate at `ViewerErasureService.php:77 @85f97f9`. @HEAD the scrub and delete cover it (OL-2609-104 C1, C6) |
| C8 | CONFIRMED | `app/Http/Controllers/Api/Internal/BotChatStatsController.php:36-43 @85f97f9` - validates exactly `message_count`, `chatters`, `chatters.*`, `latest_chatter_name`, `latest_chat_message`; no id field; `latest_chatter_name` is in `NAME_CONTROL_KEYS` at `ViewerErasureService.php:41 @85f97f9`. @HEAD accepts `latest_chatter_id` (OL-2609-103 C4) |
| C9 | CONFIRMED / CONTRADICTED | a) CONFIRMED: `BotForgetMeController.php:68-69 @85f97f9` ("we won't store you again") and `resources/help/pages/bot/commands.md:81 @85f97f9` ("stops storing them in future"). b) CONTRADICTED: "C6, C7 and C8 are the limits" - a cheer from an erased viewer also rewrites `latest_cheerer_name` (in `NAME_CONTROL_KEYS`) via `TwitchEventSubController.php:594` -> `StreamSessionService.php:173-186 @85f97f9`, a control write C7 does not describe; and `channel.chat.notification` rows keyed by `chatter_user_id` are stored at 546 and are not matched by `erase()` at all @85f97f9 |

### Surface
Complete. `git show --stat 85f97f9` lists `tests/Feature/ViewerErasureTest.php`, `docs/changelog/claims/2026/09/OL-2609-099/remedy.md` (both in Surface) and this claim file (exempt).

### Findings
- **F1** false claim - C7 lists "raid" among events that write a row matching `event_data->>'user_id'`; at `85f97f9` a raid carries `from_broadcaster_user_id` (`EventSubAvatarEnrichmentTest.php:71`) and `ViewerErasureService.php:77` matches `user_id` only, so the recorded boundary was wrong in the other direction (raids were neither suppressed nor deleted). OL-2609-104 fixes the code but cites OL-2609-103, not this claim; a reader relying on C7 should treat it as corrected by OL-2609-104 C1/C6.
- **F2** false claim - C7 calls the stored `event_data` "the raw payload"; `TwitchEventSubController.php:524 @85f97f9` scrubs it and line 535 enriches it before the create at 546. The substantive point (no suppression check) stands; the word should not be relied on.
- **F3** incomplete record - C9 states C6-C8 "are the limits" of the "won't store you again" promise, but at `85f97f9` `StreamSessionService.php:186` writes an erased cheerer's `user_name` into `latest_cheerer_name` and `channel.chat.notification` rows keyed by `chatter_user_id` are stored and never erased. Both are closed @HEAD by OL-2609-103 (scrub before `handleEvent`) and OL-2609-104 (chat notice fields), neither of which cites this claim.
- **F4** untagged assertion - the first Unchanged line says the deletion "was already there and already correct"; `claims-guide.md` names "was already correct" as a smuggled judgment that belongs in Claims with a tag. The deletion at `ViewerErasureService.php:73 @85f97f9` is real (C1 pins it); only the form is wrong.

### Notes
- OL-2609-103's preamble calls this claim's C6-C9 "accurate"; F1 and F3 show C7 and C9 were not fully so at `85f97f9`.
- C6, C8 and C9's help text have since drifted, all disclosed by OL-2609-103; `erase()`'s `twitch_events` predicate by OL-2609-104 C6.
- The mis-citing comment named in C5 is still in the tree @HEAD (`ViewerErasureService.php:119-120`); no later claim changes it.
- The shipped-revision test run was not reproduced; the first test's body is identical @HEAD and passes there.
