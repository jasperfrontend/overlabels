## Audit of OL-2609-050 - fix(products): the URL hint can no longer switch product mode on after a flow has ended

**Audited:** 2026-09-25
**Commit:** 3cd793ac0fb9b62280caac346a1bb4bd3e6b0940
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/composables/useUiMode.ts:36 @3cd793a` - `lastMile = params.get('state') === 'product'`; `:41 @3cd793a` - `product: lastMile && product && SLUG.test(product) ? product : null`, `SLUG` at `:30 @3cd793a` is `/^[a-z][a-z0-9_]{0,49}$/`. @HEAD `SLUG` (`:32`) is `/^[a-z][a-z0-9_-]{0,49}$/` (OL-2609-114 C14) |
| C2 | CONFIRMED | `useUiMode.ts:76-88 @3cd793a` - `mode: 'product'` only in the `if (setup)` branch; the null/undefined branch returns `mode: null` with `parsed.lastMile` and `parsed.product`. Same at `useUiMode.ts:107-118 @HEAD` |
| C3 | CONFIRMED | `resources/js/layouts/AppLayout.vue:51 @3cd793a` calls `useUiMode({ apply: true })`, which runs `applyUiMode(mode.value)` (`useUiMode.ts:100-101 @3cd793a`); frame gated on `mode === 'product'` (`ProductFlowFrame.vue:15 @3cd793a`); `product:` variant keyed on `[data-mode='product']` (`resources/css/app.css:14 @3cd793a`); `useProductTarget()` returns `mode.value === 'product' && ...` (`useUiMode.ts:116 @3cd793a`); banner gated on `v-if="setup"` = `page.props.productSetup` (`ProductSetupBanner.vue:14,31 @3cd793a`), so off with no flow. @HEAD `useProductTarget()` is gone, replaced by `useProductFocus()` (OL-2609-072 C27) |
| C4 | CONFIRMED | `resources/js/pages/templates/show.vue:101-102 @3cd793a` - `obsFirst = uiMode.value === 'product' \|\| uiLastMile.value`, `mainTab` = `'obs'` when true; `:412 @3cd793a` - `ProductLastStep v-if="uiMode === 'product' \|\| uiLastMile"`; tab classes at `:326 @3cd793a` are `product:`-prefixed and not in the diff. @HEAD `obsFirst` is removed and the tab is addressable (OL-2609-072 C25); the green classes now sit at `show.vue:98 @HEAD` |
| C5 | CONFIRMED | `useUiMode.test.ts:22-31 @3cd793a` asserts `/templates/337?product=follower_bowling&state=product` + `null` -> `{ mode: null, lastMile: true, product: 'follower_bowling', ready: false, target: null }`; `:43-60 @3cd793a` cover the hint, missing parameter, unknown value (`state=party`), product without state, and non-slug product. Shipped revision run with Vitest from the scratchpad: 8 passed. `npm test -- resources/js/composables/useUiMode.test.ts` @HEAD: 15 passed; fixture is now `follower-bowling` (OL-2609-114) |
| C6 | UNVERIFIABLE | tagged [unverified]; compound, see F1. First half (observation on `overlabels.test`) UNVERIFIABLE by contract. Second half is checkable and true: `useUiMode.ts:77 @3cd793a^` - `return { ...parsed, ready: false, target: null }` with `parsed.mode` set from the URL at `:31 @3cd793a^` |

### Surface
Complete.

### Findings
- **F1** mistagged, checkable as [code] - C6's second sentence ("Before this change `resolveUiMode()` returned `mode: 'product'` from the URL alone") names a function and is decided by `resources/js/composables/useUiMode.ts:31,77 @3cd793a^`; split it off C6 as a separate [code] claim instead of leaving it under [unverified].
- **F2** reverses an earlier claim without citing it - OL-2609-045 C1 ("`parseUiMode()` returns `mode: 'product'` only when the query's `state` is exactly `product`") and C4 ("`mainTab` initialises to `obs` when the mode is `product`... `ProductLastStep` is rendered with `v-if="uiMode === 'product'"`") are both reversed by this diff (`useUiMode.ts:36-41 @3cd793a`, `show.vue:101,412 @3cd793a`), and the claim never names OL-2609-045; a follow-up claim should cite them inline.
- **F3** reverses an earlier claim without citing it - OL-2609-048 C1 ("with no `productSetup` it returns the parsed URL mode") and C2 ("`data-mode="product"` is on the document root ... on a single page for a `?state=product` visit outside one") are reversed by `useUiMode.ts:87 @3cd793a`; the claim cites OL-2609-048 only for its Risk line, not these two claims, so a follow-up claim should cite C1 and C2 inline.
- **F4** false Unchanged line - Unchanged line 1 says `ProductSetup`, the banner and the frame "read `mode`", but only the frame does (`ProductFlowFrame.vue:10,15 @3cd793a`); `ProductSetupBanner.vue:14 @3cd793a` reads `page.props.productSetup` directly, and `ProductSetup` is the PHP class `app/Support/ProductSetup.php`. None of the three is in the diff, so only the stated reason is wrong; a follow-up claim should restate it.

### Notes
- `AppLayout.vue:49-50 @3cd793a` (`:50 @HEAD`) still says "The one place the URL's UI mode is written onto <html data-mode>". After this change the mode is never read from the URL. The file is not in the diff, and a stale comment is not a claim.
- The shipped test was run from scratchpad copies of the `@3cd793a` `useUiMode.ts` and `useUiMode.test.ts` (with `node_modules` symlinked in), because the tree at HEAD has since moved on. No repo file was touched.
- Later commits touching these files (OL-2609-072, OL-2609-078, OL-2609-114) each carry a claim that discloses the drift noted above. None of the drift is a finding.
