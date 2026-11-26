# EventSub Front Desk on Cloudflare - Design Strawman (#343)

> **Status:** LIVE in queue mode since 2026-11-22 22:49 UTC (2c4b8a5c). Built the same day as the
> design, with the forks of section 9 decided by Jasper. Measured on prod right after: 13,800
> unsigned test notifications at 460/s through the edge, every one answered 200 (p50 92 ms, max
> 1.5 s), everyone else's `/up` at p50 42 ms throughout, the box at load 0.7. The queue then
> drained into Laravel at ~1 batch of 100 per second (one batch at a time, ~16 ms each in Laravel),
> i.e. ~100 notifications/s - so the events worker (~16 follows/s in a burst after the avatar
> changes) is the bottleneck now, which is section 8's flood summarizer.

---

## 1. TL;DR

Twitch wants every webhook answered within "a few seconds" or it revokes the subscription. Our
webhook costs ~12 ms of CPU, ~85% of it Laravel booting, so the 1-vCPU box tops out around 80/s.
A raid that brings 460 follows a second would revoke the raided streamer's subscriptions.

The front desk is a Cloudflare Worker on the one path `overlabels.com/api/twitch/webhook`. It
checks that a request looks like Twitch, drops it in a Cloudflare Queue and answers 200 in
milliseconds, at any rate. A consumer hands Laravel the queued notifications in batches of up to
100, so Laravel boots once per hundred instead of once per event, at a pace Laravel chooses.

**The edge stays dumb.** No secrets, no user data, no decisions. Laravel verifies every signature
when the batch arrives, exactly as it does today. Forged junk can get into the queue and dies at
Laravel's check.

Part 1, independent of all of this: **batch the avatar lookups.** Today every event costs one Helix
call for the acting user's avatar. One `GET helix/users` takes 100 ids, so a flood can cost one call
per hundred events instead of one per event.

## 2. Where we are (measured 2026-11-22)

| | Number |
|---|---|
| Webhook intake ceiling, 1 vCPU | ~80/s (12 ms CPU each, ~11 ms of it Laravel boot) |
| Past the ceiling at 100/s | p95 4.6 s, every other user's `/up` ~3 s |
| Events worker, follow in a burst (after c0b045f1..5cf22f44) | ~205-290 ms, ~all of it one Helix avatar call |
| Events worker ceiling | ~5 follows/s (single worker, by design) |
| Real volume | ~11,500 events in November so far; peak ~116/min for one account (test bursts) |

## 3. The shape

```
Twitch ──POST──> Cloudflare edge: Worker route overlabels.com/api/twitch/webhook
                   │
                   ├─ webhook_callback_verification ─┐
                   ├─ revocation ────────────────────┼──> passthrough to origin (unchanged code)
                   ├─ queue send failed ─────────────┘
                   │
                   └─ notification ──> Queue "twitch-eventsub" ──> 200 to Twitch
                                            │
                            consumer (same Worker), batches of <=100
                                            │
                              POST overlabels.com/api/twitch/webhook/batch
                                            │
                        Laravel: verify each, scrub, store, dispatch ProcessTwitchEvent
                                            │
                           events worker, unchanged (plus part 1's avatar batching)
```

Everything downstream of "store the row and dispatch the job" is untouched: dedup, scrubbing,
alerts, counters, the delivery ledger, the alert queue on the overlay.

## 4. The Worker (fetch handler)

- **Route:** exactly `overlabels.com/api/twitch/webhook`. No wildcard, so `/api/twitch/webhook/batch`
  and everything else go straight to the origin. Existing subscriptions keep their callback URL; no
  resubscribing, Twitch never notices.
- **`MODE` variable:** `passthrough` forwards every request to the origin untouched (`fetch(request)`)
  and returns the origin's answer. `queue` does the below. We ship in `passthrough` first.
- **Passed through to the origin, always:** `webhook_callback_verification` (Laravel marks the
  subscription enabled and echoes the challenge) and `revocation` (Laravel records the status). Both
  are rare and both write to our database, so they stay Laravel's.
