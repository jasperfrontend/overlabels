## OL-2609-104 - fix(viewers): suppress the two payload shapes that do not call the viewer `user_*`

**Shipped:** 2026-09-18
**Commit:** `git log --grep=OL-2609-104`

Remedies OL-2609-103 audit F1, F2; F3 recorded.

### Surface
- `app/Services/TwitchPayloadScrubber.php` - `SUPPRESSED_FIELDS` and `ACTING_VIEWER_ID_FIELDS` constants, `actingViewerId()`, second nulling loop in `scrub()`
- `app/Http/Controllers/TwitchEventSubController.php` - the scrub call site asks `actingViewerId()` for the id instead of reading `$event['user_id']`
- `app/Services/ViewerErasureService.php` - the `twitch_events` delete matches every field in `ACTING_VIEWER_ID_FIELDS`
- `tests/Feature/ViewerErasureTest.php` - four new cases; `postErasureNotification()` takes a subscription type
- `docs/changelog/claims/2026/09/OL-2609-103/remedy.md` - new file

### Claims
- **C1** [code] `TwitchPayloadScrubber::actingViewerId()` returns the first non-empty scalar of `user_id`, `from_broadcaster_user_id`, `chatter_user_id`, cast to string, and null when the payload carries none of them (corrects OL-2609-103 C10, audit F1, F2).
- **C2** [code] `TwitchEventSubController` passes `forceAnonymous: $this->erasures->isSuppressed(TwitchPayloadScrubber::actingViewerId($event))` at the one scrub call site, which is still before `enrichEventWithUserAvatars()`. A raid and a chat notice are now tested against the suppression list; before this they passed null (corrects OL-2609-103 C2, audit F1).
- **C3** [code] `scrub()` nulls `SUPPRESSED_FIELDS` in a second loop that runs only when `$forceAnonymous` is true: `from_broadcaster_user_id|_login|_name|_avatar`, `chatter_user_id|_login|_name|_avatar`, `system_message`. An `is_anonymous` payload alone does not reach that loop, so nothing about an anonymous cheer changes.
- **C4** [code] `system_message` is in that list because `channel.chat.notification` repeats the chatter's display name in the sentence Twitch composed for chat, so nulling the `chatter_user_*` trigram alone leaves the name in the stored row (audit F2).
- **C5** [code] Nothing else in either payload is touched. A raid keeps `to_broadcaster_user_*` and `viewers`; a chat notice keeps `notice_type` and the `sub` object, so the ledger the rows exist for is intact.
- **C6** [code] `ViewerErasureService::erase()` deletes `twitch_events` rows matching `$twitchId` on ANY field in `TwitchPayloadScrubber::ACTING_VIEWER_ID_FIELDS`, not `user_id` alone. The field list is one public constant shared with the scrubber, so a new payload shape is one entry rather than two (corrects OL-2609-103's Unchanged line "erase() already deleted the rows", audit F2).
- **C7** [test] `ViewerErasureTest` "anonymises a raid from a viewer who asked to be forgotten" posts a signed `channel.raid` notification whose raider has used `!forgetme` and asserts the stored `event_data` has null `from_broadcaster_user_id|_login|_name`, keeps `to_broadcaster_user_id` and `viewers`, and contains the raider's display name nowhere in its JSON.
- **C8** [test] The same file's "leaves a raid from a viewer who has not asked completely alone" asserts a raid from a viewer with no erasure keeps `from_broadcaster_user_name`.
- **C9** [test] The same file's "anonymises a chat notification from a viewer who asked to be forgotten" posts a signed `channel.chat.notification` and asserts the stored `event_data` has null `chatter_user_id|_login|_name`, keeps `notice_type` and `sub.is_prime`, and contains the chatter's display name nowhere in its JSON.
- **C10** [test] The same file's "deletes the events that name the viewer under Twitch other payload names" asserts `!forgetme` deletes a `channel.raid` row keyed by `from_broadcaster_user_id` and a `channel.chat.notification` row keyed by `chatter_user_id`, and leaves another viewer's raid row standing.
- **C11** [unverified] C7, C9 and C10 were each run against the pre-fix tree on 2026-09-18 and failed, all three on "Failed asserting that '555000' is null" or the surviving row; C8 passed before and after. The full suite is green after the fix: 2293 passed, 6 skipped.
- **C12** [code] `StreamSessionService::mergeChatters()` writes the chatter logins themselves to `chat:chatters:{user_id}` with `CHATTER_SET_TTL` of 43200 seconds, so an erased viewer's login is held there for up to 12 hours or until the go-live reset forgets the set. OL-2609-103's Unchanged line "a count is a number, not a person" describes `message_count` and is not true of the unique-chatter set beside it (corrects OL-2609-103 Unchanged, audit F3).

### Unchanged
- The two help pages OL-2609-103 shipped are not in this diff. Their raid sentence ("it simply arrives without a name") is the promise audit F1 found unimplemented; the audit offered covering `from_broadcaster_user_*` or dropping "raid", and C2 and C3 cover the fields instead, so the shipped copy stands as written.
- `BotChatStatsController` and the `latest_chatter_*` pair are not in the diff. That half of OL-2609-103 already reads `latest_chatter_id`, which is the chatter's Twitch id in the shape the bot sends it.
- `StreamSessionService::mergeChatters()` is not in the diff. C12 states what it keeps rather than changing it: dropping an erased viewer's login from the set would make `unique_chatters_this_stream` disagree with `chat_messages_this_stream`, which still counts them.
- `tests/Unit/TwitchPayloadScrubberTest.php` is not in the diff. It covers the `is_anonymous` branch, which C3 leaves alone; the `$forceAnonymous` branch is covered through the feature path in `ViewerErasureTest`, as it was in OL-2609-103.
- `ViewerErasureService::NAME_CONTROL_KEYS` and `blankNameControls()` are not in the diff. They blank a control currently showing the name; the rows this change reaches are event rows, which no control reads.

### Risk
`!forgetme` now deletes more rows than it did: every `channel.raid` and `channel.chat.notification`
row naming that viewer, including chat-notice rows a later Plus Points count would have read. Rows
already deleted stay deleted.

A raid from an erased viewer reaches the streamer with no raider name, so the alert and the events
feed render the raid with an empty name, as a follow from an erased viewer already did.
