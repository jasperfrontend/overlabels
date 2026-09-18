## Audit of OL-2609-103 - fix(viewers): close the two surfaces that wrote an erased viewer straight back

**Audited:** 2026-09-18
**Commit:** e679e8f8
**Verdict:** FINDINGS

`e679e8f8` is also `@HEAD`, so every `@e679e8f8` line below reads identically `@HEAD`.

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/TwitchPayloadScrubber.php:63 @e679e8f8` - `if ($forceAnonymous \|\| ! empty($clean['is_anonymous']))`, looping `ANONYMOUS_FIELDS` at lines 41-46 = `user_id`, `user_login`, `user_name`, `user_avatar`. Signature `scrub(array $event, bool $forceAnonymous = false)` at line 59 |
| C2 | CONFIRMED | `app/Http/Controllers/TwitchEventSubController.php:533-539 @e679e8f8` - `forceAnonymous: $this->erasures->isSuppressed(isset($event['user_id']) ? (string) $event['user_id'] : null)`; `enrichEventWithUserAvatars()` is called at line 549, after it |
| C3 | CONFIRMED | Only `ANONYMOUS_FIELDS` (`TwitchPayloadScrubber.php:41-46 @e679e8f8`) is nulled; the rest of `scrub()` only strips `top_predictors`. The persist, session/counter and alert paths at `TwitchEventSubController.php:560,602-608,613-621 @e679e8f8` key on `$user` / `$broadcasterId`, both resolved at lines 498-502 before the scrub. `ViewerErasureTest` "anonymises a real twitch webhook..." asserts `broadcaster_user_id` survives |
| C4 | CONFIRMED | `app/Http/Controllers/Api/Internal/BotChatStatsController.php:49 @e679e8f8` - `'latest_chatter_id' => 'nullable\|string\|max:32'`; line 62 passes `$data['latest_chatter_id'] ?? null`; `ViewerErasureService.php:55-57 @e679e8f8` returns false for null/empty |
| C5 | CONFIRMED | `BotChatStatsController.php:64-70 @e679e8f8` - `$erased ? null : (...)` for both; `StreamSessionService.php:280,284 @e679e8f8` write only when `!== null && !== ''`. `message_count` (line 255) and `mergeChatters()` (line 260) are passed the raw values and are unmodified in the diff |
| C6 | CONFIRMED | `tests/Feature/ViewerErasureTest.php:264-291 @e679e8f8` - signed `channel.follow` notification, asserts `event_data['user_id'\|'user_name'\|'user_login']` are null, `broadcaster_user_id` is the streamer's, and `json_encode($stored->event_data)` does not contain `Alice`. `php artisan test --filter=ViewerErasure`: 16 passed, 47 assertions |
| C7 | CONFIRMED | `ViewerErasureTest.php:293-310 @e679e8f8` "leaves a webhook from a viewer who has not asked completely alone" - `user_name` is `Bob`; passes |
| C8 | CONFIRMED | `ViewerErasureTest.php:312-347 @e679e8f8` asserts `latest_chatter_name?->value` is not `Alice`, `latest_chat_message?->value` is not `hello`, `(int) chat_messages_this_stream` is 3; `ViewerErasureTest.php:349-374` asserts `Bob` is written. Both pass. The negative cases assert `not->toBe(...)` against controls seeded to `''`, not that no write occurred |
| C9 | UNVERIFIABLE | tagged [unverified]; a fail-first run against a tree that no longer exists, which the guide names as a legitimate use. The file holds 16 tests, consistent with "two failed, the other fourteen passed" |
| C10 | CONFIRMED / CONTRADICTED | a) CONFIRMED: `git grep isSuppressed -- app/ @e679e8f8` returns six lines - the declaration at `ViewerErasureService.php:53` plus five call sites (`BotChatStatsController.php:62`, `BotCheckinController.php:83`, `BotTowerController.php:92`, `TwitchEventSubController.php:535`, `Lists/ListAppendService.php:134`). b) CONTRADICTED: "There is no sixth writer of viewer identity" is false - `channel.chat.notification` is in `TwitchEventSubController::STORE_ONLY_EVENTS` (line 54 @e679e8f8) and its payload names the viewer `chatter_user_id` / `chatter_user_login` / `chatter_user_name` (fixture `tests/Feature/ChatNotificationSubscriptionTest.php:39-41 @e679e8f8`), none of which is in `ANONYMOUS_FIELDS` and none of which carries a `user_id` for `isSuppressed()` to test, so the row is stored with the erased viewer's display name. See F2. `StreamSessionService::mergeChatters()` is a second one - see F3 |

### Surface
Complete. All eight paths in `git show --stat e679e8f8` appear under `### Surface` except `docs/changelog/claims/2026/09/OL-2609-103/claim.md`, which is exempt. No `changelog-2026-09.md` entry in this commit. The two bot-repo paths are listed separately and are outside this repo.

### Findings
- **F1** unimplemented promise - both help pages shipped in this diff tell a viewer that raiding after `!forgetme` "arrives without your name attached" (`resources/help/pages/viewers.md:84 @e679e8f8`) and that a raid "simply arrives without a name" (`resources/help/pages/bot/commands.md:87 @e679e8f8`), but a `channel.raid` payload names the raider `from_broadcaster_user_id/_login/_name` and carries no `user_id` (`app/Services/TemplateDataMapperService.php:97-98 @e679e8f8`), so `TwitchEventSubController.php:535` passes null, `isSuppressed(null)` is false, and the raider's name is stored and rendered exactly as before; either cover `from_broadcaster_user_*` or drop "raid" from both sentences.
- **F2** false claim - C10's "There is no sixth writer of viewer identity" is contradicted by `channel.chat.notification`: `TwitchEventSubController.php:560 @e679e8f8` writes the row before the `STORE_ONLY_EVENTS` return at line 583, with `chatter_user_id`/`_login`/`_name` intact, and `ViewerErasureService::erase()` matches only `event_data->>'user_id'` (`ViewerErasureService.php:77 @e679e8f8`), so those rows are neither anonymised now nor deleted by a later `!forgetme`.
- **F3** inaccurate Unchanged rationale - the fourth Unchanged line justifies leaving the unique-chatter set alone with "a count is a number, not a person", but `StreamSessionService::mergeChatters()` stores the logins themselves: `Cache::put("chat:chatters:{$user->id}", $merged->all(), 43200)` (`StreamSessionService.php:329,339 @e679e8f8`, `CHATTER_SET_TTL` at line 62), so an erased viewer's login is written to a 12-hour store by the very call path this change edits; the line should say what is kept, not that only a number is.

### Notes
- `tests/Unit/TwitchPayloadScrubberTest.php` gained no case for `$forceAnonymous`; the new branch is covered only through the feature path in `ViewerErasureTest`. No claim asserts otherwise. That file passes @e679e8f8 (5 passed).
- `tests/Feature/BMACWebhookTest.php` is a Surface-only change with no numbered claim; the rename matches the OL-2609-097 audit Notes and the file passes @e679e8f8 (10 passed, 38 assertions).
- An erased viewer's cheer reaches `StreamSessionService.php:173-175 @e679e8f8` with `user_name` null, so `latest_cheerer_name` is written as `Anonymous` rather than blank - consistent with the help copy, and nothing in the claim states it.
