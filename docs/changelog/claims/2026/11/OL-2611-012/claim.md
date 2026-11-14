## OL-2611-012 - test(alerts): pin every queue hand-over, and restate what OL-2611-011 left out

**Shipped:** 2026-11-14
**Commit:** `git log --grep=OL-2611-012`

### Surface
- `resources/js/utils/alertQueue.test.ts` - "plays back-to-back alerts one after another instead of replacing them" asserts the timing of every hand-over through a `handOver()` helper
- `docs/changelog/claims/2026/11/OL-2611-011/remedy.md` - new: outcomes for the OL-2611-011 audit findings

### Claims
- **C1** [test] `alertQueue.test.ts` "plays back-to-back alerts one after another instead of replacing them" asserts, for both the a-to-b and the b-to-c hand-over, that the log is unchanged 1 ms before the 6000 ms duration ends, gains `hide` at it, is unchanged 1 ms before the 500 ms gap ends, and gains the next `show` at it. Corrects OL-2611-011 C2 (audit F1).
- **C2** [unverified] With `b`'s duration changed to 3000 ms in that test's input, the test failed; restored, it passed.
- **C3** [code] `OverlayRenderer.vue` declares no `alertSequence`, and `handleAlertTriggered()` hands a fetched source to `alertQueue.enqueue()` as a promise, so an alert whose source is still being fetched keeps its place when a newer alert arrives. This supersedes OL-2611-005 C14 (OL-2611-011 audit F2).
- **C4** [code] `PENDING_TTS_MAX_AGE_MS` in `OverlayRenderer.vue` is `300_000` (OL-2611-011 changed it from `60_000`), longer than `MAX_WAIT_MS` (`180_000`) in `alertQueue.ts`. Records OL-2611-011 audit F3.

### Unchanged
- `resources/js/utils/alertQueue.ts` is the code the C1 test exercises; it is not in this diff, so the tighter test passing is evidence about the queue as OL-2611-011 shipped it.
