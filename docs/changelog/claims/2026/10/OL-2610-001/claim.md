## OL-2610-001 - feat(products): a full sales page for every chat product

**Shipped:** 2026-10-07
**Commit:** `git log --grep=OL-2610-001`

### Surface
- `app/Http/Controllers/ProductController.php` - `pitch()` renders `products.page` when the manifest declares `pitch`, else `products.pitch`; new private `pitchPage()` derives the setup steps, looks fallback, shared questions, the plays-note link and the "more products" list; `Route` facade imported
- `resources/recipes/recipe-manifest.schema.json` - optional `pitch` object declared (tagline, lede, plays, plays_note, reasons_heading, reasons, looks_heading, looks_lede, looks, setup_note, faq, closing)
- `resources/recipes/chat-checkin/manifest.json` - `pitch` block added
- `resources/recipes/chat-emote-bubbles/manifest.json` - `pitch` block added
- `resources/recipes/chat-tower/manifest.json` - `pitch` block added
- `resources/recipes/follower-bowling/manifest.json` - `pitch` block added
- `resources/recipes/twitch-chat-overlay/manifest.json` - `pitch` block added
- `resources/views/products/page.blade.php` - new file, the full product page
- `resources/views/products/_head.blade.php` - new file, the `<head>` moved out of `pitch.blade.php` so both templates share it
- `resources/views/products/pitch.blade.php` - its `<head>` replaced by `@include('products._head')`
- `resources/views/products/_works-with.blade.php` - new file, the Works with row (Twitch, Streamlabs Desktop, OBS Studio glyphs)
- `resources/views/welcome/demos/bowling.blade.php`, `bubbles.blade.php`, `chat.blade.php`, `checkin.blade.php`, `tower.blade.php` - the "live in OBS" title bar wrapped in `@unless ($bare ?? false)`
- `resources/css/product-page.css` - new file, the page's flow, scenes, looks, setup, FAQ and Works with styles
- `resources/js/welcome/app.ts` - imports `product-page.css`
- `public/products/chat-checkin/play-pin.svg`, `look-globe.svg`, `look-numbers.svg`, `look-list.svg` - new files
- `public/products/chat-tower/play-sway.svg` - new file
- `public/products/follower-bowling/play-lane.svg` - new file
- `public/products/chat-emote-bubbles/play-bubbles.svg` - new file
- `public/products/twitch-chat-overlay/play-feed.svg` - new file
- `tests/Feature/ProductPitchPageTest.php` - new file
- `tests/Feature/ProductInstallTest.php` - the guest-page test asserts a line from the new page instead of a `highlights` line the page no longer shows

### Claims
- **C1** [code] `ProductController::pitch()` renders the `products.page` view when the manifest has a `pitch` key and `products.pitch` otherwise.
- **C2** [code] `ProductController::pitchPage()` adds a "Switch on the bot" step only when the manifest's `requires_bot` is true, and sets `steps_word` to `two` when there are two steps.
- **C3** [code] `ProductController::pitchPage()` uses the manifest's `pitch.looks` when present and otherwise maps `designer.presets` to looks of `label` and `blurb`.
- **C4** [code] `ProductController::pitchPage()` appends two shared questions, "Is it really free?" and "Can I remove it later?", after the manifest's own `faq`.
- **C5** [code] `page.blade.php` escapes manifest text with `e()` before turning backtick spans into `<code class="pp-code">`.
- **C6** [code] `page.blade.php` includes each demo partial with `['bare' => true]`; `welcome/hero.blade.php` and `welcome/products.blade.php` pass no `bare`.
- **C7** [test] `ProductPitchPageTest` asserts every listed `product`-category manifest has a `pitch` and no `alert`-category manifest has one.
- **C8** [test] `ProductPitchPageTest` asserts each chat product page shows its tagline, every play title, every own and shared question, and does not show "live in OBS".
- **C9** [test] `ProductPitchPageTest` asserts the homepage still shows "live in OBS".
- **C10** [test] `ProductPitchPageTest` asserts Chat Tower shows "Ready in three steps" and "Switch on the bot", and Chat Emote Bubbles shows "Ready in two steps" without "Switch on the bot".
- **C11** [test] `ProductPitchPageTest` asserts every `src` and `image` path a `pitch` names exists under `public/`.
- **C12** [test] `ProductPitchPageTest` asserts the Chat Checkin page links to `route('settings.integrations.checkin.show')`.
- **C13** [unverified] With `['bare' => true]` changed to `['bare' => false]` in `page.blade.php`, `ProductPitchPageTest`'s "renders every section" test failed.

### Unchanged
- The five donation alert pages (`ko-fi-alerts`, `streamlabs-alerts`, `buy-me-a-coffee-alerts`, `fourthwall-alerts`, `throne-alerts`) declare no `pitch`, so `pitch()` still renders them with `products.pitch`; that view's body is not in the diff, only its `<head>` moved to a partial.
- `highlights` stays in all five product manifests: `welcome/products.blade.php` still reads it for the homepage teaser, and that file is not in the diff.
- The install checklist (`products.manage`, `products/show.vue`) is not in the diff.
- Product URLs and slugs are unchanged; the sitemap and `llms.txt` are not in the diff.

### Risk
- The step pictures are hand-drawn SVG sketches, not screenshots of the real overlays.
