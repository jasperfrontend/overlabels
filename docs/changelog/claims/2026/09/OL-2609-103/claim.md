## OL-2609-103 - fix(viewers): close the two surfaces that wrote an erased viewer straight back

**Shipped:** 2026-09-18
**Commit:** `git log --grep=OL-2609-103`

Acts on OL-2609-099 audit F4, which Remy recorded in OL-2609-101 C6-C9 rather than fixing. The
recorded boundary was real and the claim describing it is accurate; this removes the boundary instead
of documenting it, because the promise was already shipped in two user-facing places.

### Surface
- `app/Services/TwitchPayloadScrubber.php` - `scrub()` gains `bool $forceAnonymous = false`, OR-ed with the existing `is_anonymous` check
- `app/Http/Controllers/TwitchEventSubController.php` - passes `forceAnonymous: $this->erasures->isSuppressed($event['user_id'])`; `ViewerErasureService` injected and imported
- `app/Http/Controllers/Api/Internal/BotChatStatsController.php` - accepts optional `latest_chatter_id`; nulls `latest_chatter_name` and `latest_chat_message` when that viewer is suppressed; `ViewerErasureService` injected and imported
- `resources/help/pages/viewers.md` - the "stops it happening again" callout now lists all three outcomes
- `resources/help/pages/bot/commands.md` - the `!forgetme` section states that events still reach the streamer without a name
- `tests/Feature/ViewerErasureTest.php` - four new cases, plus a signed-webhook helper and a control helper
- `tests/Feature/BMACWebhookTest.php` - test name and section comment corrected (OL-2609-097 audit Notes)

Bot repository (`overlabels-bot`, committed separately):
- `src/chatStats.js` - buckets carry `latestId`; the flushed summary carries `latest_chatter_id`
- `test/chatStats.test.js` - asserts the id travels with the name/text pair

### Claims
- **C1** [code] `TwitchPayloadScrubber::scrub()` nulls `user_id`, `user_login`, `user_name` and `user_avatar` when `$forceAnonymous` is true, by the same branch that handles `is_anonymous`. The two are OR-ed, so neither disables the other.
- **C2** [code] `TwitchEventSubController` computes `forceAnonymous` from `ViewerErasureService::isSuppressed()` on `$event['user_id']`, at the existing scrub call site, which is before `enrichEventWithUserAvatars()`. An erased viewer therefore has no avatar fetched for them either.
- **C3** [code] Only the acting viewer's identity is nulled. `broadcaster_user_id` and every other field are untouched, so the streamer still receives the event, the alert and the counter.
- **C4** [code] `BotChatStatsController` validates `latest_chatter_id` as `nullable|string|max:32`. An older bot that does not send it yields null, `isSuppressed(null)` is false, and the pair is written exactly as before. The field is additive; no bot deploy is required for the app to be correct.
- **C5** [code] When the latest chatter is suppressed, `latest_chatter_name` and `latest_chat_message` are passed as null to `applyChatSummary()`, which skips a null or empty value. `message_count` and the unique-chatter set are unaffected.
- **C6** [test] `ViewerErasureTest` posts a real signed `channel.follow` notification to `/api/twitch/webhook` for an erased viewer and asserts the stored `twitch_events.event_data` has null `user_id`, `user_login` and `user_name`, retains `broadcaster_user_id`, and contains the display name nowhere in its JSON.
- **C7** [test] The same file asserts a webhook from a non-erased viewer keeps its `user_name`, so the guard is not a blanket anonymiser.
- **C8** [test] The same file posts a chat summary naming an erased viewer and asserts `latest_chatter_name` and `latest_chat_message` are not written while `chat_messages_this_stream` still reaches 3, and a second case asserts a non-erased viewer's name is still written.
- **C9** [unverified] Both new guards were reverted individually on 2026-09-18 and the suite re-run: the webhook case and the chat-stats case failed, the other fourteen passed. Restored and re-run green.
- **C10** [code] `git grep isSuppressed -- app/` now returns five call sites: the three from OL-2609-099 plus these two. There is no sixth writer of viewer identity; donation events carry a donor identity from the service, not a Twitch viewer id.

### Unchanged
- `ViewerErasureService` is not in the diff. `erase()` already deleted the rows; what was missing was stopping them being rewritten, which belongs at the two write sites rather than in the eraser.
- `viewer_erasures` still holds a Twitch id and a date and nothing else. Honouring the chat-stats case needed an id from the bot rather than a login stored here, which would have contradicted what `/help/viewers` tells viewers is kept.
- The `tower:status` cooldown key noted in the OL-2609-099 audit still receives an erased viewer's id before the suppression check. It is a cache key with a short TTL holding no viewer-scoped value, and moving the check would change the cooldown's meaning; left as the audit described it.
- `BotChatStatsController` still applies `message_count` and the unique-chatter set for an erased viewer. A count is a number, not a person, and removing them from it would misreport the channel's activity.

### Risk
The bot must deploy for the chat-stats half to take effect; until it does, `latest_chatter_id` is
absent and that surface behaves as it did before. The webhook half is app-only and takes effect on
deploy.

A streamer whose regular has used `!forgetme` will see follow and sub alerts arrive with no name.
That is the intended trade and both help pages now say so, but it is a visible behaviour change with
no in-product explanation at the moment it happens.
