## OL-2611-037 - feat(alerts): burst triggers, so a burst can have an alert of its own

**Shipped:** 2026-11-24
**Commit:** `git log --grep=OL-2611-037`

### Surface
- `app/Models/EventTemplateMapping.php` - five `burst.*` entries in `EVENT_TYPES`; `BURST_PREFIX`, `burstTypeFor()`, `baseEventType()`
- `app/Http/Controllers/TwitchEventSubController.php` - `alertOrBurst()` holds when the event's own OR its burst trigger is mapped; `fireAlert()` resolves the burst trigger first when `burst_count` is set
- `app/Services/Alerts/AlertEventSamples.php` - a burst trigger's samples are its base event's with `burst_count` and `burst_started_at` added
- `resources/help/reference/eventsub-tags/alert-bursts.md` - new file, reference entry for the burst tags
- `resources/help/reference/eventsub-tags/subscription-gifts.md` - the note describes the gift countdown instead of an 8-second window
- `tests/Feature/AlertBurstTest.php` - four new tests
- `tests/Feature/EventSubExpansionTest.php` - reachability guard reads burst triggers through their base event; new guard that every burst trigger's base event bursts

### Claims
- **C1** [code] `EventTemplateMapping::EVENT_TYPES` contains `burst.channel.follow`, `burst.channel.subscribe`, `burst.channel.subscription.message`, `burst.channel.cheer` and `burst.channel.channel_points_custom_reward_redemption.add`, each placed directly after its base event type.
- **C2** [code] `EventTemplateMapping::BURST_PREFIX` is `burst.`; `burstTypeFor()` prepends it and `baseEventType()` strips it, returning any other type unchanged.
- **C3** [code] `TwitchEventSubController::alertOrBurst()` offers an event to `AlertBursts` when an enabled mapping with a template exists for the event type or for `burstTypeFor()` of it.
- **C4** [code] `TwitchEventSubController::fireAlert()` resolves `burstTypeFor($eventType)` first when `event.burst_count` is set, and falls back to the event type's own mapping when that resolves nothing.
- **C5** [code] `AlertEventSamples::for()` builds a burst trigger's samples from `twitchPayloads()` of its base event type, with `subscription.type` set to the base type and `burst_count` 24 and `burst_started_at` added to the event.
- **C6** [test] `AlertBurstTest` asserts: a burst plays the alert mapped to `burst.channel.follow` when one is assigned; a lone follow plays the `channel.follow` alert when both are assigned; with only `burst.channel.follow` mapped, a lone follow is `NoMapping` and a burst of two plays the burst alert; `burstTypeFor()` and `baseEventType()` round-trip.
- **C7** [test] `EventSubExpansionTest` asserts every `EVENT_TYPES` key, read through `baseEventType()`, is in `UserEventSubManager::SUPPORTED_EVENTS`.
- **C8** [test] `EventSubExpansionTest` asserts `AlertBursts::kindOf()` is not null for the base event of every `burst.*` key in `EVENT_TYPES`.
- **C9** [test] `AlertEventSamplesTest` (unchanged) passes with the five new keys: each has at least one sample with tags.
- **C10** [unverified] With the burst-trigger lookup in `fireAlert()` disabled, the first and third tests named in C6 failed and the rest of `AlertBurstTest` passed.
- **C11** [unverified] The `alert-bursts` reference page renders its example as a code block with a Copy button (checked in Firefox on overlabels.test).

### Unchanged
- `AlertBursts` decides which events burst and when; no change to it is in the diff, so the five triggers cover exactly the kinds it already produced in OL-2611-036.
- `UserEventSubManager::SUPPORTED_EVENTS` is not in the diff: no burst type is ever subscribed to at Twitch.
- The Triggers tab component (`TriggerManager.vue`) and the trigger validation in `OverlayTemplateController` read `EVENT_TYPES` and are not in the diff.

### Risk
None for existing alerts: nothing is assigned to the new triggers until a streamer does it.