- **A notification is queued when it:**
  - is a POST with `Twitch-Eventsub-Message-Id`, `-Timestamp`, `-Signature` and `-Type: notification`
  - has a body that is valid JSON with a `subscription.type`, at most 64 KB (one queue op; a bigger
    one is passed through to Laravel instead)

  **No age check at the edge** (dropped while building, 2026-11-22). A Twitch redelivery can carry an
  old timestamp, and refusing it would count as a failed delivery against the subscription - the
  very thing this exists to prevent. Age is Laravel's call: old notifications are stored without an
  alert (section 6).

  It goes on the queue as `{id, timestamp, signature, raw body}` - the raw body string, because the
  HMAC is computed over the exact bytes. Answer: `200 OK`.
- **Fails those checks:** `400`. Only something that is not Twitch fails them.
- **`queue.send()` throws** (5,000 msg/s per queue, or the 25 GB backlog cap): fall back to
  passthrough for that request, so the worst case is today's behaviour, never a dropped event.
- **`queue.send()` is slow** (added 2026-11-26, #346): after ~25 quiet minutes the queue takes up
  to ~20 s per write for the first ~19 s of a burst. Measured with the `X-Front-Desk-Send-Ms`
  header: the write was the whole wait, everything else ~30 ms. The answer now waits at most
  `MAX_SEND_WAIT_MS` (2 s); a write still running then finishes in `waitUntil`, and if it fails
  after all it goes to Laravel in the background. The one new loss case: a late failed write AND
  Laravel unreachable at that moment - Twitch got its 200, so it does not retry.

## 5. The queue and the consumer

- **Queue `twitch-eventsub`:** retention 4 days (default; max 14).
- **Consumer = the same Worker's `queue()` handler**, push-based (no idle polling, nothing billed
  while quiet):
  - `max_batch_size` 100, `max_batch_timeout` 1 s (fork 9.2)
  - `max_concurrency` 10 (was 1, fork 9.4, until 2026-11-24): at 1, Laravel needed 0.33 s per full
    batch but batches started 2.8 s apart, so intake ran at 35-62/s and was the line every
    streamer queued in during a raid (#343). At 10, batches no longer arrive strictly in order
- **Per batch:** sort by Twitch timestamp, `POST /api/twitch/webhook/batch` with a shared-secret
  header, read back a per-message result. `stored` / `duplicate` / `invalid` are acked; `error` is
  retried. A non-2xx answer or a network failure retries the whole batch.
- **Retries:** exponential backoff capped at 15 minutes, `max_retries` 100, so roughly a day of a dead
  Linode is absorbed before anything gives up. After that a message goes to the dead-letter queue
  `twitch-eventsub-dlq` (retention 14 days) for a manual replay.

## 6. Laravel's side

- **`POST /api/twitch/webhook/batch`**, authenticated by `hash_equals` on the shared secret
  (`FRONT_DESK_SECRET`, a Kamal secret - three places, per CLAUDE.md). No session, CSRF or
  throttle middleware, same as the webhook.
- The single webhook and the batch endpoint share one `storeNotification(id, timestamp, signature,
  body)`: verify the HMAC (per-user secret, global fallback - today's `verifyTwitchSignature()`
  reworked to take the parts instead of the Request), scrub, insert on the unique `message_id`,
  dispatch `ProcessTwitchEvent`. Returns `stored` / `duplicate` / `invalid`.
- Per batch: the broadcaster's user row is looked up once, not per message.
- The direct webhook stays live and unchanged: rollback, Twitch CLI testing, and the passthrough
  fallback all use it.

**Late events.** After an outage, the queue delivers events that are minutes or hours old. Today
they would each fire an alert. My pick (fork 9.1): store and count them, skip the alert when the
Twitch timestamp is more than 5 minutes old, and record that outcome in the delivery ledger.

## 7. Part 1: batch the avatar lookups (buildable now, needs no Cloudflare)

**Cloudflare cannot make these cheaper.** Helix limits are per app and per token, not per IP, so a
Worker calling Helix spends the same budget, and it would need a Twitch token at the edge. Avatar
images themselves are already served by Twitch's CDN straight to the overlay. What saves calls is
asking for 100 users at once.

**First: skip it when nothing shows it** (Jasper's sharpening). Only two things read an event
avatar: an alert's `event.*_avatar` tags and a static overlay's bare `*_avatar` tags (which the
event payload overwrites). Follower Bowling does not - its pins read `f.user_profile_image_url` from
the cached follower list. Lists, recents and the events feed store no avatar. So
`OverlayTemplate::ownerShowsEventAvatars()` checks the streamer's own templates (html, css, head,
TTS and chat lines) for such a tag, `channel_avatar` excluded; cached, and forgotten whenever one of
their templates is saved or deleted. No tag, no lookup at all.

**Then, when it is needed:** `ProcessTwitchEvent` handles rows in id order, so the rows after the
current one are what the worker does next. When the current event's user ids are not cached:

1. Collect the user trigrams of the next up-to-99 stored rows for the same broadcaster (id greater
   than this one). They are stored scrubbed, so a `!forgetme` viewer or an anonymous cheerer is
   never looked up - same guarantee as today.
2. Drop the ids already in the avatar cache.
3. One `GET helix/users` for up to 100 ids, with the same token as today (the streamer's, else the
   app token). Cache each avatar for 60 s, as today.

The next 99 jobs then find their avatar cached and make no Helix call. A 55,000-follow raid costs
~550 avatar calls instead of 55,000. It works with or without the front desk, and with the front
desk the rows arrive 100 at a time, which is exactly what it batches.

**Expected** (not measured): a follow in a burst drops from ~210 ms to tens of ms, so the events
worker goes from ~5 to somewhere around 20-50 follows a second.

## 8. What this does NOT solve

- **55,000 follows are still 55,000 jobs and 55,000 alerts.** At ~20-50/s that is 20-45 minutes of
  backlog during which every other streamer's alerts wait behind the raid on the single worker. The
  **flood summarizer** (collapse a burst into one "+N followers" moment) and **per-account lanes** are
  still the real fix for the backlog. Neither is designed here.
- **TTS and chat lines per follow** (found 2026-11-22): a follow alert with a TTS line queues one paid
  ElevenLabs synthesis per follow; a chat line adds one bot outbox row per follow. Needs a per-burst
  cap. Not designed here.
- Twitch's own behaviour under a real raid (does it even send 460/s?) stays unknown until one happens.

## 9. Forks

**9.1 Late events after an outage. DECIDED: store and count, no alert, if older than 5 minutes.**
An alert for a follow from two hours ago is noise. Checked 2026-11-22: nothing does this today. The
overlay's alert queue drops an alert that waited over 3 minutes *in the overlay*
(`alertQueue.ts`, counted from arrival), so an old event that arrives fresh would still play, and
the server would still synthesise its TTS and queue its chat line.

**9.2 Batch wait. DECIDED: `max_batch_timeout` 1 s,** then measure. Today an alert already waits up
to 1 s for the events worker's poll and up to 3 s for the default worker's (`--sleep=1` /
`--sleep=3`). If it is noticeable, 0 is one config line.

**9.3 Deploying the Worker. DECIDED: GitHub Action** on changes under `cloudflare/**`,
`wrangler deploy` with a `CLOUDFLARE_API_TOKEN` secret. The code lives in this repo under
`cloudflare/eventsub-front-desk/`.

**9.4 Consumer concurrency. DECIDED: 1.** Keeps delivery order (`GiftSubAbsorber` needs a gift
before its recipients) and keeps Linode's intake gentle. Revisit with per-account lanes.

**9.5 Privacy policy. DECIDED: add the line,** in the same commit that turns `MODE=queue` on, so the
policy never describes something that is not running. Event payloads then sit at rest on
Cloudflare for up to 4 days (14 in the DLQ), outside a guaranteed EU location; Jasper is fine with
that for Twitch payloads.

## 10. Build order

1. **Avatar batching** (part 1). Laravel only, test-first, ships on its own.
2. **Batch endpoint + shared `storeNotification()`.** Laravel only; the single webhook keeps working
   identically and is pinned by the existing webhook tests.
3. **Worker in `passthrough`** on the route. Zero behaviour change; proves the route, Twitch's
   traffic and the verification handshake all survive the edge.
4. **`MODE=queue`.** Test with a Twitch CLI burst, then the load script at 460/s against the edge.
   That test is finally safe to run on prod, because the edge absorbs it.
5. Watch a week of real traffic (queue backlog, DLQ, alert latency) before calling it done.

## 11. Cost

Workers Paid ($5/month, already bought 2026-11-22) covers it. November-sized volume is ~12k
notifications: ~12k Worker requests, ~36k queue operations (3 per message), ~120 consumer
invocations. A 55,000-follow raid adds ~165k queue operations. All inside the included allowance.
