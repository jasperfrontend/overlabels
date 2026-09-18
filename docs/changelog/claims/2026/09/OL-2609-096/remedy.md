## Remedy of OL-2609-096 - fix(privacy): strip supporter contact and postal details from stored integration payloads, and document the whole data tree

**Remedied:** 2026-09-18
**Claim:** OL-2609-102

| Finding | Outcome | What |
|---------|---------|------|
| F1 | RECORD | OL-2609-102 C1 names `AdminTwitchEventController::showExternal()` as the reader C6 missed, C2 records that `index()` projects with `->through()`, and C3 restates why no denied key is read by name |
| F2 | RECORD | OL-2609-102 C4 and C5 state that the `your-data.md` BMAC "Removed on arrival" row is true of the tree at HEAD, pinned by `BMACWebhookTest` |
