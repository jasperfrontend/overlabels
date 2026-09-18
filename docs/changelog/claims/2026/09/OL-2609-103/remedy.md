## Remedy of OL-2609-103 - fix(viewers): close the two surfaces that wrote an erased viewer straight back

**Remedied:** 2026-09-18
**Claim:** OL-2609-104

| Finding | Outcome | What |
|---------|---------|------|
| F1 | FIXED | `TwitchPayloadScrubber::SUPPRESSED_FIELDS` nulls `from_broadcaster_user_*` and `actingViewerId()` reads `from_broadcaster_user_id`, so a raid is tested against the suppression list; `ViewerErasureTest` "anonymises a raid from a viewer who asked to be forgotten" failed then passed. Both help sentences kept as shipped |
| F2 | FIXED | The same list nulls `chatter_user_*` and `system_message`, and `ViewerErasureService::erase()` now deletes `twitch_events` matching any of `TwitchPayloadScrubber::ACTING_VIEWER_ID_FIELDS`; `ViewerErasureTest` "anonymises a chat notification from a viewer who asked to be forgotten" and "deletes the events that name the viewer under Twitch other payload names" each failed then passed |
| F3 | RECORD | OL-2609-104 C12 states what `StreamSessionService::mergeChatters()` keeps - the logins themselves, in `chat:chatters:{user_id}` for 12 hours - in place of "a count is a number, not a person" |
