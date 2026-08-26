# Event delivery: heal first, then measure

Written 2026-08-26 from a repo audit prompted by the "what is uptime for a streamer" question.
Conclusion of that audit: the answer is a per-stream success-rate debrief, and the ledger it
needs mostly already exists (`twitch_events`, `external_events`, `overlay_access_logs`). But the
audit also turned up bugs on the delivery path that make any success rate a lie until fixed.

This document is the work list. Two piles, worked in order. Nothing in pile B starts until pile A
is merged, because every pile B number would be computed over the holes pile A closes.

Rules for every entry, no exceptions:

- One entry = one branch = one PR = one `/ship`. Small enough to review in five minutes.
- Every heal entry ships with a test that was **verified to fail before the fix** (`/rootcause`).
- Smallest diff that fixes the stated thing. Nothing "while I was in there".
- An entry marked **decision** does not start until Jasper has made the call written next to it.

---

## Pile A: heal (bugs, in order)

Ordered by how invisible the failure is today and how cheap the fix is. A1-A4 are the ones that
falsify a success rate; A5-A7 are real but smaller.

### A1. A revoked EventSub subscription still reads `enabled` locally

- **Symptom:** Twitch sends a `revocation` message; we log a warning and write a 5-minute cache
  key. `user_eventsub_subscriptions.status` is not touched, so the integrations page and
  `eventsub:monitor` both believe the sub is healthy until the 24-hour verify window catches it.
  Twitch told us and we dropped it.
- **Evidence:** `app/Http/Controllers/TwitchEventSubController.php:317-323`.
- **Fix:** in the revocation branch, update the matching row's `status` to the status Twitch sent
  (`authorization_revoked`, `user_removed`, `notification_failures_exceeded`, ...) and stamp
  `last_verified_at`. Nothing else.
- **Proof:** feature test posting a revocation payload; assert the row's status. Fails today.
- **Decision:** none.

### A2. Empty `catch (Throwable) {}` on the Twitch webhook path

- **Symptom:** any PHP `Error` (TypeError, a `ShouldBroadcastNow` event throwing, ...) inside
  `handleTwitchEvent` vanishes. No log, no row, no broadcast, and Twitch gets a 200 so it never
  retries.
- **Evidence:** `TwitchEventSubController.php:586-587`.
- **Fix:** log it (same shape as the `Exception` branch above it). Keep returning 200 - a 5xx
  makes Twitch redeliver, and until A3 lands a redelivery is a duplicate row.
- **Proof:** unit test forcing a `TypeError` inside the handler; assert a log line. Fails today.
- **Decision:** none.

### A3. `twitch_events` has no `message_id`, so a redelivery is two rows and two alerts

- **Symptom:** Twitch retries on any non-2xx or timeout. Each retry inserts a new row, fires the
  alert again, and counts as a second success. `external_events` already dedups on
  `(service, message_id)`; the Twitch side never did.
- **Evidence:** `TwitchEventSubController.php:512-518`; migration
  `2025_08_05_161813_create_twitch_events_table.php` (no such column). The header is read at
  `:295` and `:359` for logging and signing only.
- **Fix:** nullable `message_id` column + unique index (nullable-unique is fine in Postgres, so
  existing rows are untouched); mirror the external path's `UniqueConstraintViolationException`
  -> `duplicate` handling at `ExternalWebhookController.php:167-174`. The Twitch header is
  `Twitch-Eventsub-Message-Id`.
- **Proof:** post the same notification twice; assert one row and one broadcast. Fails today.
- **Decision:** none. Migration is additive.

### A4. Queue-worker liveness is unobservable and the existing guard is dead

- **Symptom:** every user-facing broadcast (`AlertTriggered`, `ControlValueUpdated`, ...) is
  `ShouldBroadcast`, i.e. it only leaves the box when the single `queue:work` container runs the
  job. The webhook returns 200 the instant the job is enqueued. `routes/console.php:170-178`
  guards a `queue:restart` on `cache('last_job_processed_at')`, and **nothing in the repo ever
  writes that key**, so the guard has never fired.
- **Evidence:** `routes/console.php:174`; grep for the key finds only that read.
- **Fix:** a `Queue::after` / `JobProcessed` listener writing the key. One line in a provider.
  That makes the existing guard live and gives pile B its `worker.alive` wire for free.
- **Before starting:** check the prod container restart policy (`docker inspect` on the queue
  container, read-only). If Kamal already restarts a crashed worker, the remaining failure mode is
  a *backed-up* queue, not a dead one, and the wire should read oldest-job age from `jobs`, not
  a heartbeat. Report which it is before writing the fix.
- **Proof:** test that processing a job writes the key. Fails today.
- **Decision:** none for the stamp. The `queue:restart` remedy itself is Jasper's call once the
  restart-policy check is in.

### A5. Reverb client events are on

- **Symptom:** `config/reverb.php` sets no `accept_client_events_from`; Reverb defaults to
  `'all'`. Any overlay connection can whisper on `private-alerts.{id}` and every other subscriber
  receives it. Nothing uses whispers, so nothing breaks today - but "client events stay disabled"
  is a premise the uptime discussion leaned on, and it is not true.
- **Evidence:** `config/reverb.php:74-88`;
  `vendor/laravel/reverb/src/Protocols/Pusher/ClientEvent.php:32-43` (rejects anything that is
  not `'all'` or `'members'`).
- **Fix:** add `'accept_client_events_from' => 'none'` to the app definition. Prod needs a
  Reverb restart (a deploy does that).
