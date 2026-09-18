## Audit of OL-2609-104 - fix(viewers): suppress the two payload shapes that do not call the viewer `user_*`

**Audited:** 2026-09-18
**Commit:** 861ba40a
**Verdict:** CLEAN

`861ba40a` is also `@HEAD` (`git rev-parse HEAD` = `861ba40ae103...`), so every `@861ba40a` line below reads identically `@HEAD`.

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/TwitchPayloadScrubber.php:85-96 @861ba40a` - `actingViewerId()` loops `ACTING_VIEWER_ID_FIELDS` (`:73-77` = `user_id`, `from_broadcaster_user_id`, `chatter_user_id`, in that order), returns `(string) $id` on the first `is_scalar($id) && (string) $id !== ''`, and `return null` at `:95` |
| C2 | CONFIRMED | `app/Http/Controllers/TwitchEventSubController.php:533-537 @861ba40a` - `forceAnonymous: $this->erasures->isSuppressed(TwitchPayloadScrubber::actingViewerId($event))`, one call site (`git grep -n 'TwitchPayloadScrubber::scrub' app/` = that line only); `enrichEventWithUserAvatars()` is at `:549`, after it. At the parent `e679e8f8` the same line read `isset($event['user_id']) ? (string) $event['user_id'] : null`, and neither `channel.raid` nor `channel.chat.notification` carries `user_id` (fixtures `tests/Feature/ChatNotificationSubscriptionTest.php:36-49 @861ba40a`, `tests/Feature/ViewerErasureTest.php:360-367`), so both passed null |
| C3 | CONFIRMED | `TwitchPayloadScrubber.php:121-127 @861ba40a` - `if ($forceAnonymous) { foreach (self::SUPPRESSED_FIELDS ...` , a separate block from the `$forceAnonymous \|\| ! empty($clean['is_anonymous'])` loop at `:113`. `SUPPRESSED_FIELDS` at `:56-66` is exactly the nine fields listed. `tests/Unit/TwitchPayloadScrubberTest.php` unchanged and passing (`php artisan test --filter=TwitchPayloadScrubber`: 5 passed, 17 assertions) |
| C4 | CONFIRMED | `TwitchPayloadScrubber.php:65 @861ba40a` - `system_message` is in the list; the app's own model of the payload carries the display name in that sentence (`ChatNotificationSubscriptionTest.php:41,45 @861ba40a` - `chatter_user_name` `Viewer`, `system_message` `'Viewer subscribed at Tier 1.'`, a fixture predating this commit). Nulling only the trigram would leave `Alice subscribed at Tier 1.` in the row, which is what `ViewerErasureTest.php:430 @861ba40a` (`not->toContain('Alice')`) fails on |
| C5 | CONFIRMED | `TwitchPayloadScrubber.php:109-130 @861ba40a` - `scrub()` does three things and no more: `stripDeniedKeys` (`DENIED_KEYS` = `top_predictors` only, `:34-36`), the `ANONYMOUS_FIELDS` loop (`user_*`, absent from both payload shapes), the `SUPPRESSED_FIELDS` loop. `ViewerErasureTest.php:377-378` asserts `to_broadcaster_user_id` and `viewers` survive a suppressed raid; `:427-428` asserts `notice_type` and `sub.is_prime` survive a suppressed notice |
| C6 | CONFIRMED | `app/Services/ViewerErasureService.php:81-85 @861ba40a` - `$events = TwitchEvent::query();` then `orWhereRaw("event_data->>'$field' = ?", [$twitchId])` over `TwitchPayloadScrubber::ACTING_VIEWER_ID_FIELDS`, replacing the single `event_data->>'user_id'` predicate at `:75 @e679e8f8`. The constant is `public` (`TwitchPayloadScrubber.php:73`) and is the same list `actingViewerId()` reads |
| C7 | CONFIRMED | `tests/Feature/ViewerErasureTest.php:351-382 @861ba40a` - signed `channel.raid`, asserts `from_broadcaster_user_id`, `_login`, `_name` are null, `to_broadcaster_user_id` is the streamer's, `viewers` is 42, and `json_encode($stored->event_data)` does not contain `Alice`. Passes |
| C8 | CONFIRMED | `ViewerErasureTest.php:384-402 @861ba40a` - "leaves a raid from a viewer who has not asked completely alone" asserts `from_broadcaster_user_name` is `Bob`. Passes |
| C9 | CONFIRMED | `ViewerErasureTest.php:404-430 @861ba40a` - signed `channel.chat.notification`, asserts `chatter_user_id`, `_login`, `_name` are null, `notice_type` is `sub`, `sub.is_prime` is false, and the JSON does not contain `Alice`. Passes |
| C10 | CONFIRMED | `ViewerErasureTest.php:317-349 @861ba40a` - a `channel.raid` row keyed `from_broadcaster_user_id` `555000` and a `channel.chat.notification` row keyed `chatter_user_id` `555000` are both null after `forgetMe()`, and a second raid row keyed `999999` is `not->toBeNull()`. Passes. `php artisan test --filter=ViewerErasure`: 20 passed, 72 assertions |
| C11 | UNVERIFIABLE / CONFIRMED | First half (fail-first runs against the pre-fix tree) tagged [unverified] and uncheckable by contract; the quoted failure `'555000' is null` is consistent with `ViewerErasureTest.php:376,425 @861ba40a` being the first null assertion in each test. Second half checked: `php artisan test` @861ba40a = `6 skipped, 2293 passed (12748 assertions)`, exit 0 - the stated numbers exactly |
| C12 | CONFIRMED | `app/Services/StreamSessionService.php:306-331 @861ba40a` - `mergeChatters()` lowercases the incoming logins and `Cache::put($key, $merged->all(), self::CHATTER_SET_TTL)` at `:329`; `CHATTER_SET_TTL = 43200` at `:62`; key is `"chat:chatters:{$user->id}"` at `:339`. Values stored are logins, not a count |

### Surface
Complete. All six paths in `git show --stat 861ba40a` appear under `### Surface` except `docs/changelog/claims/2026/09/OL-2609-104/claim.md`, which is exempt; `docs/changelog/claims/2026/09/OL-2609-103/remedy.md` is listed as the guide requires. No `changelog-2026-09.md` entry in this commit. Every Unchanged line holds: `resources/help/pages/viewers.md`, `resources/help/pages/bot/commands.md`, `BotChatStatsController.php`, `StreamSessionService.php` and `tests/Unit/TwitchPayloadScrubberTest.php` are not in the diff, and the `ViewerErasureService.php` hunk touches only lines 75-85, leaving `NAME_CONTROL_KEYS` (`:40-48`) and `blankNameControls()` untouched.

### Findings
None.

### Notes
- Remedy check against `OL-2609-103/audit.md`: F1 and F2 are closed by C2/C3/C6 with the tests in C7-C10; F3's RECORD outcome is C12, which states what `mergeChatters()` keeps rather than repeating "a count is a number, not a person" - the exact correction the finding asked for.
- OL-2609-099 C2 ("`erase()` deletes ... `twitch_events` (`event_data->>'user_id'`)") is superseded by C6 here; C6 cites OL-2609-103's Unchanged line but not 099 C2.
- `channel.raid` is subscribed with condition `to_broadcaster_user_id` only (`app/Services/UserEventSubManager.php:46-50,489-491 @861ba40a`), so `from_broadcaster_user_id` is always the raider and never the account's own outgoing raid - which is what makes the new `ACTING_VIEWER_ID_FIELDS` entry safe for C1's ordering.
- A real `channel.chat.notification` also carries `message.text`, `color` and `badges` (`ChatNotificationSubscriptionTest.php:44-48 @861ba40a`); the payload C9's test posts omits them, so its "name nowhere in the JSON" assertion does not exercise the message body. C9 does not claim it does, and `message` is not in `SUPPRESSED_FIELDS`.
- C11 is compound - a fail-first run plus a full-suite result. The second sentence was checkable in-repo and checks out, so the tag cost nothing here; a suite count belongs in `[code]` or a Notes line rather than under `[unverified]`.
