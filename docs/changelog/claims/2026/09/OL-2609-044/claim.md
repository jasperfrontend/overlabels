## OL-2609-044 - feat(products): the verified badge on every product surface, and a green finished state on the product page

**Shipped:** 2026-09-09
**Commit:** `git log --grep=OL-2609-044`

### Surface
- `resources/js/components/ProductBadge.vue` - new: the `tdesign:verified-filled` mark (TDesign Icons, MIT) as an inline SVG filled with `currentColor`, `role="img"` with a label or `aria-hidden` without
- `resources/js/pages/products/show.vue` - badge in the header, green header when installed, a green finished band with the badge, `ready_message` and one "Add <overlay> to OBS" link per overlay, a progress bar and landed ticks on the checklist, scoped motion styles with a reduced-motion override
- `resources/js/pages/products/index.vue` - badge in the heading and on each card, green when installed
- `resources/js/components/ProductSetupBanner.vue` - badge replaces the package icon while steps remain
- `resources/recipes/recipe-manifest.schema.json` - optional `ready_message`, 1 to 160 characters
- `resources/recipes/chat_checkin/manifest.json` - `ready_message`
- `resources/recipes/follower_bowling/manifest.json` - `ready_message`
- `app/Http/Controllers/ProductController.php` - `show()` passes `ready_message`

### Claims
- **C1** [code] `ProductBadge.vue` renders one `<svg viewBox="0 0 24 24">` with one `<path fill="currentColor">` whose `d` equals the TDesign `verified-filled` path; with a `label` prop it sets `role="img"` and `aria-label`, without one `aria-hidden="true"`.
- **C2** [code] `products/show.vue` renders the finished band only when `installed`, `installed.subject` and `remaining === 0` all hold, and inside it one `Link` to `route('templates.show', overlay.id)` per entry of `installed.overlays`.
- **C3** [code] The checklist section renders a `role="progressbar"` element with `aria-valuenow` = done steps and `aria-valuemax` = total steps, whose inner bar width is `round(done / total * 100)` percent, fuchsia while steps remain and green when none do.
- **C4** [code] The only motion added is a `width` transition on the bar and a scale-and-fade keyframe on a landed tick; both are disabled under `prefers-reduced-motion: reduce`.
- **C5** [code] `RecipeManifestValidator` accepts both shipped manifests with `ready_message` set and rejects a `ready_message` over 160 characters through the schema.
- **C6** [test] The product suites (`ProductInstallTest`, `ProductBowlingTest`, `ProductChatInstallsTest`, `ProductUninstallTest`, `ProductSetupFlowTest`) pass unchanged against the extended manifests and controller.
- **C7** [unverified] On `overlabels.test` on 2026-09-09, an installed Chat Checkin with every step satisfied rendered the green header, the green band with the badge and "Everything is in place", the ready message, the OBS link, "4 of 4 done" and a full green bar.

### Unchanged
- `WiringCatalog`, `WiringFacts` and the wiring settings page are not in the diff; the wiring subject for a product does not carry the badge yet.
- `RekaToast.vue` and `AppLayout.vue` are not in the diff; no page outside the product surfaces takes any of this styling.

### Risk
The product page is the one place in the app with a solid green band and motion; that is by decision, for this surface only.
