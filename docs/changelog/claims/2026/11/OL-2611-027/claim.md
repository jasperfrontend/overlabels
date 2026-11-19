## OL-2611-027 - docs(claims): restate the preview-token test coverage of OL-2611-026, and cite the expiry it superseded

Remedies OL-2611-026 audit F1, F2, F3.

**Shipped:** 2026-11-19
**Commit:** `git log --grep=OL-2611-027`

### Surface
- `docs/changelog/claims/2026/11/OL-2611-026/remedy.md` - new: the remedy of the OL-2611-026 audit

### Claims
- **C1** [test] `DesignerPreviewTokenTest` "signs the live channel and serves alert sources for a preview token only with its cookie" posts a preview token to `/api/overlay/broadcasting/auth` with no cookie (401) and with the right `ol_preview` cookie (200) only; no request in the file sends a wrong cookie to that route (corrects OL-2611-026 C10, audit F1).
- **C2** [test] The wrong-cookie 401 in `DesignerPreviewTokenTest` is asserted on `/api/overlay/render` only, in "renders the overlay for a preview token only in the browser that holds its cookie" (corrects OL-2611-026 C10, audit F1).
- **C3** [test] `DesignerPreviewTokenTest` "never reads the events feed or a List with a preview token, cookie or not" sends a preview token to `/api/events` and `/api/lists/{key}` only WITH the right `ol_preview` cookie, and asserts 401 on both; no request in the file sends a preview token to either route without a cookie, despite the test's name (corrects OL-2611-026 C10, audit F2).
- **C4** [code] `ProductDesigner::previewToken()` mints a preview token with `expires_at => now()->addMinutes(self::TOKEN_LIFETIME_MINUTES)` (120), and `renewPreviewToken()` moves it to 120 minutes from the renewal; this supersedes OL-2609-124 C6's "Preview tokens still expire a day after minting (`expires_at => now()->addDay()`)" (audit F3).
