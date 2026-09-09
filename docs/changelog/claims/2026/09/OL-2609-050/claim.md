## OL-2609-050 - fix(products): the URL hint can no longer switch product mode on after a flow has ended

**Shipped:** 2026-09-09
**Commit:** `git log --grep=OL-2609-050`

### Surface
- `resources/js/composables/useUiMode.ts` - `parseUiMode()` returns `lastMile` and `product`; `resolveUiMode()` sets `mode` from the flow only; `useUiMode()` exposes `lastMile`
- `resources/js/composables/useUiMode.test.ts` - rewritten for the new shape, including the finished-flow case
- `resources/js/pages/templates/show.vue` - the OBS tab opens first and the callout renders when the flow is on OR the URL carries the hint; the green tab stays flow-only

### Claims
- **C1** [code] `parseUiMode()` returns `{ lastMile, product }` where `lastMile` is true only when the query's `state` equals `product`, and `product` only then and only for a value matching `^[a-z][a-z0-9_]{0,49}$`.
- **C2** [code] `resolveUiMode()` returns `mode: 'product'` only when `productSetup` is non-null; with a null or undefined `productSetup` it returns `mode: null` whatever the URL says, carrying `lastMile` and the URL's `product` through.
- **C3** [code] `AppLayout` still writes `data-mode` from `mode`, so with no flow active the frame, the setup banner, the `product:` variant and `useProductTarget()` are all off on a page whose URL carries `?state=product`.
- **C4** [code] `templates/show.vue` initialises `mainTab` to `obs` and renders `ProductLastStep` when `mode === 'product'` or `lastMile` is true; the tab's `product:` green classes are unchanged and so apply only during a flow.
- **C5** [test] `useUiMode.test.ts` asserts C2 with `/templates/337?product=follower_bowling&state=product` and no flow yielding `mode: null, lastMile: true, product: 'follower_bowling'`, and asserts C1 for the hint, a missing parameter, an unknown value and a non-slug product.
- **C6** [unverified] Reported by the author on `overlabels.test` on 2026-09-09: after a finished Follower Bowling flow, pressing the green band's OBS button landed on `/templates/337?product=follower_bowling&state=product` with the frame, banner and green tab back on. Before this change `resolveUiMode()` returned `mode: 'product'` from the URL alone, which is that behaviour.

### Unchanged
- `ProductSetup`, the banner and the frame are not in the diff; they read `mode`, which now has one source.
- `products/show.vue` is not in the diff; the green band's OBS button still carries the hint, which is what opens the tab and shows the callout after the flow has ended.

### Risk
None beyond the fix: a visit with the hint and no flow shows the OBS tab and callout without the frame or green, which is the intended "flow done, here is the last mile" reading. Corrects OL-2609-048's Risk line, which assumed the URL and the flow agreed.
