## Remedy of OL-2609-007 - feat(globe): the [[[checkin_globe]]] 3D globe renderer

**Remedied:** 2026-09-24
**Claim:** OL-2609-134

| Finding | Outcome | What |
|---------|---------|------|
| F1 | RECORD | OL-2609-134 C1 restates that `globeTag.test.ts` pins only row `MASK_ROWS - 1` as empty |
| F2 | RECORD | OL-2609-134 C2 keeps the runtime base64 decode as `[code]`; C3 splits the `geo_places` provenance out as `[unverified]` |
| F3 | RECORD | OL-2609-134 C4 lists the seven CSS-read properties (shell pair via OL-2609-068); C5 records the inline far-side label `opacity: 0` in `checkinGlobe.ts`, left in place pending a decision |
| F4 | SKIPPED | choosing between a `TAG_CATALOG` entry (changes `/tags` and the drift guard) and a `CLAUDE.md` exception is a decision only Jasper can make |
