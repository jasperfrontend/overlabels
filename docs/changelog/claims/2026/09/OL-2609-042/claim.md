## OL-2609-042 - feat(products): a setup banner on every app page reels a mid-install streamer back to the product

**Shipped:** 2026-09-09
**Commit:** `git log --grep=OL-2609-042`

### Surface
- `app/Support/ProductSetup.php` - new: `start()`, `end()`, `activeSlug()`, `banner()`, `steps()`, `instanceFor()`; the fact lives under the `product_setup` preference
- `app/Http/Controllers/ProductController.php` - `install()` starts the flow after a successful install; `show()` forgets the moderated-channels cache and ends the flow when nothing is left; `uninstall()` ends it for the same slug; new `dismissSetup()`; `installedView()` adds `remaining`
- `app/Http/Middleware/HandleInertiaRequests.php` - shares `productSetup`, null unless a flow is active
- `routes/web.php` - `products.setup.dismiss` inside the auth group
- `resources/js/components/ProductSetupBanner.vue` - new: fuchsia while steps remain, green when ready, "Back to" / "Go to installed product" link, "Not now"
- `resources/js/layouts/app/AppSidebarLayout.vue` - mounts the banner after `VersionBanner`
- `resources/js/types/index.d.ts` - `productSetup` on `AppPageProps`
- `tests/Feature/ProductSetupFlowTest.php` - new file, 9 tests

### Claims
- **C1** [code] `ProductSetup::start()` writes `['slug', 'started_at']` under the `product_setup` preference through `User::setPreference()` and saves; `end()` writes null there; no migration is in the diff.
- **C2** [code] `ProductController::install()` calls `ProductSetup::start()` only after `RecipeInstaller::install()` returns without throwing; a refused install starts no flow.
- **C3** [code] `HandleInertiaRequests` shares `productSetup` as null when there is no user or `ProductSetup::activeSlug()` is null, and otherwise as `ProductSetup::banner()`: `slug`, `name`, `url` (the product page), `remaining` (count of MISSING wires), `next` (label and message of the first MISSING wire in circuit order, or null), `ready` (`remaining` is 0).
- **C4** [code] `ProductSetup::banner()` returns null when the manifest is not found or the user has no instance of the slug, so a deleted instance leaves no banner.
- **C5** [code] `ProductController::show()` calls `BotModeratedChannels::forget()` before computing the installed view only when the active flow's slug is this product, and calls `ProductSetup::end()` when that view's `remaining` is 0.
- **C6** [code] `ProductController::uninstall()` ends the flow only when `activeSlug()` equals the uninstalled slug; `dismissSetup()` ends it unconditionally and returns `back()`.
- **C7** [code] `ProductSetupBanner.vue` renders nothing when `productSetup` is null, is mounted in `AppSidebarLayout` only, and posts to `products.setup.dismiss` from "Not now"; the ready state has no "Not now".
- **C8** [test] `ProductSetupFlowTest` asserts C2 both ways, C3 on `/dashboard` with `remaining` 2 and `next.label` "The bot is switched on" for a fresh checkin install, C4 after deleting the instance directly, C5 for both the cache forget (mid-setup only) and the end on a ready page, C6 for both roads, and that `steps()` keeps circuit order with not-applicable wires removed.

### Unchanged
- `VersionBanner.vue` is not in the diff; the setup banner is a sibling in the same slot, not a mode of it.
- `WiringFacts::productSubject()` and `WiringReport::build()` are not in the diff; `ProductSetup::steps()` reads their output, which is what keeps the banner and the product page in agreement.
- The product pages render outside `AppLayout` and are not in the diff for the banner; it cannot appear there.

### Risk
While a flow is active, every app page load runs the product's wiring queries once more, plus the moderated-channels lookup from cache. Nothing runs for anyone without an active flow.
