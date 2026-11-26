## OL-2611-038 - feat(eventsub): floods become numbers - the last hundred kept, the rest counted

**Shipped:** 2026-11-26
**Commit:** `git log --grep=OL-2611-038`

### Surface
- `app/Services/Floods/FloodGate.php` - new file, the intake gate (`offer()`) and the lane-side application of a flood's count (`tick()`, summary row, kept rows, re-announce)
- `app/Jobs/FloodTick.php` - new file, the job that runs `FloodGate::tick()` on the streamer's lane
- `app/Http/Controllers/TwitchEventSubController.php` - `handleTwitchEvent()` asks `FloodGate::offer()` before writing the row; import
- `app/Services/Alerts/AlertBursts.php` - new `grow()` adds counted events to an existing burst
- `app/Services/StreamSessionService.php` - `handleEvent()` takes `$times` for the per-event counter
- `app/Enums/DeliveryOutcome.php` - new case `FloodCounted`
- `app/Http/Controllers/StreamSessionController.php` - `flood_count` summed in delivery `no_target`, `event_counts`, follows, redemptions and redemption cost
- `app/Http/Controllers/DashboardController.php` - last session's `moments` sums `flood_count`; `DB` import
- `app/Services/Lists/RecentEventFormatter.php` - a summary row formats as "+N more follows" / "+N more redemptions: <reward>"
- `resources/js/composables/useTwitchEventRules.ts` - `followers_total` rule gains `byPath: 'event.flood_count'`
- `resources/js/components/EventsTable.vue` - a summary row's "who" reads "+N more"
- `resources/js/pages/dashboard/stream-sessions.vue` - `flood_counted` in the `Delivery` type and the no-target sentence
- `resources/js/utils/deliveryOutcome.ts` - label for `flood_counted`
- `resources/js/utils/deliveryOutcome.test.ts` - mirror test lists thirteen outcomes
- `resources/js/stores/overlayState.test.ts` - new file
- `resources/help/reference/eventsub-tags/alert-bursts.md` - paragraph on floods
- `tests/Feature/FloodGateTest.php` - new file
- `tests/Feature/StreamSessionDeliveryTest.php` - expected `no_target` includes `flood_counted`; new test for a summary row in session stats
- `tests/Feature/DeliveryLedgerTest.php` - vocabulary pin includes `flood_counted`, unscored
- `CLAUDE.md` - section "Floods become numbers"

