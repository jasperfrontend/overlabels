## Remedy of OL-2611-011 - test(alerts): pin every queue hand-over, and restate what OL-2611-011 left out

**Remedied:** 2026-11-14
**Claim:** OL-2611-012

| Finding | Outcome | What |
|---------|---------|------|
| F1 | FIXED | `alertQueue.test.ts` "plays back-to-back alerts one after another instead of replacing them" now asserts the exact timing of both hand-overs (a to b, b to c): no hide 1 ms before the duration ends, hide at the duration, no show 1 ms before the gap ends, next show at the gap. OL-2611-012 C1 |
| F2 | RECORD | OL-2611-012 C3 restates that OL-2611-011 removed `alertSequence` and so supersedes OL-2611-005 C14: a fetched alert now keeps its place in the queue instead of being dropped when a newer alert fires during the fetch |
| F3 | RECORD | OL-2611-012 C4 records that `PENDING_TTS_MAX_AGE_MS` went from 60000 to 300000, longer than the queue's `MAX_WAIT_MS` of 180000 |
