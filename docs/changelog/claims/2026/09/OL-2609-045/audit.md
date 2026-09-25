## Audit of OL-2609-045 - feat(products): a URL-carried product mode turns the overlay page's Add to OBS tab green, with a last-step callout

**Audited:** 2026-09-25
**Commit:** d1e970879018771f09513a0f298d2bead44a0636 (sole commit carrying `Changelog: OL-2609-045`)
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/composables/useUiMode.ts:22-23,30,35 @d1e9708` - `MODES = ['product']`, `mode = MODES.includes(state) ? state : null`, `product: mode && product && SLUG.test(product) ? product : null`, `SLUG = /^[a-z][a-z0-9_]{0,49}$/`. @HEAD `parseUiMode()` returns `{ lastMile, product }` instead (`useUiMode.ts:35-45 @HEAD`, OL-2609-050 C1), mode comes from `productSetup` via `resolveUiMode()` (`:107 @HEAD`, OL-2609-048 / OL-2609-050 C2), and `SLUG` admits a hyphen (`:32 @HEAD`, OL-2609-114 C14) |
| C2 | CONFIRMED | `useUiMode.ts:55-58 @d1e9708` - `if (options.apply) watchEffect(() => applyUiMode(mode.value))`; `applyUiMode()` sets `root.dataset.mode` or deletes it (`:42-46`); `git grep` at d1e9708 finds `useUiMode({ apply: true })` only at `resources/js/layouts/AppLayout.vue:51`. @HEAD same watchEffect (`useUiMode.ts:131-133`), still sole `apply: true` caller `AppLayout.vue:52` |
| C3 | CONFIRMED | `resources/css/app.css:14 @d1e9708` - `@custom-variant product (&:is([data-mode='product'] *));`; identical at `app.css:14 @HEAD` |
| C4 | CONFIRMED | `resources/js/pages/templates/show.vue:100 @d1e9708` - `ref<string>(uiMode.value === 'product' ? 'obs' : 'overview')`; `:324` obs tab button gets `product:border-t-green-400 product:bg-green-600 product:text-white product:hover:bg-green-700`; `:410` `<ProductLastStep v-if="uiMode === 'product'" ...>` inside the `mainTab === 'obs'` panel (`:409`). @HEAD `mainTab` is `useAddressableTabs(..., 'overview')` (`:115`, OL-2609-072 C25), the classes live on the `obs` entry of `mainTabs` (`:98`, OL-2609-071 C11), and the callout's v-if is `uiMode === 'product' \|\| uiLastMile` (`:411`, OL-2609-050 C4) |
| C5 | CONFIRMED | `resources/js/pages/products/show.vue:210 @d1e9708` - the finished band's "Add {{ overlay.name }} to OBS" `Link` gains `?state=product&product=${product.slug}`; the "Your overlay" row link at `:270` is `route('templates.show', overlay.id)` and not in the diff. @HEAD the OBS hrefs are `urlWithTab(withLastMileHint(...), 'obs')` (`:422,461`, OL-2609-078 C2); the row link at `:689 @HEAD` is still plain |
| C6 | CONFIRMED | `useUiMode.test.ts @d1e9708` covers a path, an absolute URL with `#obs`, missing param, `state=party`, `product` without `state`, `../etc` and empty slug, set/remove on a given root, null root. Shipped test + source run in an isolated scratch copy: `vitest run` 5 passed. @HEAD the file was rewritten (OL-2609-048/050/072/078/114); `npm test -- resources/js/composables/useUiMode.test.ts` 15 passed |
| C7 | UNVERIFIABLE | tagged [unverified]; a manual observation on `overlabels.test` |

### Surface
Complete.

### Findings
None.

### Notes
- Unchanged: `AddToObsPanel.vue` and `HelpContext` are not in the diff; at d1e9708 `git grep useUiMode` finds readers only in `AppLayout.vue` and `templates/show.vue`, so "no other page reads the mode" held at ship. @HEAD `ProductFlowFrame.vue:10` and `useProductFocus()` also read it (OL-2609-048, OL-2609-072).
- The Unchanged line's "HelpContext ignores undeclared query parameters" is an untagged assertion; it agrees with CLAUDE.md ("undeclared query params are ignored").
- C5 alters the finished-band href that OL-2609-044 C2 recorded as `route('templates.show', overlay.id)` without citing OL-2609-044; it builds on it and does not reverse it.
- OL-2609-050 replaces this claim's C1 return shape and does not cite OL-2609-045 inline (OL-2609-048 C2 does). That belongs in the audit of OL-2609-050, not here.
