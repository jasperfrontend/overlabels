## OL-2609-037 - fix(dashboard): following a row's call to action clears it from the What's New card

**Shipped:** 2026-09-09
**Commit:** `git log --grep=OL-2609-037`

### Surface
- `resources/js/components/WhatsNewCard.vue` - new `followCta()` bound to the per-row CTA anchor with `@click`

### Claims
- **C1** [code] `followCta()` calls `router.delete(route('dashboard.whats-new.dismiss', item.id), ...)`, the same endpoint the row's X button uses; no new route or controller method is in the diff.
- **C2** [code] For an unmodified click, `followCta()` calls `event.preventDefault()` and follows `item.cta.href` with `window.location.assign` from the request's `onFinish` callback.
- **C3** [code] For a click with `ctrlKey`, `metaKey`, `shiftKey` or `altKey` set, `followCta()` sends the same delete without `preventDefault` and without navigating, so the browser handles the anchor natively.
- **C4** [code] `followCta()` returns without doing anything when `item.cta` is null; the anchor is only rendered under `v-if="item.cta"`.
- **C5** [unverified] In Chrome on `overlabels.test` on 2026-09-09, clicking "Check your wiring" on the row for update 14 produced `DELETE /dashboard/whats-new/14` (200) followed by `GET /settings/wiring` (200), set `dismissed_at` on the user's row for that update, and pressing Back returned to a dashboard whose card no longer listed the row.
- **C6** [code] `opened()` on the title `<Link>` is unchanged; it still rewrites the page's `whatsNew` prop via `router.replaceProp` and does not call the dismiss endpoint, because `UpdateController::show()` writes the record for that path.

### Unchanged
- `WhatsNewController::dismiss()` is the endpoint C1 calls; it is not in the diff. It writes `dismissed_at` via `updateOrCreate` and answers with `back()`, which is what redraws the dashboard for the modified-click case in C3.
- `UpdateController::show()` and the `MarkWhatsNewVisited` removal in OL-2609-036 are not revisited: a visit to a CTA target page still records nothing on its own; only the click on the card does.

### Risk
An unmodified CTA click now waits one round trip before the page changes. A failed dismiss request still navigates, since `onFinish` runs on error too, and the row then comes back on the next dashboard load.
