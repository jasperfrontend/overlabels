# B4: the debrief

Sketch for B4 of `event-delivery-heal-2026-08.md`, written 2026-08-27 after B1-B3 and A10 shipped.
Nothing built. This is the shape and the sentences, for a yes or a redirect before code.

## Where it lives

**A sixth tab on `/dashboard/stream-sessions`, called `Delivery`.** Not a new page.

That page already has the two things the debrief needs and would otherwise have to reinvent:
the session selector rail (pick a stream, read it) and the per-session time windows anchored on
`stream.online` / `stream.offline` (`buildWindowsCte()`), which are also the only correct way to
group events by session - the `stream_session_id` FK is near-empty by design. Its tabs are
`Overview`, `Twitch`, `Income`, `Engagement`, `Raw`; `Delivery` is one more `v-else-if` panel fed
by one more aggregate loader joined to the same windows CTE. One dumb thing, added to a page that
exists. "Health" stays reserved for the uptime idea; this is per-stream and belongs with streams.

## What it computes, per session window

One query over `twitch_events UNION ALL external_events` joined to the windows CTE, like the
other loaders:

| figure | from |
|---|---|
| `scored` | rows whose `outcome` is in the scored family (`delivered`, `no_listener`, `failed`, `token_invalid`, `render_failed`) |
| `by_outcome` | count per outcome, scored and `no_target` alike |
| `latency_p50_ms`, `latency_p95_ms` | `delivered_at - created_at` over `delivered` rows, `percentile_cont` |
| `failures` | the individual non-`delivered` scored rows: time, event type, outcome. Capped at 20, newest first |

The success rate is `delivered / scored`. **`no_target` rows are never in the denominator** - an
event with no alert set up did not fail to reach the screen. They are reported underneath as
context, once, in one line.

A session with `scored = 0` shows **nothing** - no tile, no "100%", no "0%". Every stream before
2026-08-26 22:28 UTC has null outcomes on every row and must read as "before the ledger", not as
a perfect or a failed night. Same rule as the tag reference: never show a fabricated value as real.

## The sentences

The percentage is a footnote; the failure reason is the product. The panel is prose first, one
sentence per fact, each only when true:

- **`23 of 24 alerts reached your overlay.`** The headline. Singular/plural handled; when all did,
  `All 24 alerts reached your overlay.`
- **`1 was sent while no overlay was open (21:04).`** `no_listener`, with the time of the first one.
  Not the app's fault, and the most useful line on the page.
- **`Your Twitch login expired on 14 June. 12 alerts could not be built.`** `token_invalid`, with
  `token_expires_at` when it is in the past. This is TenzinNiznet's whole story in one line.
- **`1 could not be built.`** `render_failed`. The log has the message; the page does not
  pretend to.
- **`1 failed on the way to your overlay.`** `failed`. The queue gave up after three tries.
- **`Alerts reached the overlay in 0.8 s (typical), 2.1 s (slowest).`** Only when there were
  delivered rows. Formatter: existing `duration` pipe conventions, no new one.
- **`87 events had no alert set up.`** The `no_target` context line, muted, last. Optionally
  broken down: `(muted: 3, chat only: 12)` - only the non-zero ones.

Then the `failures` list under a `Failures` heading: time, event type, outcome word. No colour
per outcome - the word is the information. The existing provider icon for the source.

## What it deliberately does not do

- **No live view.** Nobody watches a status page mid-stream; this is read after.
- **No chart.** Numbers with `tabular-nums` and sentences. A sparkline of alerts-per-minute is a
  decoration until someone asks for it.
- **No proof of paint.** "Reached your overlay" means Reverb delivered to N connections. The
  copy says "reached your overlay", never "was shown".
- **No per-alert-template breakdown** in the first cut. Which template failed is in the
  failures list by event type; grouping by template is a second pass if it earns it.
- **No backfill.** The tab is honest about the ledger's start date by showing nothing before it.

## Cost

One `DB::select` per page load, with the same inline `VALUES` CTE the other seven use, bounded by
`SESSION_LIMIT = 50`. The events tables are indexed on `user_id`; a window filter on `created_at`
over 50 sessions is what `loadHeadlineAggregates` already does.

## Tests

`StreamSessionDeliveryTest`: rate counts only scored rows; `no_target` rows are context, never
denominator; a session with no scored rows produces no `delivery` key; latency percentiles from
`delivered_at - created_at`; the failures list is capped and newest-first; events outside the
window do not leak in; another user's rows never do. Page test: the tab renders with the
sentences for a session that has one of each outcome.

## Decisions before code

1. **Tab name.** `Delivery` (what happened to alerts) or `Alerts` (matches the wiring circuit).
   I lean `Delivery`: the Twitch tab already counts alerts as events.
2. **The failures cap.** 20 newest, or all with the list collapsed past 20.
3. **A second, smaller accretion:** an outcome word on each row of `/dashboard/events` (the
   feed). One column, no new component. It makes the same information findable per event
   rather than per stream. Yes now, yes later, or no.
