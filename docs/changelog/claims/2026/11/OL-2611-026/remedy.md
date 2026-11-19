## Remedy of OL-2611-026 - fix(designer): a preview token read off a stream works nowhere else

**Remedied:** 2026-11-19
**Claim:** OL-2611-027

| Finding | Outcome | What |
|---------|---------|------|
| F1 | RECORD | OL-2611-027 C1 and C2 restate that broadcasting auth is asserted with no and the right cookie only, and the wrong-cookie case on render only |
| F2 | RECORD | OL-2611-027 C3 restates that `/api/events` and `/api/lists` are asserted with a preview token only while the cookie is sent |
| F3 | RECORD | OL-2611-027 C4 cites OL-2609-124 C6 as superseded by the 120-minute expiry at `app/Support/ProductDesigner.php:480` |
