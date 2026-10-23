## OL-2610-006 - feat(products): the finished product page leads with the one step left

**Shipped:** 2026-10-24
**Commit:** `git log --grep=OL-2610-006`

### Surface
- `app/Http/Controllers/ProductController.php` - `arrived` prop, `obs_hint` passthrough, `loaded_ago` per stage overlay, new `lastLoaded()` and `realLinks()`, `yourOverlays()` filters through `realLinks()`, install toast removed
- `resources/js/pages/products/show.vue` - installed state rebuilt around one green band, the moves list, reference section and footer; arrival scroll and glow
- `resources/js/utils/productMoves.ts` - new file, pure ordering of the finished page's steps
- `resources/js/utils/productMoves.test.ts` - new file, Vitest for `productMoves()`
- `resources/js/components/AddToObsButton.vue` - `variant`, `hint` props and a `done` event
- `resources/js/components/ProductServices.vue` - `inline` prop; no status word for a connected service whose link is unused
- `resources/recipes/recipe-manifest.schema.json` - `obs_hint` field, `ready_message` description
- `resources/recipes/chat-tower/manifest.json` - ready_message
- `resources/recipes/chat-checkin/manifest.json` - ready_message
- `resources/recipes/twitch-chat-overlay/manifest.json` - ready_message, `obs_hint`, size note removed
- `resources/recipes/chat-emote-bubbles/manifest.json` - ready_message, `obs_hint`, size note removed
- `resources/recipes/ko-fi-alerts/manifest.json` - ready_message, notes trimmed
- `resources/recipes/streamlabs-alerts/manifest.json` - ready_message, notes trimmed
- `resources/recipes/fourthwall-alerts/manifest.json` - ready_message, notes trimmed
- `resources/recipes/buy-me-a-coffee-alerts/manifest.json` - ready_message, notes trimmed
- `resources/recipes/throne-alerts/manifest.json` - ready_message, notes trimmed
- `tests/Feature/ProductSetupFlowTest.php` - two `arrived` tests
- `tests/Feature/ProductTowerTest.php` - `loaded_ago` test
- `tests/Feature/ProductDonationServicesTest.php` - designer-preview test; install asserts no `success` flash
- `tests/Feature/ProductInstallTest.php` - ready-message guard test; install asserts no `success` flash

### Claims
- **C1** [code] `ProductController::manage()` passes `arrived` as true exactly when it calls `ProductSetup::end()`: the product is installed, its setup flow is active for this slug, and `remaining` is 0.
- **C2** [test] `ProductSetupFlowTest` "says arrived only on the view that ends the flow" asserts `arrived` is true on the view that ends the flow and false on the next visit.
- **C3** [test] `ProductSetupFlowTest` "arrives on the first view after an install whose steps were already done" asserts `arrived` is true on the first view after such an install.
- **C4** [code] `ProductController::install()` redirects to `products.manage` without a `success` flash.
- **C5** [test] `ProductInstallTest` and `ProductDonationServicesTest` assert `assertSessionMissing('success')` after an install POST.
- **C6** [code] `installedView()` gives every non-alert overlay a `loaded_ago` key from `lastLoaded()`, null when no real link has loaded its slug; alert overlays get no such key.
- **C7** [code] `realLinks()` selects the user's `OverlayAccessToken` ids excluding those whose `metadata->purpose` equals `ProductDesigner::TOKEN_PURPOSE`.
- **C8** [code] `lastLoaded()` and `yourOverlays()` both read `OverlayAccessLog` through `realLinks()`.
- **C9** [test] `ProductTowerTest` "says when a real overlay link last loaded the tower, and never counts the designer preview" asserts `loaded_ago` stays null for a designer-preview load and is set for a real token load.
- **C10** [test] `ProductDonationServicesTest` "does not count the designer preview as an overlay link serving an overlay" asserts `your_overlays.loaded` is false when only a designer-preview load exists.
- **C11** [code] `productMoves()` marks at most one move `current`, the first move that is not done.
- **C12** [test] `productMoves.test.ts` covers the move order for chat and alert products, including a connected Buy Me a Coffee counting as pasted and an unproven Ko-fi or Throne link folding into the test tip.
- **C13** [code] `show.vue` marks the current move with `data-product-focus` and `aria-current="step"`, and scrolls it into view only when the `arrived` prop is true, with `behavior: 'instant'` under `prefers-reduced-motion`.
- **C14** [code] In the finished state (`remaining === 0`) `show.vue` renders no "Installed" label, no "Connected" pill and no numbered beat circles; the "Installed" label remains only in the header shown while steps are left.
- **C15** [test] `ProductInstallTest` "never has a ready message claim the overlay is already on stream" fails for any listed manifest whose lowercased `ready_message` contains "waiting in obs", "on your stream right now", "is on screen" or "wired to".
- **C16** [code] `AddToObsButton` accepts `variant` (`hero`, `compact`, `again`) and `hint`, and emits `done` from "Close this screen and continue".
- **C17** [code] `manage()` passes the manifest's `obs_hint` as `product.obs_hint`, null when absent.
- **C18** [unverified] In Chrome on overlabels.test, a fresh Chat Tower install scrolled to and lit "Add Chat Tower to OBS", the card turned done after the overlay link was loaded in a second tab, and a reload stayed at the top with nothing lit.
- **C19** [unverified] The arrival scroll has not been checked in Firefox.

### Unchanged
- The in-progress checklist (`remaining > 0`) renders the same wires with the same CTAs; only Uninstall moved to the footer and the designer card moved below the checklist.
- `useProductFocus()` in `useUiMode.ts` is not in the diff; its `behavior: 'auto'` scroll still follows the global `scroll-behavior: smooth` under reduced motion.
- The pre-install ingredients form and the public pitch page are not in the diff.

### Risk
A stage overlay nobody has loaded through a real link in the 90 days the access log keeps shows the
Add to OBS step as open again. Any load of the link counts, including a browser tab or the QR code,
which is why the done copy says "has loaded from your link" rather than "is in OBS".