- **Proof:** config assertion test. Fails today.
- **Decision:** none.

### A6. `ControlPanel.vue` listens on the wrong channel

- **Symptom:** `echo.channel('alerts.{id}')` is the public channel; `ControlValueUpdated`
  broadcasts on `private-alerts.{id}`. The panel never receives `.control.updated`.
- **Evidence:** `resources/js/components/ControlPanel.vue:295-296`;
  `app/Events/ControlValueUpdated.php:73`.
- **Fix:** `echo.private(...)`, plus the matching `leave` if there is one.
- **Proof:** no unit harness reaches this (no jsdom). Verify in the browser: toggle a control in
  one tab, watch the panel in another. Say so in the PR.
- **Decision:** none.

### A7. `GET /api/eventsub-health-check` is unauthenticated and mutating

- **Symptom:** an unauthenticated GET that dispatches `SetupUserEventSubSubscriptions` for every
  failed sub and every unconnected auto-connect user. Explicitly exempted from `CheckBanned`.
  Nothing in the repo calls it.
- **Evidence:** `routes/api.php:274-326`; `app/Http/Middleware/CheckBanned.php:21`.
- **Decision (Jasper):** is anything external hitting it - a cron, a monitor? The scheduler
  already runs `eventsub:monitor --fix` hourly, which does the same work. If nothing calls it,
  delete the route. If something does, it needs a secret and a POST.

---

## Pile B: measure (build, only after pile A)

These are the pieces the debrief actually needs. Each is a heading with the open question, not a
spec. Specs get written one at a time when the previous one has shipped.

### B1. Read `subscription_count` back from Reverb at send time

Reverb's events endpoint honours Pusher's `info=subscription_count` and returns per-channel
counts in the trigger response
(`vendor/laravel/reverb/src/Protocols/Pusher/Concerns/InteractsWithChannelInformation.php:50-56`,
`EventsController.php:51`). `MeteredBroadcaster` (`app/Broadcasting/MeteredBroadcaster.php:35`)
already owns the Pusher client and is the one chokepoint all 55 broadcast sites pass through.
"How many connections was this delivered to" is answerable there with no presence channel and
the overlay sending nothing.

- Open: where the number lands. It arrives in the queue worker, which does not know which
  `twitch_events` / `external_events` row it belongs to. See B2.
- Caveat to write down now: it is connections, not overlays, at the instant Reverb accepted the
  event. Proof of delivery, never proof of paint. That gap stays open on purpose.

### B2. Close the ledger row from the worker

`external_events` already has `controls_updated` / `alert_dispatched`. `twitch_events.processed`
exists and nothing sets it. The debrief needs an outcome per row written by the process that
actually broadcast it.

- Open: how the row id reaches the broadcast event. `AlertTriggered` and friends carry no
  reference to the inbound row. Threading one through is the design question of this whole
  effort and must be its own short doc before any code.
- Open: the outcome enum. Most "no broadcast" cases are legitimate (muted, no mapping, epsilon
  drop, no matching control). The enum needs a `no_target` family or the success rate is wrong on
  day one. `bot_chat_outbox` (`sent_at` / `discarded_at`, with a docblock explaining why dropped
  rows stay visible) is the in-house pattern to copy.
- Open: `channel.channel_points_custom_reward_redemption.update` was 8k of August's 15k rows,
  7.5k of them in one two-hour storm for one user (peak 448/min). It should probably never count
  against a success rate at all.

### B3. Present-tense wires on `/wiring`

`/wiring` is already present-tense by design ("a wire is a QUERY, never a record",
`app/Support/WiringCatalog.php:13-17`) and today has exactly two wires, neither on the event
path. Candidates, each reading state we already hold after pile A:

- `eventsub.subscribed` - `user_eventsub_subscriptions.status` (honest after A1)
- `integration.receiving` - `external_integrations.last_received_at`
- `worker.alive` - the A4 stamp, or oldest-job age
- `token.valid` - `users.token_expires_at`; note nothing polls validity on a schedule today
- `bot.present` - needs one new timestamp: when the last chat-stats POST arrived. The bot already
  sends one every 30-60s while live, so "enabled + live + silent for 5 min" is a real signal.
  Today `bot.in_chat` reads the toggle, not the bot.

Readiness and the debrief share a vocabulary and are different tenses of the same facts. They
are **not** one storage: wiring refuses records by design, the debrief is nothing but records.

### B4. The debrief itself

Per stream session, aggregated by time window over `created_at` (never the
`stream_session_id` FK - see CLAUDE.md). Failure reason is the product; the percentage is a
footnote. Not started until B1-B3 exist. Data tool, not dashboard.

---

## Pile C: noticed, parked

Real, but not on the path and not worth a PR alone. Bundle when something nearby is touched.

- `gps_pings` table has zero references (`2026_03_17_000001_create_gps_pings_table.php`). GPS
  flows through `external_events`.
- `eventsub:monitor --fix` is scheduled twice (`routes/console.php:156`, `:163`) with identical
  flags; the "deep check" differs only in log file, and that log file lives in an unmounted
  container filesystem.
- `private-lists.{id}.{slug}` has no `routes/channels.php` entry; only the overlay-token path
  can authorise it. Matters only if the dashboard ever subscribes to it.
- `config/reverb.php` `allowed_origins => ['*']`. Separate conversation.
- `overlay_access_logs` is 27k rows of "a browser source was open" that only an admin page reads.
  Pile B may want it; do not delete it.
