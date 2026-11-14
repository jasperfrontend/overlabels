## OL-2611-011 - feat(alerts): alerts take turns, and a gift bomb is one alert

**Shipped:** 2026-11-14
**Commit:** `git log --grep=OL-2611-011`

### Surface
- `resources/js/utils/alertQueue.ts` - new: `createAlertQueue()`, a timestamp-ordered alert queue with keyed in-place updates, `GAP_MS`, `MAX_WAITING`, `MAX_WAIT_MS`
- `resources/js/utils/alertQueue.test.ts` - new: 12 Vitest cases for the queue
- `resources/js/components/OverlayRenderer.vue` - `handleAlertTriggered()` enqueues instead of showing; `showAlert()` no longer times itself; sound and TTS keyed to show time (`markTtsShown`, `markTtsDropped`, `scheduleTts`); `alertSequence` and `alertTimeout` removed
- `resources/js/composables/useNotificationQueue.ts` - deleted (had no importer)
- `app/Events/AlertTriggered.php` - optional `eventType` constructor argument, broadcast as `alert.event_type`
- `app/Http/Controllers/TwitchEventSubController.php` - passes `$mapping->event_type` to `AlertTriggered`; absorbs gifted subs via `GiftSubAbsorber` before resolving a mapping; arms it on a mapped `channel.subscription.gift`
- `app/Services/Alerts/GiftSubAbsorber.php` - new: per-user countdown of gifted subs a gift alert already announced
- `app/Enums/DeliveryOutcome.php` - new no_target case `GiftAbsorbed` (`gift_absorbed`)
- `app/Http/Controllers/StreamSessionController.php` - `gift_absorbed` counted in the delivery `no_target` breakdown and total
- `resources/js/utils/deliveryOutcome.ts` - label `gift_absorbed` => "in a gift alert"
- `resources/js/pages/dashboard/stream-sessions.vue` - `no_target.gift_absorbed` typed and listed as "part of a gift alert"; sentence lead "had no alert of their own"
- `resources/js/utils/deliveryOutcome.test.ts` - outcome list gains `gift_absorbed`
- `tests/Feature/DeliveryLedgerTest.php` - pinned vocabulary gains `gift_absorbed`, unscored
- `tests/Feature/StreamSessionDeliveryTest.php` - expected `no_target` gains `gift_absorbed => 0`
- `tests/Feature/AlertEventTypeBroadcastTest.php` - new: 3 tests for `event_type` on the broadcast
- `tests/Feature/GiftSubAbsorbTest.php` - new: 6 tests for gift absorbing through the webhook

### Claims
- **C1** [code] `OverlayRenderer.vue` `handleAlertTriggered()` calls `alertQueue.enqueue()` and never calls `showAlert()` directly.
- **C2** [test] `alertQueue.test.ts` "plays back-to-back alerts one after another instead of replacing them" asserts three alerts enqueued together are shown in turn, each after the previous one's duration plus the gap.
- **C3** [test] `alertQueue.test.ts` "orders waiting alerts by timestamp, ties in arrival order" asserts that ordering.
- **C4** [test] `alertQueue.test.ts` "keeps the place of an alert whose source is still loading" asserts an alert with a pending source is shown before a later alert whose source was ready.
- **C5** [code] `createAlertQueue()` drops the oldest waiting alert when more than `MAX_WAITING` (25) wait, and drops an alert that waited longer than `MAX_WAIT_MS` (180000) instead of showing it; both call `onDrop`.
- **C6** [test] `alertQueue.test.ts` "updates a live alert on screen in place and restarts its clock" asserts a keyed alert matching the one on screen re-shows with new data and extends the hide time.
- **C7** [code] `OverlayRenderer.vue` sets the queue `key` only when `alertData.event_type` ends with `.progress`.
- **C8** [code] `OverlayRenderer.vue` calls `playAlertSound()` from the queue's `show` callback, not on arrival, except for an alert whose source plan is `none`.
- **C9** [code] `handleTtsAudioReady()` stores the audio URL on a pending entry whose `shownAt` is null and plays it from `markTtsShown()`; audio for an entry marked `dropped` is discarded.
- **C10** [code] `AlertTriggered::broadcastWith()` includes `event_type` in the `alert` array, null when not given.
- **C11** [test] `AlertEventTypeBroadcastTest` asserts a Twitch alert for `channel.poll.progress` and for `channel.cheer` broadcasts that type while `data` lacks `event.type`.
- **C12** [unverified] All three `AlertEventTypeBroadcastTest` tests failed with the `AlertTriggered` and `TwitchEventSubController` changes stashed.
- **C13** [code] `TwitchEventSubController` records `DeliveryOutcome::GiftAbsorbed` and renders no alert for a `channel.subscribe` that `GiftSubAbsorber::absorb()` accepts.
- **C14** [code] `GiftSubAbsorber::absorb()` returns false for any event whose `is_gift` is not `true`.
- **C15** [code] `GiftSubAbsorber::expect()` is called only for `channel.subscription.gift` with a resolved mapping that has a template.
- **C16** [code] The countdown is cached under `gift_absorb:<user id>` for `GiftSubAbsorber::WINDOW_SECONDS` (120), reset on every expect and absorb.
- **C17** [test] `GiftSubAbsorbTest` "a gift bomb of three fires one gift alert and absorbs its three recipients" asserts one `AlertTriggered` and three `gift_absorbed` rows.
- **C18** [test] `GiftSubAbsorbTest` asserts a non-gifted sub alerts during a countdown, gifted subs alert with no gift mapping, past the total, and after the window.
- **C19** [unverified] On prod (2026-11-14) a `channel.subscribe` with `is_gift` true carries no gifter field, and recipients arrived 0 to 13 s after their `channel.subscription.gift` (25 gifts: 13 s).

### Unchanged
- `useGiftBombDetector` filters the `.twitch.event` data stream in `OverlayRenderer.vue`, which this change does not touch; it is not in the diff. Its buffer key uses the event's `user_id`, which for a gifted sub is the recipient.
- `ExternalAlertService` and `ExternalEventController::replay` construct `AlertTriggered` without the new argument, so external alerts broadcast `event_type: null` and always queue; neither file is in the diff.
- `EventTemplateMapping::resolveForEvent()` and its amount variants are called as before for every event the absorber does not take.

### Risk
Behaviour change for every overlay: an alert that arrives while another shows now waits instead of
cutting it off, and can be dropped after 3 minutes or when 25 wait. Sound and TTS move with it.
Gifted-sub alerts stop firing for accounts with a Gift Subscription alert assigned. An OBS source
running the previous build keeps the old behaviour until it reloads.
