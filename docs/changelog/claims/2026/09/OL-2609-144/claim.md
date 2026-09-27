## OL-2609-144 - feat(products): split the product page into a public pitch and an authed install checklist

**Shipped:** 2026-09-27
**Commit:** `git log --grep=OL-2609-144`

### Surface
- `app/Http/Controllers/ProductController.php` - `show()` split into `pitch()` (public Blade, SEO-bearing) and `manage()` (authed Inertia checklist); every internal redirect repointed at `products.manage`; `installedFor()` helper added; the dead `overlays`/`lists`/`commands` payload computation and keys removed from `manage()`
- `app/Support/ProductSetup.php` - `banner()`'s `url` now built from `route('products.manage', ...)`
- `resources/css/welcome-demos.css` - Chat Emote Bubbles demo: rise animation switched from `transform: translateY()` to animating `bottom`; three color-tinted bubble variants collapsed into one uniform glass skin; per-bubble custom properties consumed instead of `nth-child` rules; emote image padded/contained instead of stretched to the bubble's edge
- `resources/js/components/ProductLastStep.vue` - "Back to the product" link repointed at `/products/{slug}/install`
- `resources/js/pages/products/design.vue` - breadcrumb and "Back to {name}" link repointed at `/products/{slug}/install`
- `resources/js/pages/products/show.vue` - pre-install "Installing gives you / You still do" marketing section removed; dead guest-login branch removed (the route is authed-only now, so it never rendered for a guest); the "Your setup" checklist section's guard narrowed to `remaining > 0`; the "Your overlay(s)" and "Good to know" blocks moved into the post-completion celebration section; the inline "Add to OBS" `Link`s in both OBS beats replaced with `AddToObsButton`
- `resources/js/pages/settings/integrations/index.vue` - "Install the product" link repointed at `/products/{slug}/install`
- `resources/recipes/chat-checkin/manifest.json` - `highlights` added
- `resources/recipes/chat-emote-bubbles/manifest.json` - `highlights` added
- `resources/recipes/chat-tower/manifest.json` - `highlights` added
- `resources/recipes/follower-bowling/manifest.json` - `highlights` added
- `resources/recipes/recipe-manifest.schema.json` - `highlights` property declared (optional, 1-3 string items)
- `resources/recipes/twitch-chat-overlay/manifest.json` - `highlights` added
- `resources/views/products/pitch.blade.php` - new file, the public pitch page
- `resources/views/welcome/demos/bubbles.blade.php` - new file, Chat Emote Bubbles' homepage/pitch-page demo (previously had none)
- `resources/views/welcome/navbar.blade.php` - in-page anchors (`#products` etc.) made homepage-relative (`/#products`), since the navbar is now also rendered on the non-homepage pitch page
- `resources/views/welcome/products.blade.php` - homepage teaser trimmed to name/first-sentence/CTA under a constrained demo; the local `$facts` array removed in favour of `$game['highlights']`; `chat-emote-bubbles` registered in the demo map
- `routes/web.php` - `GET /products/{slug}` (`products.show`) now resolves to `pitch()`; new `GET /products/{slug}/install` (`products.manage`) added to the authed group
- `tests/Feature/ProductBowlingTest.php`, `ProductCategoryTest.php`, `ProductChatEmoteBubblesTest.php`, `ProductChatPresetsTest.php`, `ProductDonationServicesTest.php`, `ProductInstallTest.php`, `ProductServiceConnectTest.php`, `ProductSetupFlowTest.php`, `ProductSlugRenameTest.php`, `ProductTowerTest.php`, `ProductTwitchChatTest.php`, `ProductUninstallTest.php` - updated for the route split; several page-payload assertions against fields the manage page no longer carries rewritten as direct manifest/overlay-document assertions

### Claims
- **C1** [code] `routes/web.php`'s `GET /products/{slug}` route, named `products.show`, calls `ProductController::pitch()`.
- **C2** [code] `routes/web.php` declares `GET /products/{slug}/install`, named `products.manage`, inside the `auth.redirect` middleware group, calling `ProductController::manage()`.
- **C3** [code] `ProductController::pitch()` returns a 409 response with an `X-Inertia-Location` header when the request carries an `X-Inertia` header, before rendering `products.pitch`.
- **C4** [test] `ProductInstallTest`'s "shows a product page to a visitor without an account" asserts the guest response to `/products/chat-checkin` carries no `X-Inertia` header.
- **C5** [code] `recipe-manifest.schema.json` declares `highlights` as an optional array of 1-3 strings.
- **C6** [code] `welcome/products.blade.php` contains no `$facts` array; its highlight list reads `$game['highlights']`.
- **C7** [code] `resources/views/welcome/demos/bubbles.blade.php`'s `$emotes` array maps 8 real Twitch emote codes (Poooound, GoldPLZ, PartyHat, bleedPurple, SingsNote, Kappa, Jebasted, TwitchUnity) to real Twitch CDN emote ids, and the markup renders each bubble as an `<img>`, never a text label.
- **C8** [code] `.ol-bub__bubble`'s `@keyframes ol-bub-rise` (`welcome-demos.css`) animates the `bottom` property across its keyframe stops, not `transform: translateY()`.
- **C9** [code] Every `.ol-bub__bubble` element `bubbles.blade.php` renders carries its own `--x`, `--size`, `--delay`, `--dur`, `--driftn`, `--rotn` inline custom properties, each from an independent `random_int()` call in the `$bubbles` collection build.
- **C10** [code] `products/show.vue`'s "Your setup" checklist `<section>` guard is `installed && installed.subject && remaining > 0`.
- **C11** [code] `products/show.vue` contains no "Installing gives you" / "You still do" section.
- **C12** [code] `ProductController::manage()`'s returned `product` array contains no `overlays`, `lists`, or `commands` keys.
- **C13** [test] `ProductSetupFlowTest`'s "sends the next step to the page its control is on..." asserts `ProductSetup::banner(...)['url']` equals `route('products.manage', 'chat-checkin')`.
- **C14** [code] `products/show.vue` imports and renders `AddToObsButton` in both the stages beat and the alert-only "Have an overlay in OBS" beat, in place of a `Link` to `templates.show?tab=obs`.
- **C15** [test] `php artisan test` passes the full suite (2412 passed, 6 skipped, 0 failed) against this tree.

### Unchanged
- `resources/js/pages/products/index.vue` (the `/products` shelf) is untouched - its card links already pointed at `/products/{slug}`, which now resolves to the pitch page automatically; no code there needed to change.
- `ProductController::design()` and `applyPreset()`'s designer-building logic (`ProductDesigner::*`) is untouched beyond the redirect targets already listed under Surface.
- The five donation-alert manifests' (`ko-fi-alerts`, `streamlabs-alerts`, `buy-me-a-coffee-alerts`, `fourthwall-alerts`, `throne-alerts`) `installs` sections are untouched; `highlights` was added only to the five product-category manifests that had homepage `$facts` copy to move.
- `App\Support\WiringReport` / `WiringFacts` (the checklist's data source) are untouched; only which section of `show.vue` renders their output, and when, changed.

### Risk
- Any in-flight OAuth connect (`integration_return_to.*` session key) captured before this deploy still names the old bare `/products/{slug}` URL; the callback will land on the pitch page instead of the checklist rather than erroring, but the person will need to click through to `/install` again to see the connected state.
- The bubbles demo hotlinks 8 emote ids from Twitch's own CDN. Twitch periodically retires global/promoted emotes (documented precedent: `resources/js/utils/chatSample.ts` lost `BibleThump` this way); a retired id renders as a broken image in the demo with no fallback.
