## Audit of OL-2609-057 - fix(controls): one query, one roll - a random-mode control resolves once per list append and once per alert

**Audited:** 2026-09-25
**Commit:** 666d57b1c1526fc3371c51ef6dfde1952ffd0de3
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Support/ControlSnapshot.php:46-51 @666d57b` - one `resolveDisplayValue()` per row of `OverlayControl::where('user_id', ...)->get()`, identifier `source_managed ? broadcastKey() : key`. @HEAD the identifier is `$control->tagIdentifier()` (OL-2609-060, whose C14 defines it as the same rule) |
| C2 | CONFIRMED | `app/Support/ControlSnapshot.php:53-55 @666d57b` - `if ($control->isRandom()) $rolls['c:'.$identifier] = $value;`; same @HEAD |
| C3 | CONFIRMED | `app/Services/Messages/AlertMessageRenderer.php:104-109 @666d57b` - one `ControlSnapshot::for($user)`, `tts` and `chat` both go through `resolveWith(..., $snapshot->values, $data)`. @HEAD `resolveWith()` also calls `ExpressionControlHydrator::hydrate()` (OL-2609-060 C10) |
| C4 | CONFIRMED | `app/Services/Messages/AlertMessageRenderer.php:105,110 @666d57b` - `$data = array_merge($templateData, $snapshot->rolls)`; string `c:` keys, so the later array wins; same @HEAD |
| C5 | CONFIRMED | `app/Services/Messages/AlertMessageRenderer.php:108-109 @666d57b` - `isGatedOff()` guards only the `tts` element; `chat` has no gate; same @HEAD |
| C6 | CONFIRMED | `TwitchEventSubController.php:732-739,749 @666d57b`, `ExternalAlertService.php:65-72,82 @666d57b`, `ExternalEventController.php:68-75,85 @666d57b` - one `renderAlert()` each, its `data` passed to `AlertTriggered`, chat from `$rendered['chat']`. For ExternalEventController the call sits in `replayForUser()` (line 36), which `replay()` (line 23) delegates to. Unchanged @HEAD |
| C7 | CONFIRMED | `git grep "renderMessage(\|AlertMessageRenderer::class)->render(" 666d57b -- app/` returns only the definition at `AlertMessageRenderer.php:80`; same @HEAD (definition at line 85) |
| C8 | CONFIRMED | `app/Services/Bot/BotCommandResolver.php:97,106-108 @666d57b` - `?ControlSnapshot $snapshot = null`, `($snapshot ?? ControlSnapshot::for($user))->values`, then `loadLists()` and `loadTwitchTags()` on every call (`loadTwitchTags()` skipped under `$dryRun`, as before the diff). @HEAD a `hydrate()` call follows (OL-2609-060 C9) |
| C9 | CONFIRMED | `app/Services/Lists/ListAppendService.php:111-112,158 @666d57b` - snapshot built before the `value_template` resolve, same `$snapshot` passed to the `success_reply` resolve at 158, after `$list->update()` at 133. @HEAD an erasure check is inserted before dedup (OL-2609-103) |
| C10 | CONFIRMED | `resources/js/components/OverlayRenderer.vue:1336,1354-1357 @666d57b` - `processedData = { ...alertData.data }` (emote-parsed on two text fields), merged `{ ...data.value, ...processedData }` and passed to `showAlert()`; same shape @HEAD lines 1520/1540 |
| C11 | CONFIRMED | `tests/Feature/AlertRandomSnapshotTest.php` has 3 tests: Ko-fi webhook (dispatch), `/external-events/{id}/replay`, `/events/{id}/replay` (renderEventAlert); each compares the `SynthesizeAlertTts` text, `BotChatOutbox` message and `AlertTriggered::$data['c:random1000']`. `php artisan test --filter=AlertRandomSnapshotTest` - 3 passed |
| C12 | CONFIRMED | `tests/Feature/ListAppendRandomSnapshotTest.php:76-102` - five fires, the returned `value` (the string passed to `ListItems::appendValue()`) compared to the reply; line 93 checks the two in-reply occurrences agree. It does not read the stored list back. `php artisan test --filter=ListAppendRandomSnapshotTest` - 1 passed |
| C13 | CONFIRMED | `tests/Feature/BotListTagsTest.php:194-227` expects `sighting 7` after appending to a 6-item list. `php artisan test --filter='reports the post-append count in the success reply'` - 1 passed |
| C14 | UNVERIFIABLE | tagged [unverified]; a fail-first run against a tree that no longer exists |

### Surface
Complete.

### Findings
- **F1** scope - the diff moves chat-line rendering out of the `if ($user->bot_enabled)` branch and into `renderAlert()`, which all three sites call unconditionally before the broadcast (`ExternalAlertService.php:65-72 @666d57b`, `ExternalEventController.php:68-75 @666d57b`, `TwitchEventSubController.php:732-739 @666d57b`; previously `renderMessage()` ran only inside that branch, after the broadcast). So every alert now renders `chat_message` even when the bot is off and the result is thrown away. No claim, Surface line or Risk line records this. The `renderAlert()` docblock says "`chat` is gated on bot_enabled at the dispatch site, as before", and that is true only of posting, not of rendering. @HEAD this means `resolveWith()`'s `hydrate()` call (OL-2609-060) can also cost a query and a sidecar evaluation for a chat line that is never sent. The reader should record this in a new claim, or skip chat rendering when `bot_enabled` is false.

### Notes
- Tests were run at HEAD. Neither new test file has changed since 666d57b (`git diff 666d57b HEAD --stat` on both is empty).
- C6's `ExternalEventController::replay()` matches the broadcast-point name CLAUDE.md uses (Alert Targeting). The edited method is `replayForUser()`.
- The title says "one query", but `renderAlert()` still runs a separate `isGatedOff()` query (`AlertMessageRenderer.php:204 @666d57b`). No numbered claim says otherwise.
