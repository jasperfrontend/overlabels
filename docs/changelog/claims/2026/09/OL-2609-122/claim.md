## OL-2609-122 - fix(products): a chat designer frame whose token died asks the page for a new one instead of reloading itself forever

**Shipped:** 2026-09-22
**Commit:** `git log --grep=OL-2609-122`

`ChatDesigner::previewToken()` keeps one preview token per account and sweeps the rest when it mints
(OL-2609-109). A second browser session opening the designer therefore invalidates the first
session's frame, and that frame's auto-reload retried the dead token in its URL fragment forever;
only a hard refresh of the page recovered. Now the frame hands the 401 to the page, which mints a
fresh token through a partial reload and swaps the frame's src. The sweep itself is unchanged.

### Surface
- `resources/js/composables/useOverlayHealth.ts` - `cancelAutoReload` added to the object `useOverlayHealth()` returns
- `resources/js/components/OverlayRenderer.vue` - a `watch` on `health.status` that, in sample mode and when framed, cancels the auto-reload and posts `{ ol: 'chat-sample', expired: true }` to the parent
- `resources/js/pages/products/design.vue` - `RECOVERY_WINDOW_MS`, `recoverPreview()`; `onFrameMessage()` handles `expired`

### Claims
- **C1** [code] The watch in `OverlayRenderer.vue` returns without acting unless `status === 'auth_error'`, `props.sample` is true and `window.parent !== window`. An overlay loaded by OBS keeps the health composable's auto-reload.
- **C2** [code] When it acts, it calls `health.cancelAutoReload()` before posting, so the frame never reloads itself with the dead token.
- **C3** [code] `cancelAutoReload()` is the pre-existing function at `useOverlayHealth.ts` (clears `autoReloadTimer` and `countdownTimer`); this change only adds it to the returned object.
- **C4** [code] `recoverPreview()` calls `router.reload({ only: ['preview_url'] })`, which re-runs `ProductController::design()` and therefore `ChatDesigner::previewToken()`, and only on success sets `frameSrc`.
- **C5** [code] The src it sets is `preview_url` with `reload=<timestamp>` inserted into the query string before the fragment, because a src that differs from the current one only in its fragment does not load a new document.
- **C6** [code] `recoverPreview()` returns without reloading when called within `RECOVERY_WINDOW_MS` (15000) of its last attempt.
- **C7** [code] `onSuccess` clears `pendingKeys` and the veil backstop, so a veil that was up when the frame died does not wait on keys the dead frame will never report.
- **C8** [code] `onFrameMessage()` still accepts a message only when `event.source` is the frame's `contentWindow` and `event.data.ol === 'chat-sample'`.
- **C9** [unverified] On overlabels.test with the designer open: deleting the session's preview token row, then reloading only the frame (what its auto-reload did), produced `expired` at 394 ms, a changed src at 608 ms, `ready` from the new frame at 1554 ms and fourteen sample messages at 1821 ms, with exactly one preview token in the database afterwards. Before C5 the same run swapped the src but the frame kept its dead document.
- **C10** [unverified] Same setup, but reloading the whole designer page in Chrome while a Firefox session held the token: the page came back healthy with a new token and the Firefox token gone - the pre-existing behaviour this change leaves in place.

### Unchanged
- `ChatDesigner::previewToken()` and its sweep are not in the diff. Two designer sessions on one account still take turns invalidating each other; each now recovers at its next health check or load instead of never. Sweeping only expired tokens would end the collision itself and was not asked for.
- `useOverlayHealth.ts`'s auth-error branches, `scheduleAutoReload()` and `AUTO_RELOAD_DELAY` are not in the diff. The composable still schedules the reload; the overlay cancels it in the one case it can do better.
- `ProductController::design()` is not in the diff; the partial reload asks for a prop it already serves.

### Risk
None for OBS (C1). On the designer a dead token now costs about two seconds of banner and a
re-rendered frame, and the sample chat in the frame starts over.
