## Audit of OL-2609-044 - feat(products): the verified badge on every product surface, and a green finished state on the product page

**Audited:** 2026-09-25
**Commit:** 88eacfe09d344e9eb70f50b6d13cf67feb166b9b
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED (structure) / UNVERIFIABLE (path origin) | Compound. Structure: `resources/js/components/ProductBadge.vue:11-22 @88eacfe` - one `<svg viewBox="0 0 24 24">`, one `<path fill="currentColor">`; `:role="label ? 'img' : 'presentation'"`, `:aria-label="label"`, `:aria-hidden="label ? undefined : 'true'"` (lines 14-16). "`d` equals the TDesign `verified-filled` path" cannot be checked in-repo: `git grep` finds the `d` string only in `ProductBadge.vue`, no TDesign/Iconify package is in `node_modules`, and `curl https://api.iconify.design/tdesign.json?icons=verified-filled` returned `CONNECT tunnel failed, response 403`. @HEAD the component is rewritten with a tooltip wrapper by OL-2609-088 (its C24); the `d` string is unchanged |
| C2 | CONFIRMED | `resources/js/pages/products/show.vue:198 @88eacfe` - `v-if="installed && installed.subject && remaining === 0"`; line 207-214 - one `Link` per `installed.overlays` with `:href="route('templates.show', overlay.id)"`. @HEAD the band is rebuilt: `show.vue:366` same condition, links now iterate `stages` (non-alert overlays, `show.vue:162`) via `urlWithTab(withLastMileHint(...), 'obs')` (OL-2609-079, OL-2609-078, OL-2609-080) |
| C3 | CONFIRMED | `show.vue:86-87 @88eacfe` - `done = steps.length - remaining`, `progress = steps.length ? Math.round(done / steps.length * 100) : 100`; line 234 `role="progressbar" :aria-valuenow="done" :aria-valuemax="steps.length"`; line 235 width `${progress}%`, `remaining ? 'bg-fuchsia-500' : 'bg-green-500'`. Same @HEAD `show.vue:137-138, 653-654` |
| C4 | CONFIRMED | `show.vue:345-373 @88eacfe` - `<style scoped>` holds only `.product-progress { transition: width ... }`, `.product-tick { animation: product-tick-land }` with a scale 0.4->1 / opacity 0->1 keyframe, and a `prefers-reduced-motion: reduce` block setting `transition: none` / `animation: none`; no other transition/animation in the diff. @HEAD more motion exists (`product-pulse` from OL-2609-080, `product-design-breathe` from OL-2609-118), each under the same reduced-motion block (`show.vue:869-881`) |
| C5 | CONFIRMED | Ran the @88eacfe `RecipeManifestValidator` (copied, namespace renamed) against the @88eacfe schema: `chat_checkin` and `follower_bowling` manifests -> `{"valid":true}`; `ready_message` of 161 chars -> `valid:false`, `/ready_message: Maximum string length is 160, found 161`. Schema `recipe-manifest.schema.json:98-103 @88eacfe` has `minLength 1`, `maxLength 160`. Same rule @HEAD (`schema.json:116-121`); manifests moved to hyphenated dirs by OL-2609-114 |
| C6 | CONFIRMED | All five files exist @88eacfe and @HEAD under `tests/Feature/`; none is in the diff. `php artisan test --filter='ProductInstallTest\|ProductBowlingTest\|ProductChatInstallsTest\|ProductUninstallTest\|ProductSetupFlowTest'` @HEAD: 5 suites PASS, 55 tests, 419 assertions. Run against the tree @HEAD only (see Notes) |
| C7 | UNVERIFIABLE | tagged [unverified] |

### Surface
Complete.

### Findings
- **F1** mistagged, needs outside the repo - C1's "`d` equals the TDesign `verified-filled` path" is tagged `[code]` but no copy of the TDesign icon exists in the repo, so that half can only be checked against an external source; split it into a `[code]` structure claim and an `[unverified]` provenance claim.
- **F2** scope - `resources/js/pages/products/show.vue @88eacfe` removes the right-column `Check` + "Installed" span (`show.vue:156 @88eacfe^`) and adds an uppercase "Installed" eyebrow above the `h1` (`show.vue:163 @88eacfe`); no claim or Surface line records the relocation or the dropped icon; record it in a follow-up claim.
- **F3** scope - `show.vue:224 @88eacfe` changes the checklist heading to "Your setup" when `remaining` is 0 (was always "Finish setting up"); not in any claim or Surface line; record it.
- **F4** scope - `show.vue:243 @88eacfe` changes a completed checklist row's border from `border-border` (`show.vue:198 @88eacfe^`) to `border-green-500/40`; not in any claim or Surface line; record it.
- **F5** record contradiction - the comment at `resources/js/components/ProductBadge.vue:3-4 @88eacfe` (still present @HEAD) lists "the wiring subject" among the surfaces the badge is used on, while this claim's Unchanged says the wiring subject does not carry it and no wiring file uses `ProductBadge` @88eacfe or @HEAD (`git grep ProductBadge`); correct the comment or the record.

### Notes
- C6 was run @HEAD only: an exported @88eacfe tree could not boot against the current `vendor/` (stale `Stevebauman\Location` provider, then Pest namespace binding to the repo's `tests/`), so "pass unchanged" at ship time is confirmed only in the sense that no test file is in the diff; the five test files have all changed since (`git diff --stat 88eacfe HEAD`, 128+/64-).
- C1 @88eacfe also sets `role="presentation"` on the unlabelled svg alongside `aria-hidden`; the claim does not mention it and does not deny it. @HEAD same (OL-2609-088).
- The finished band, header and listing card have been reworked since by OL-2609-078, -079, -080, -084 and -088; C2 and the index.vue "green when installed" Surface line describe the shipped tree, not HEAD (OL-2609-088 C22 makes the listing badge always violet).
- Unchanged: `WiringCatalog`, `WiringFacts`, the wiring settings page, `RekaToast.vue`, `AppLayout.vue` - none is in `git show --stat 88eacfe`.
