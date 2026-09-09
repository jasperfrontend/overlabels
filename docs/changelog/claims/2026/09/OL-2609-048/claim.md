## OL-2609-048 - feat(products): product mode is on for the whole app while a setup flow is active, with a glowing frame

**Shipped:** 2026-09-09
**Commit:** `git log --grep=OL-2609-048`

### Surface
- `resources/js/composables/useUiMode.ts` - new pure `resolveUiMode()`; `useUiMode()` reads the shared `productSetup` prop and returns `ready` as well
- `resources/js/composables/useUiMode.test.ts` - 3 new tests for `resolveUiMode()`
- `resources/js/components/ProductFlowFrame.vue` - new: the fixed 10px frame, fuchsia while steps remain, green when ready
- `resources/js/layouts/app/AppSidebarLayout.vue` - mounts `ProductFlowFrame` inside `AppShell`

### Claims
- **C1** [code] `resolveUiMode()` returns `mode: 'product'` whenever `productSetup` is non-null, with `product` = the URL's slug when present, else `productSetup.slug`, and `ready` = `productSetup.ready`; with no `productSetup` it returns the parsed URL mode with `ready` false.
- **C2** [code] `useUiMode()` resolves from `page.url` and `page.props.productSetup`, so `data-mode="product"` is on the document root on every app page for the whole life of a setup flow, and on a single page for a `?state=product` visit outside one; the writer stays `AppLayout` alone (OL-2609-045 C2).
- **C3** [code] `ProductFlowFrame.vue` renders a `position: fixed; inset: 0` element with `pointer-events: none`, `aria-hidden`, a 10px border and an inset glow, only when the mode is `product`; fuchsia with a 2.4 s opacity pulse while `ready` is false, green and still when true; the pulse is off under `prefers-reduced-motion`.
- **C4** [code] The frame is mounted once, in `AppSidebarLayout` inside `AppShell`, so it appears on every page under `AppLayout` and on none of the product pages, which render outside it.
- **C5** [code] No progress is stored on the client: the checklist behind `productSetup` is recomputed by `ProductSetup::banner()` on each page load (OL-2609-042 C3), and the frame turns green from that, never from a click.
- **C6** [test] `useUiMode.test.ts` asserts C1 for an active flow with and without a URL slug, for a ready flow, and for no flow with and without the URL query.
- **C7** [code] `templates/show.vue` and `ProductLastStep.vue` are not in the diff; they now receive the flow's slug through `useUiMode().product` when the URL names none, so the callout's way back works on any arrival while a flow is active.

### Unchanged
- `ProductSetup`, `ProductSetupBanner.vue` and the shared prop are not in the diff; the frame reads the same fact the banner already reads.
- The `product:` Tailwind variant and the green Add to OBS tab are not in the diff; they now light on every visit to the overlay page during a flow, by C2.

### Risk
While a flow is active every `product:`-styled element in the app is live, not only the one the green band linked to. Today that is the Add to OBS tab and the last-step callout. The frame covers the viewport edge on every app page, including over dialogs below `z-40`.
