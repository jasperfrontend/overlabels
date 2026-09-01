## OL-2609-010 - fix(checkin): gate !checkin on a confidently live stream, speak the stored distance

**Shipped:** 2026-09-02
**Commit:** `git log --grep=OL-2609-010`

### Surface
- `app/Http/Controllers/Api/Internal/BotCheckinController.php` - live gate before place resolution; reply formats the stored km value
- `tests/Feature/BotCheckinTest.php` - two offline tests, live-state helper wired into `connectCheckin()`, reply asserts the stored value
- `resources/help/pages/checkin.md` - the live-only rule documented
- `resources/help/pages/bot/commands.md` - `!checkin` row notes the live-only rule

### Claims
- **C1** [code] `BotCheckinController::store()` returns `{reply: "Checkins open when the stream is live. Come back then!"}` when `StreamSessionService::isLive()` (the `isConfidentlyLive()` gate) is false, before any resolve/upsert/pipeline work - nothing is stored, incremented, or broadcast offline.
- **C2** [code] The refusal is a spoken reply, not silence, and sits after the per-chatter cooldown - a swallowed command would make the viewer retype it later into Twitch's repeated-message filter, and an ungated refusal would let offline spam draw bot replies.
- **C3** [test] "checkin is refused while the stream is offline" asserts the reply, zero `checkins`/`external_events` rows, and untouched controls; "the offline refusal sits behind the per-viewer cooldown" asserts the second attempt is silent.
- **C4** [unverified] The offline-refusal test was run against a tree with the gate condition disabled and failed; the cooldown test passes either way (the cooldown predates the gate).
- **C5** [code] The distance reply formats the stored value - `number_format(km, 1)` with trailing `.0` trimmed - so the bot speaks 57.5 where the overlay's `|distance:km` shows 57.5, replacing the zero-decimal `number_format()` that said 58.
- **C6** [test] The home-location test asserts the reply contains the pin's stored `distance_km` verbatim.

### Unchanged
- `StreamSessionService::handleEvent()` and `applyChatSummary()` - the two existing consumers of the confidently-live gate this change aligns with - are not in the diff.
- The go-live reset (OL-2609-004) still zeroes the three per-stream keys; the gate now means they can only ever move while live, so reset and gate together make the `_this_stream` label true at all times, not just at stream start.
- `PipeFormatter::distance()` (PHP) is deliberately NOT used for the reply and not in the diff: it assumes input in meters while `formatters.ts` documents input in km - a pre-existing runtime divergence (GPS-era) reported to the maintainer during this change and left for a separate decision.

### Risk
Viewers who used to pre-check-in while the channel was offline now get a refusal until go-live;
accepted behavior change, chosen over exempting checkins from the house live gate.