### Claims
- **C1** [code] `FloodGate::EVENT_TYPES` is `channel.follow` and `channel.channel_points_custom_reward_redemption.add`; `PASS_THROUGH` and `KEPT` are 100, `KEEP_OPEN_SECONDS` equals `AlertBursts::KEEP_OPEN_SECONDS`, `TICK_SECONDS` is 2, `DEDUP_SECONDS` is 900.
- **C2** [code] `FloodGate::offer()` returns `PASS` for any other event type, `DUPLICATE` when the message id is in the cache as counted, `PASS` for the first `PASS_THROUGH` events of a burst, and `COUNTED` after that.
- **C3** [code] The burst kind is `AlertBursts::kindOf()`, so redemptions are counted per reward.
- **C4** [code] A `COUNTED` event writes no `twitch_events` row and dispatches no `ProcessTwitchEvent`; `handleTwitchEvent()` returns `stored` for it and `duplicate` for `DUPLICATE`. The gate runs after `TwitchPayloadScrubber::scrub()` and only when the broadcaster has an account.
- **C5** [code] A `COUNTED` event increments the cache counter `flood:{user}:{kind}:dropped`, appends its payload to a list capped at `KEPT`, remembers its message id for `DEDUP_SECONDS`, and dispatches `FloodTick` (delayed `TICK_SECONDS`) at most once per 60 s window via `Cache::add`.
- **C6** [code] `FloodTick` runs on `ProcessTwitchEvent::queueFor()` of the broadcaster, with `$tries` 1.
- **C7** [code] `FloodGate::tick()` with a new count N: adds N to one summary row (`outcome` `flood_counted`, `event_data.flood_count`), calls `EventMeter::record($userId, N)` and `StreamSessionService::handleEvent($user, $eventType, [], N)`, broadcasts one `TwitchEventReceived` whose data is the newest kept payload plus `flood_count` N and a `flood-` prefixed `message_id`, marked feed-handled, and calls `AlertBursts::grow()`.
- **C8** [code] When `AlertBursts::grow()` answers `REANNOUNCE`, the newest kept payload is taken off the list, written as a row of its own, subtracted from the summary, and passed to `fireAlert()` with `burst_count` and `burst_started_at`.
- **C9** [code] `tick()` dispatches the next `FloodTick` while N was above zero or the burst is still open at intake; otherwise it finishes: inserts the kept payloads as rows with outcome `burst_absorbed`, subtracts their number from the summary, feeds one recent-events line from the summary when its count is above zero and deletes the summary when it is zero, then forgets the flood's cache keys.
- **C10** [code] `StreamSessionService::handleEvent()` adds `step * max(1, $times)` to the per-event control; the cheer branch ignores `$times`.
- **C11** [code] `StreamSessionController` counts a row as `COALESCE((event_data->>'flood_count')::int, 1)` in `event_counts`, follows, redemptions and (multiplied by reward cost) redemption cost, and reports `no_target.flood_counted` as the sum of `flood_count` over `flood_counted` rows, included in `no_target.total`.
- **C12** [code] `DeliveryOutcome::FloodCounted` has value `flood_counted` and `isScored()` false.
- **C13** [test] `tests/Feature/FloodGateTest.php`, through the real batch endpoint with `ProcessTwitchEvent` and `FloodTick` faked: 300 follows store 100 rows, queue 100 jobs and one tick, every result `stored`; a tick makes one summary of 200 and one broadcast naming the newest follower with a `flood-` message id, feed-handled; the per-stream follow counter moves from 7 to 157 for 250 follows; 1,000 follows finish as 100 passed-through rows, 100 kept rows (newest last) and a summary of 800; a 150-follow flood leaves no summary; a redelivered counted follow is `duplicate` and not counted again; 250 subs are all stored; redemptions of two rewards flood separately; an open burst due a re-announce plays one alert with count 202 and leaves the summary at 199; a feed list receives exactly `+800 more follows`; the formatter writes both summary forms.
- **C14** [test] `StreamSessionDeliveryTest` asserts a session with one delivered follow and a summary row of 5,000 reports follows 5,001, `event_counts['channel.follow']` 5,001 and `no_target.flood_counted` 5,000.
- **C15** [test] `resources/js/stores/overlayState.test.ts` asserts `followers_total` is 202 after a follow, a flood event of 200 and a follow, and that the flood event sets the latest follower name.
- **C16** [unverified] With `offer()` forced to `PASS`, eight of the eleven `FloodGateTest` tests failed (small flood, subs and formatter passed); the C14 test failed against the previous `StreamSessionController`; the C15 test failed without the `byPath` on the rule.

### Unchanged
- The handling of the first `PASS_THROUGH` events of a flood, and of every event of every other type, goes through `ProcessTwitchEvent` and `processStoredEvent()` exactly as before; neither is in the diff beyond the gate in `handleTwitchEvent()`.
- `UnifiedEventFeedService` lists rows as they are; the summary row is one row in it, rendered as "+N more" by `EventsTable.vue`, and the service is not in the diff.
- The unused `useProcessEvent.ts` applier gives `by` precedence over `byPath` and is not in the diff; the overlay applies rules through the store.

### Risk
A follow or redemption past the first hundred of a burst no longer exists as its own row: it cannot
be replayed, its name is in no log, and only the last hundred of a flood are kept by name. A steady
trickle of follows with gaps under four seconds counts as one burst at intake, so after a hundred of
them the rest are counted too. The counts live in the evictable cache Redis.
