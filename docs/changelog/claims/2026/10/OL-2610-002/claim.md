## OL-2610-002 - feat(products): a three-knob designer that plays itself on the Twitch Chat Overlay page

**Shipped:** 2026-10-08
**Commit:** `git log --grep=OL-2610-002`

### Surface
- `app/Http/Controllers/ProductController.php` - new private `looksTeaser()`; `pitchPage()` returns it as `teaser`
- `resources/views/products/_looks-teaser.blade.php` - new file, the knobs, the preview frame, the name field and the designer button
- `resources/views/products/page.blade.php` - the Looks section includes `products._looks-teaser` when `$page['teaser']` is set, and keeps the text cards otherwise
- `resources/js/welcome/looksTeaser.ts` - new file, the preview chat, the knobs and the autoplay cursor
- `resources/js/welcome/app.ts` - imports `initLooksTeasers` and calls it on `DOMContentLoaded`
- `tests/Feature/ProductPitchPageTest.php` - two tests: the teaser on the chat overlay page, text looks on Chat Emote Bubbles
- `tests/Feature/ProductTwitchChatTest.php` - two tests: the designer button's target, the name field's pre-fill

### Claims
- **C1** [code] `ProductController::looksTeaser()` returns null for every slug other than `twitch-chat-overlay`.
- **C2** [code] `ProductController::looksTeaser()` reads the `css` field of the manifest's first `installs.overlays` file through `OverlayMarkdown::parse()`.
- **C3** [code] `ProductController::looksTeaser()` replaces every `[[[c:key]]]` in that CSS with the first designer preset's value for `key`, and returns null when any `[[[` remains afterwards.
- **C4** [code] `ProductController::looksTeaser()` builds one `fonts.bunny.net` URL naming every distinct `font` value across the presets, slugged lowercase with spaces hyphenated.
- **C5** [code] `_looks-teaser.blade.php` renders the preview as an `<iframe>` whose `srcdoc` holds that CSS and an empty `.ol-chat`, with `sandbox="allow-same-origin"` and no `allow-scripts`.
- **C6** [code] `_looks-teaser.blade.php` links "Open the full designer" to `products.design` when `$installed` is true and to `products.manage` otherwise.
- **C7** [code] `_looks-teaser.blade.php` pre-fills the name field from the logged-in user's `twitch_data['display_name']`, falling back to `name`.
- **C8** [code] `looksTeaser.ts` builds every preview message with `createElement` and `textContent`, and never assigns `innerHTML`.
- **C9** [code] `looksTeaser.ts` positions the cursor with the `translate` property, not `transform`.
- **C10** [code] `looksTeaser.ts` starts no autoplay loop when `prefers-reduced-motion: reduce` matches.
- **C11** [code] `looksTeaser.ts` stops autoplay on `pointerenter` or `focusin` on the teaser and resumes it `RESUME_MS` (5000 ms) after `pointerleave`.
- **C12** [code] `looksTeaser.ts` adds a chat message only while the teaser intersects the viewport and the document is visible.
- **C13** [test] `ProductPitchPageTest` asserts the Twitch Chat Overlay page contains `data-looks-teaser`, a `data-look="vapor"` button and the rule `.skin-terminal .name::before`, and contains no `[[[c:`.
- **C14** [test] `ProductPitchPageTest` asserts the Chat Emote Bubbles page does not contain `data-looks-teaser`.
- **C15** [test] `ProductTwitchChatTest` asserts a guest does not get the `products.design` link, and a user with the product installed does.
- **C16** [test] `ProductTwitchChatTest` asserts the name field carries the logged-in user's `display_name` and is empty for a guest.
- **C17** [unverified] In Chrome, a scale of 0.8 animated on the cursor while it was placed with `transform: translate(300px, 200px)` moved its box 58px left and 38px up mid-click; placed with `translate`, its centre moved 1px.

### Unchanged
- The overlay itself: `resources/recipes/twitch-chat-overlay/chat.md` and its manifest are read by `looksTeaser()` but are not in the diff.
- The real designer (`ProductController::design()`, `products.design`) is linked to and is not in the diff; it still answers 404 to an account without the product installed.
- `pitchPage()`'s `looks` value is still computed and still drives the text cards on every product without a teaser.

### Risk
- The preview loads the preset fonts from `fonts.bunny.net`, the same third party the page's own font already comes from.
