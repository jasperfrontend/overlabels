## OL-2611-028 - feat(bubbles): twenty shapes for Chat Emote Bubbles

**Shipped:** 2026-11-19
**Commit:** `git log --grep=OL-2611-028`

### Surface
- `resources/recipes/chat-emote-bubbles/bubbles.md` - the Shape look: an SVG defs block before the emotes loop (twenty `ol-shape-*` paths, a clip path and the `ol-shape-mask` mask), a `shape-[[[c:shape]]]` class on the root, the shape CSS and per-shape constants, `--reach` / `--lead` replacing `--size` in the travel, spawn and pop distances, four new controls and the `shape` choice on `look`, updated descriptions and counts
- `resources/recipes/chat-emote-bubbles/manifest.json` - the four keys in the designer's Look group and in every preset, copy that counted four looks and twelve controls, new `hero_alt`
- `public/products/chat-emote-bubbles-hero.jpg` - replaced with a hero showing cat-head bubbles and the designer's shape controls
- `database/migrations/2026_11_19_170000_add_shapes_to_installed_chat_emote_bubbles.php` - new file, backfill for existing installs
- `tests/Feature/BubblesShapesBackfillTest.php` - new file
- `tests/Pest.php` - `shapesBackfill()` and `makePreShapes()` shared helpers
- `tests/Feature/ProductChatEmoteBubblesTest.php` - sixteen controls, the `shape` vocabulary, a test for the shape wiring and the travel distances
- `tests/Feature/BubblesGradientBackfillTest.php` - installs rolled back to before the shapes; the active-preset test also runs the shapes backfill
- `tests/Feature/BubblesPlainLookBackfillTest.php` - installs rolled back to before the shapes; the declared choices compared without `shape`
- `tests/Feature/EnabledIfBackfillTest.php` - bubbles installs rolled back to before the shapes
- `resources/help/pages/chat-emote-bubbles.md` - the Shape look and its three controls, sixteen controls
- `resources/help/pages/index.md` - sixteen controls

### Claims
- **C1** [code] The `c:shape` control in `bubbles.md` is a text control with exactly twenty choices, default `ghost`, declared `enabled_if=["c:look = shape"]`.
- **C2** [code] The `c:look` choices in `bubbles.md` are `bubble`, `snow`, `heart`, `shape`, `plain` in that order, and the `shape` choice is labelled `Choose another shape`.
- **C3** [code] `c:border_width` (0 to 8, step 0.5, default 3), `c:fill_strength` (0 to 100, default 30) and `c:breathing_room` (0 to 12, default 5) are number controls in `bubbles.md`, each declared `enabled_if=["c:look = shape"]`.
- **C4** [test] `ProductChatEmoteBubblesTest` "draws every shape choice, and cuts each bubble out with the one mask" asserts every shape choice has exactly one `<path id="ol-shape-<value>"` in the html and a `.shape-<value>` rule with `--sr`, `--ox`, `--oy`, `--bw`, `--bh`.
- **C5** [test] The same test asserts the `ol-shape-defs` SVG appears in the html before `[[[foreach:emotes as e]]]`.
- **C6** [code] `.look-shape --k` in `bubbles.md` divides half the emote height (`--size-n * 0.28`) plus `--room` plus `--border` by the shape's `--sr`, so the emote keeps the `.skin img` size of the bubble look.
- **C7** [test] The same test asserts `stroke-width: calc(2px * var(--border) / var(--k));` on `.ol-shape-ring`.
- **C8** [unverified] Firefox drew the border as a hairline with a unitless `calc()` for `stroke-width` and at the chosen width with `2px`; Chrome drew both correctly.
- **C9** [code] `.bubble --dist` in `bubbles.md` adds `var(--reach)`; `--reach` is `var(--size)` on `.ol-bubbles` and only `.look-shape` sets another value.
- **C10** [code] `.spawn-outside .bubble --offabs` is `calc(var(--lead) + 8px)` and `.popping .bubble --dist` subtracts `var(--lead)`; `--lead` is `calc(var(--size) / 2)` on `.ol-bubbles` and only `.look-shape.dir-up` and `.look-shape.dir-down` set another value.
- **C11** [unverified] Before the `--reach` change, finished paw-print bubbles (direction down, Pop after 0) parked with their top 4px inside the viewport, measured in Firefox; after it, 2 to 11px below it.
- **C12** [code] Every `designer.presets[*].values` in `manifest.json` names `shape`, `border_width`, `fill_strength` and `breathing_room`, and the `Look` group lists all four.
- **C13** [test] `BubblesShapesBackfillTest` "gives an install from before the shapes exactly what a fresh install has now" asserts html, css, every control row (key, label, type, value, description, config, sort order, source) and the tag allowlist equal a fresh install's after the backfill runs.
- **C14** [unverified] C13 failed when the description update in the backfill was replaced with `if (false)`.
- **C15** [test] `BubblesShapesBackfillTest` asserts the backfill moves no existing control's value or `updated_at`.
- **C16** [test] `BubblesShapesBackfillTest` asserts an install whose html or css was edited at a patched snippet keeps its html, css and twelve controls, for three different snippets.
- **C17** [test] `BubblesShapesBackfillTest` asserts a `look` select or a `bubble_size` description the streamer changed is kept.
- **C18** [test] `BubblesShapesBackfillTest` asserts a second run changes nothing.
- **C19** [code] The backfill skips an install with no `gradient_angle` control, so an install the 2026-11-15 gradient backfill skipped is skipped too.
- **C20** [code] `public/products/chat-emote-bubbles-hero.jpg` is 1280 by 720 and contains no `c2pa` string.

### Unchanged
- The overlay renderer is not in the diff. Emotes stay in the page until the 100-occurrence emote window drops them; the fix in C9 and C10 moves where a finished bubble sits, not how long it is kept.
- The other four looks' rules (`.look-bubble`, `.look-snow`, `.look-heart`, `.look-plain`) are not in the diff, and C9 and C10 give them the same distances as before.
- The `og:image` tag in `resources/views/products/_head.blade.php` already reads the product's `hero`; it is not in the diff.

### Risk
On deploy the backfill rewrites the html, css and controls of every Chat Emote Bubbles install whose
patched snippets are intact. Nothing on stream changes until a streamer picks the Shape look:
`look` keeps its value.
