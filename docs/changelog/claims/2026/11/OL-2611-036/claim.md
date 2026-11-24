## OL-2611-036 - feat(alerts): many of the same Twitch event at once play one alert

**Shipped:** 2026-11-24
**Commit:** `git log --grep=OL-2611-036`

### Surface
- `app/Services/Alerts/AlertBursts.php` - new file, the burst state per streamer and kind (hold, join, flush, forget) and its timing constants
- `app/Jobs/FlushAlertBurst.php` - new file, the delayed job that plays a held event's own alert or one burst alert
- `app/Http/Controllers/TwitchEventSubController.php` - alert block moved into `alertOrBurst()` and public `fireAlert()`; imports for the two new classes
- `app/Enums/DeliveryOutcome.php` - new case `BurstAbsorbed`
- `app/Http/Controllers/StreamSessionController.php` - `burst_absorbed` counted in the delivery summary's `no_target`
- `resources/js/pages/dashboard/stream-sessions.vue` - `burst_absorbed` in the `Delivery` type and the no-target sentence
- `resources/js/utils/deliveryOutcome.ts` - label for `burst_absorbed`
- `resources/js/utils/deliveryOutcome.test.ts` - mirror test lists twelve outcomes
- `tests/Feature/AlertBurstTest.php` - new file
- `tests/Feature/DeliveryLedgerTest.php` - vocabulary pin includes `burst_absorbed`, unscored
- `tests/Feature/StreamSessionDeliveryTest.php` - expected `no_target` includes `burst_absorbed`

### Claims
- **C1** [code] `AlertBursts::OPEN_WINDOW_SECONDS` is 1 and `AlertBursts::KEEP_OPEN_SECONDS` is 4.
- **C2** [code] `AlertBursts::kindOf()` returns a kind for `channel.follow`, `channel.subscription.message`, `channel.cheer`, a non-gifted `channel.subscribe`, and `channel.channel_points_custom_reward_redemption.add` (suffixed with `reward.id` when present), and null for every other event type and for a `channel.subscribe` with `is_gift` true.
- **C3** [code] In `TwitchEventSubController::alertOrBurst()`, an event is offered to `AlertBursts` only when its kind is not null and the user has an enabled `EventTemplateMapping` for that event type with an existing template.
- **C4** [code] On `AlertBursts::HOLD`, `alertOrBurst()` dispatches `FlushAlertBurst` delayed by `OPEN_WINDOW_SECONDS` and plays no alert.
- **C5** [code] On `AlertBursts::JOINED`, `alertOrBurst()` records `DeliveryOutcome::BurstAbsorbed` on the row and plays no alert.
- **C6** [code] `FlushAlertBurst` runs on `ProcessTwitchEvent::QUEUE` with `$tries` 1.
- **C7** [code] `FlushAlertBurst::handle()` sets `event.burst_count` and `event.burst_started_at` on the data only when `AlertBursts::flush()` returns a burst (two or more events), then calls `TwitchEventSubController::fireAlert()`.
- **C8** [code] `FlushAlertBurst::failed()` calls `AlertBursts::forget()` for its user and kind.
- **C9** [code] For `channel.cheer`, `AlertBursts::offer()` returns `ALONE` when the cheer's non-anonymous `user_id` is already among the burst's actors.
- **C10** [code] The gift recipient check (`GiftSubAbsorber::absorb()`) and the late check (`isLate()`) in `processStoredEvent()` both run before `alertOrBurst()` is reached.
- **C11** [code] `fireAlert()` holds the alert block that `processStoredEvent()` held before: `EventTemplateMapping::resolveForEvent()`, `GiftSubAbsorber::expect()` for `channel.subscription.gift`, `renderEventAlert()`, and `DeliveryOutcome::NoMapping` when no mapping resolves.
- **C12** [code] `DeliveryOutcome::BurstAbsorbed` has value `burst_absorbed` and `isScored()` false.
- **C13** [test] `tests/Feature/AlertBurstTest.php` asserts, with `FlushAlertBurst` faked and run by hand: a lone follow plays its own alert with no burst count after the flush; three follows before the flush play one alert with count 3 and two `BurstAbsorbed` rows; every follow in a burst still dispatches `TwitchEventReceived`; an open burst absorbs a follow within the keep-open gap and a follow after the gap opens a new hold; one user's burst does not hold another user's follow; a follow and a sub are separate bursts; gifted subs are never held; a repeat cheer from one viewer alerts on its own while a second viewer's cheer joins; an unmapped event is not held and is `NoMapping`; a late notification is not held and is `Late`; a failed flush lets the next event hold again.
- **C14** [unverified] With the `if ($alerts)` branch in `alertOrBurst()` disabled, six of the twelve `AlertBurstTest` tests failed (lone follow, three follows, keep-open gap, other streamer, cheers, failed flush) and the other six passed.

### Unchanged
- The per-event work before the alert decision in `processStoredEvent()` (avatar enrichment, row save, `EventMeter`, `refreshCachesForEvent()`, `LivingTitleService`, `StreamSessionService::handleEvent()`) and the `TwitchEventReceived` broadcast after it are not in the diff, so every event in a burst still does them.
- `replay()`, `replayForUser()` and `testCheer()` call `renderEventAlert()` directly and do not pass through `alertOrBurst()`; none of them is in the diff.
- External donation events go through `ExternalAlertService`, which is not in the diff.

### Risk
Every alert for a burstable event type now plays one to two seconds after its event is processed
(the hold), including a lone test event from the Twitch CLI. Under a local sync queue the flush runs
at once, so bursts never form in local development.
