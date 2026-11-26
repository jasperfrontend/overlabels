## OL-2611-039 - docs(claims): record what OL-2611-038 left out about counted flood events

**Shipped:** 2026-11-26
**Commit:** `git log --grep=OL-2611-039`

Remedies OL-2611-038 audit F1, F2, F3.

### Surface
- `docs/changelog/claims/2026/11/OL-2611-038/remedy.md` - new file, the remedy of the OL-2611-038 audit

### Claims
- **C1** [code] A follow or redemption that `FloodGate::offer()` answers `COUNTED` returns from `TwitchEventSubController::handleTwitchEvent()` before `TwitchEvent::create()`, so it never reaches `processStoredEvent()` and does none of the per-event work OL-2611-036's Unchanged section lists for every event in a burst (narrows OL-2611-036 Unchanged line 1, audit F1).
- **C2** [code] `FloodGate::apply()` and `FloodGate::finish()` call neither `refreshCachesForEvent()` nor `LivingTitleService::schedule()`; of the per-event work, a tick does only `EventMeter::record()`, `StreamSessionService::handleEvent()` and the `TwitchEventReceived` broadcast, each once for the tick's count (audit F1).
- **C3** [unverified] Whether skipping the follower and goal cache refresh and the living-title scheduling for counted events is intended is not recorded anywhere in the repo; it is Jasper's call (audit F1).
- **C4** [code] `FloodGate::keep()` appends through `FloodGate::withTail()`, which takes `Cache::lock("{base}:tail-lock", 5)->block(2, ...)` and, on `LockTimeoutException`, skips the write silently; a counted event can therefore be missing from the kept list, while the `:dropped` counter it was added to stays exact (corrects OL-2611-038 C5, audit F2).
- **C5** [code] `FloodTick::handle()` calls `FloodGate::forget($userId, $kind)` and returns without ticking when `User::find($userId)` is null (audit F3).
- **C6** [code] `FloodGate::forget()` deletes the flood's `last`, `n`, `dropped`, `tail`, `tick` and `summary` cache keys and writes no row (audit F3).
- **C7** [code] OL-2611-038 C13's "one summary of 200 and one broadcast" is narrower in the test than in the claim: `FloodGateTest` asserts the first `flood_counted` row has count 200 and that at least one matching `TwitchEventReceived` was dispatched, not that exactly one summary row or one broadcast exists (corrects OL-2611-038 C13).
