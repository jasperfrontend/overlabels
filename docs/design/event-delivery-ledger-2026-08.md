# B2: closing the ledger row

Design note for B2 of `event-delivery-heal-2026-08.md`. Written 2026-08-26 after Jasper settled
the three definitional questions; nothing here is built yet.

## The three decisions (settled)

1. **The unit is the alert.** An inbound event that produced an alert with overlay work is scored.
   Everything else gets an outcome in the `no_target` family and is reported, never counted
   against a rate. An account with no alerts (TenzinNiznet tonight: no tokens, no overlays, no
   alerts, one live stream) therefore has no scored rows at all - its events are `no_mapping`,
   and the zero connections B1 recorded for it is "nothing", not a failure.
2. **Delivered to nobody is its own outcome, `no_listener`, not `failed`.** It is the most useful
   line in a debrief ("your overlay was not open at 21:04") and it is not the app's fault.
3. **90 days.** The rows already prune at 90 days (`prune:twitch-events`, `prune:external-events`);
   outcomes ride along. The data barely holds value past that.

## The correlation key already exists

The open question in the heal doc was how a queued broadcast learns which inbound row it belongs
to. It does not need to learn anything new: `renderEventAlert()` mints `$alertId` (a UUID) in the
request, and it is on the wire at `payload['alert']['alert_id']`, which is exactly the `$payload`
`MeteredBroadcaster::broadcast()` receives in the worker. So the request stamps `alert_id` on the
row when it builds the alert, and the worker closes the row by `alert_id`. Nothing added to the
payload, no new reference type.

One row is at most one alert: `EventTemplateMapping::resolveForEvent()` and its external twin both
return a single mapping (`?self`), variants included. Single columns suffice.

## Columns

Four nullable columns, additive, on both `twitch_events` and `external_events`:

| column | type | written by |
|---|---|---|
| `alert_id` | `uuid`, nullable, indexed | request, when an alert with overlay work is broadcast |
| `outcome` | `string(32)`, nullable | request for `no_target`; worker or listener for the rest |
| `delivered_at` | `timestamp`, nullable | worker, on Reverb accepting the trigger |
| `connections` | `smallint`, nullable | worker, from B1's `subscription_count` |

`twitch_events.processed` stays exactly as it is (admin filter and `pending_events` use it).
`external_events.alert_dispatched` and `controls_updated` stay too; `outcome` is finer-grained,
not a replacement.

## Outcomes

One enum, `App\Enums\DeliveryOutcome` (string-backed), two families:

**Scored** - the row had an alert with overlay work:

- `delivered` - Reverb accepted it, `connections >= 1`
- `no_listener` - Reverb accepted it, `connections == 0`
- `failed` - the job exhausted its retries (`Payload too large`, Reverb down, ...)

**`no_target`** - never an alert, or one the overlay had nothing to do with. Reported, not scored:

- `no_mapping` - no enabled mapping for this event type
- `muted` - `alerts:muted` was on
- `chat_only` - the alert had no HTML, sound or TTS (A9)
- `unknown_user` - no account for the broadcaster id (row has `user_id = null` already)
- `duplicate` - never a row (A3 returns before the insert); listed for completeness of the
  reason vocabulary, not stored

`null` outcome with a non-null `alert_id` means "broadcast queued, not yet closed". It is a valid
in-flight state and, after the worker has had its `--tries=3`, a bug.

Not in the ledger: `TwitchEventReceived` (the raw event to static overlays on
`twitch-events.{id}`). It has no target, no `alert_id`, and no "should have hit the screen"
claim. B1's last-delivery cache already covers "was anything listening" for it.

## Writes

Three, and they are the whole feature:

1. **Request, `no_target`.** `handleTwitchEvent()` already knows every `no_target` reason at the
   point it takes the early exit (`:550-566` unknown user / no mapping; `renderEventAlert` `:610`
   muted; the A9 guard chat-only). Each exit sets `outcome` on the row it just created. The
   external path mirrors it in `ExternalAlertService::dispatch()`, which returns `false` on the
   same reasons today and would return the reason instead.
2. **Request, `alert_id`.** When `hasOverlayWork()` is true and `broadcast($alert)` is called, the
   row gets `alert_id`. `handleTwitchEvent()` currently discards the created model
   (`DB::transaction(fn () => TwitchEvent::create(...))`); assign it. On the external path
   `dispatch()` returns the alert id (or the `no_target` reason) so the controller can stamp the
   row it already holds - today it returns a bool and the controller sets `alert_dispatched`.
3. **Worker, close.** `MeteredBroadcaster::broadcast()` already has the payload and, after B1,
   the counts. If `payload['alert']['alert_id']` is present: one `UPDATE ... WHERE alert_id = ?`
   across both tables setting `outcome` (`delivered` / `no_listener` by the count on the
   `alerts.{id}` channel), `delivered_at`, `connections`. Zero rows matched is fine - replay and
   `testCheer` mint alert ids with no row behind them.

**Failure close** lives in a `JobFailed` listener, not in the broadcaster. `queue:work --tries=3`
means a bad broadcast hits the broadcaster three times; closing as `failed` on the first
exception would flip to `delivered` on a retry that succeeds, and the broadcaster cannot see the
attempt number. `Illuminate\Queue\Events\JobFailed` fires once, after the last attempt, with the
job payload; the serialised `BroadcastEvent` inside carries the `AlertTriggered` and its
`alertId`. The listener unserialises, checks `instanceof AlertTriggered`, and closes the row as
`failed`. Every other job class is ignored.

## Edge cases, decided

- **Replay** (`/external-events/{id}/replay`, `replayForUser`) writes no row; the alert id
  matches nothing; the close is a no-op. Replays are not scored.
- **`testCheer`** writes a row with `processed = true` that is reaped a minute later. It gets an
  `alert_id` like any other and may close before reaping. Harmless.
- **Chat-only** alerts never broadcast (A9), so they never get an `alert_id`. `outcome =
  chat_only` is set in the request.
- **Stream-status / control / list broadcasts** are not alerts and are not touched.
- **Redelivery** (A3) never reaches any of this.
- **Prune** deletes the row and the outcome with it. Nothing to add.

## Volume

Prod: ~15k Twitch rows a month, p50 75 per session, p90 200. Alerts are a fraction of rows (only
mapped types). One `UPDATE` by an indexed `uuid` per alert, in the worker. Inline is fine.

## Tests

Feature tests on the Twitch and external fire paths, each verified to fail first: `no_mapping`,
`muted`, `chat_only`, `unknown_user` set in-request; `alert_id` set only when the overlay has
work; the worker close sets `delivered` at `connections >= 1` and `no_listener` at 0 through the
same mocked-Pusher path B1 uses; the `JobFailed` listener closes `failed` and ignores other jobs;
a close with no matching row is a no-op. `DeliveryOutcome` cases pinned so a rename shows up.

## Deliberately out

- Proof of paint. Still nobody's.
- Whether TTS audio or the alert sound actually played. `TtsAudioReady` is a second broadcast with
  its own fate; not scored.
- The debrief itself (B4) and the `/wiring` wires (B3). This note only makes the rows honest.
- A backfill. Existing rows keep `outcome = null` and are simply "before the ledger".
