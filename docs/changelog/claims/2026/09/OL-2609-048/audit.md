## Audit of OL-2609-048 - feat(products): product mode is on for the whole app while a setup flow is active, with a glowing frame

**Audited:** 2026-09-25
**Commit:** 4167f94f502e944a11a8dd922bbe45a31b5825f3
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/composables/useUiMode.ts:68-74 @4167f94` - `if (setup)` returns `{ mode: 'product', product: parsed.product ?? setup.slug, ready: setup.ready }`, else `{ ...parsed, ready: false }`. @HEAD `useUiMode.ts:107-119` the no-flow branch returns `mode: null` whatever the URL says (OL-2609-050 C2) |
| C2 | CONFIRMED | `useUiMode.ts:78 @4167f94` - `resolveUiMode(parseUiMode(page.url), page.props.productSetup)`; `app/Http/Middleware/HandleInertiaRequests.php:127-134 @4167f94` shares `productSetup` on every request while `ProductSetup::activeSlug()` is set; `useUiMode({ apply: true })` only at `resources/js/layouts/AppLayout.vue:51 @4167f94` (git grep). @HEAD a `?state=product` visit outside a flow no longer sets the mode (OL-2609-050 C3) |
| C3 | CONFIRMED | `resources/js/components/ProductFlowFrame.vue:14-19 @4167f94` - `v-if="mode === 'product'"`, `pointer-events-none fixed inset-0 z-40 border-[10px]`, `aria-hidden="true"`, `border-green-500` when `ready` else `border-fuchsia-500`; `:23-27` fuchsia inset box-shadow and `animation: product-flow-glow 2.4s`; `:30-34` green shadow, `animation: none`; `:37-45` opacity 0.85 to 1; `:47-49` `animation: none` under `prefers-reduced-motion: reduce`. @HEAD a bottom-left label span was added (OL-2609-049 C5) |
| C4 | CONFIRMED | `resources/js/layouts/app/AppSidebarLayout.vue:25 @4167f94` - `<ProductFlowFrame />` first child of `<AppShell>`, the only mount (git grep); `AppLayout.vue:2 @4167f94` imports that layout; `resources/js/pages/products/index.vue` and `products/show.vue @4167f94` import neither layout (comments at `index.vue:32`, `show.vue:63,126` say they render outside AppLayout). @HEAD `resources/js/layouts/ProductsLayout.vue:5,101` wraps `AppLayout` (49eca947, no claim ID), so product pages now carry the frame - see F1 |
| C5 | CONFIRMED | `app/Support/ProductSetup.php:52-77 @4167f94` - `banner()` rebuilds `steps()` from `WiringReport::build()` per call, `ready` = `$missing === []`; the prop is a per-request closure (`HandleInertiaRequests.php:127 @4167f94`); no `localStorage`/`sessionStorage` in `useUiMode.ts`, `ProductFlowFrame.vue` or `ProductSetupBanner.vue @4167f94`; the frame reads only `mode`/`ready` from `useUiMode()` (`ProductFlowFrame.vue:10 @4167f94`) |
| C6 | CONFIRMED | `resources/js/composables/useUiMode.test.ts:5-33 @4167f94` - active flow without URL slug (`/dashboard`, `/settings/integrations`), with URL slug (`follower_bowling` wins), URL mode without slug (flow slug fills in), ready flow (`ready: true`), no flow with query (`null`) and without (`undefined`). `npx vitest run resources/js/composables/useUiMode.test.ts` in a detached worktree at 4167f94: 8/8 passed. Same file @HEAD: 15/15 passed (rewritten by OL-2609-049/050) |
| C7 | CONFIRMED | Neither file in `git show --stat 4167f94`; `resources/js/pages/templates/show.vue:99 @4167f94` takes `product: uiProduct` from `useUiMode()` and `:410` passes it as `ProductLastStep`'s `product` prop; `ProductLastStep.vue:8,18-19 @4167f94` renders the back link from that prop. @HEAD `show.vue:109,411` also renders on `lastMile` (OL-2609-050 C4) |

### Surface
Complete.

### Findings
- **F1** changed without a claim - C4's "on none of the product pages" is false @HEAD: commit 49eca947 (2026-09-24, no `Changelog:` trailer, `.vue`-only so the path rule did not require a claim) made `resources/js/layouts/ProductsLayout.vue:101 @HEAD` wrap `AppLayout`, which mounts `ProductFlowFrame` via `AppSidebarLayout.vue:36 @HEAD`, so `/products` and a product page now show the frame during a flow; record this in a new claim that cites OL-2609-048 C4.
- **F2** contradicts the record - OL-2609-045's Risk line reads "The mode drops on any navigation whose URL lacks the query, which is the intended lifetime."; this change makes the mode persist across every navigation for the life of a flow (`useUiMode.ts:78 @4167f94`) and cites OL-2609-045 only for C2 (the writer), not for the lifetime it reverses; the shipped docblock at `useUiMode.ts:5-9 @4167f94` still says "Navigating anywhere without it drops it." A new claim should cite the reversal inline (the docblock was rewritten @HEAD by OL-2609-050).
- **F3** scope - by widening `mode` to the whole flow, the diff makes `resources/js/pages/templates/show.vue:100 @4167f94` (`mainTab` = `'obs'` when `uiMode === 'product'`) open the Add to OBS tab first on every template's page during a flow; no claim, Unchanged line or the Risk section states this (Unchanged line 2 and Risk mention only the tab's green styling and the callout), so it should be recorded.

### Notes
- C1's no-flow branch and C2's "single page for a `?state=product` visit" half were superseded by OL-2609-050 (C2, C3), which cites only OL-2609-048's Risk line.
- C3's frame gained a label in OL-2609-049 C5; that claim discloses it without citing OL-2609-048.
- C6 was run at the shipped revision in a temporary detached worktree (removed afterwards); nothing in `/home/user/repo` was modified besides this file.
