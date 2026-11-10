## OL-2611-010 - feat(alerts): alert products ask for the Alerts overlay, and /kits leads with the ready-made kits

**Shipped:** 2026-11-10
**Commit:** `git log --grep=OL-2611-010`

### Surface
- `app/Http/Controllers/ProductController.php` - `yourOverlays()` reads the Alerts overlay; returns `alerts_overlay`, drops `total` and the newest-overlays fallback
- `app/Http/Controllers/KitController.php` - `index()` sends `featuredKits` and leaves featured kits out of `recentPublicKits`
- `resources/js/components/AlertsOverlayPanel.vue` - `reload` prop
- `resources/js/pages/products/show.vue` - pending OBS card for alert-only products is the Alerts overlay panel
- `resources/js/pages/kits/index.vue` - "Ready-made kits" section above "Your kits"
- `resources/recipes/buy-me-a-coffee-alerts/manifest.json` - description names the Alerts overlay
- `resources/recipes/fourthwall-alerts/manifest.json` - same
- `resources/recipes/ko-fi-alerts/manifest.json` - same
- `resources/recipes/streamlabs-alerts/manifest.json` - same
- `resources/recipes/throne-alerts/manifest.json` - same
- `resources/recipes/buy-me-a-coffee-alerts/tip_alert.md` - targeting sentence names the Alerts overlay
- `resources/recipes/fourthwall-alerts/tip_alert.md` - same
- `resources/recipes/ko-fi-alerts/tip_alert.md` - same
- `resources/recipes/streamlabs-alerts/tip_alert.md` - same
- `resources/recipes/throne-alerts/tip_alert.md` - same
- `tests/Feature/ProductDonationServicesTest.php` - OBS step tests rewritten for the new rule, one added
- `tests/Feature/KitPickerTest.php` - one test added
- `CLAUDE.md` - Alerts overlay and front door sections updated

### Claims
- **C1** [test] For an installed alert-only product with no Alerts overlay and no static overlay served, `installed.your_overlays` is `loaded` false, `overlays` empty and `alerts_overlay` `{overlay: null, live: false}`, even when the user owns a static overlay.
- **C2** [test] With a live Alerts overlay, `installed.your_overlays` is `loaded` true and `overlays` lists only the Alerts overlay.
- **C3** [test] With no live Alerts overlay, a static overlay a real overlay link served in the last 30 days makes `loaded` true and is the only overlay listed.
- **C4** [test] A load through the designer preview token does not count as served.
- **C5** [test] A product with a static overlay of its own gets `your_overlays` `{loaded: false, overlays: [], alerts_overlay: null}`.
- **C6** [test] `kits.index` sends the featured kits as `featuredKits`, and `recentPublicKits` excludes them.
- **C7** [code] `products/show.vue` renders `AlertsOverlayPanel` with `reload` `['installed']` for a pending `obs` move of an alert-only product, and no longer renders a list of the person's overlays there.
- **C8** [code] No file under `resources/recipes/*-alerts/` contains "inside every overlay you have in OBS".
- **C9** [unverified] On local, in Firefox: the Ko-fi Alerts product page with a live Alerts overlay showed "The alert shows inside Alerts", and `/kits` showed "Ready-made kits" above "Your kits".

### Unchanged
- `productMoves.ts` still marks the `obs` move done from `hostLoaded`, which is `your_overlays.loaded`; it is not in the diff.
- `RecipeInstaller` still installs these alerts without targets; it is not in the diff.

### Risk
An alert product installed by someone with static overlays that OBS has not loaded lately now asks
for the Alerts overlay instead of offering those overlays.
