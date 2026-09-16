## OL-2609-089 - feat(products): Twitch Chat, a fourth product that installs one overlay whose look is twelve controls

**Shipped:** 2026-09-17
**Commit:** `git log --grep=OL-2609-089`

### Surface
- `resources/recipes/twitch_chat/manifest.json` - new file: listed, `category` `product`, `requires_bot` false, no integrations, installs one overlay (`chat` -> `chat.md`), `max_instances_per_user` 1, hero, ready message, three notes
- `resources/recipes/twitch_chat/chat.md` - new file: the static overlay document (head with a Google Fonts link for five families, html with `[[[foreach:chat as msg]]]`, css driven by custom properties) declaring twelve template-scoped controls
- `public/products/twitch-chat-hero.svg` - new file: the 1280x720 hero exported from the Claude Design project as `twitch-chat-overlay-hero-5a.svg`, with its C2PA `<metadata>` manifest and `xmlns:c2pa` attribute removed
- `tests/Feature/ProductTwitchChatTest.php` - new file
- `tests/Feature/ProductCategoryTest.php` - product counts 3 -> 4, totals 8 -> 9, `twitch_chat` added to the expected shelf orders, `kofi_alert` index 5 -> 6
- `tests/Feature/ProductInstallTest.php` - `twitch_chat` added to the expected listed slugs, listing count 8 -> 9

### Claims
- **C1** [code] `resources/recipes/twitch_chat/manifest.json` has `listed` true, `category` `product`, `requires_bot` false, `requires_integrations` `[]`, and `installs` containing only `overlays: [{ref: chat, file: chat.md}]`.
- **C2** [code] `OverlayMarkdown::parse()` on `chat.md` yields twelve controls with keys, in order: `layout`, `font`, `font_size`, `twitch_colors`, `name_color`, `text_color`, `accent`, `background`, `background_color`, `lifetime`, `show_badges`, `emote_size`; none is of type `expression`.
- **C3** [code] Every `[[[c:<key>]]]` or `[[[if:c:<key>` reference in the document's `html` and `css` names one of those twelve keys, and every one of the twelve is referenced.
- **C4** [code] The `css` field contains no `[[[if:`, `[[[elseif:` or `[[[foreach:`, so `compileCssBindings()` in `resources/js/utils/tagParser.ts` does not bail to the slow path for it; every control read in CSS is a bare tag on a custom property of `.ol-chat`.
- **C5** [code] `--font: [[[c:font]]];` is unquoted. The bindings compiler rewrites the tag to a `var(--ol-c-font)` reference, so a quoted form produces the literal string `"var(--ol-c-font)"` as the font family.
- **C6** [code] The root element's class list is `ol-chat layout-[[[c:layout]]] bg-[[[c:background]]]` plus ` fading` only inside `[[[if:c:lifetime > 0]]]`, and `.fading .msg` is the only rule that applies the `ol-chat-out` animation, with `var(--life)` as its delay and `forwards` as its fill.
- **C7** [code] `.layout-bottom` is `column` + `flex-end`, `.layout-top` is `column-reverse` + `flex-end`, `.layout-ticker` is `row` + `flex-end`: each packs toward the edge the newest message sits on, so a full window overflows off the opposite edge.
- **C8** [code] Each message element carries `data-key="[[[msg.id]]]"`, the key `OverlayRenderer.vue`'s `getMorphNodeKey()` reads, so morphdom keeps existing message nodes across re-renders and their CSS animations do not restart.
- **C9** [code] The name's inline `style="color: [[[msg.color]]]"` is emitted only inside `[[[if:c:twitch_colors]]]`; the badge span only inside `[[[if:c:show_badges]]]`.
- **C10** [test] `ProductTwitchChatTest` asserts the listing at index 3 with hero, category, `installs` `['Overlay']` and null service; the product page with one overlay named `Twitch Chat`, empty integrations, lists and commands, `requires_bot` false; an install creating one static template with the twelve controls in the order of C2, zero expression and zero source-managed controls, and zero integration, list or user-scoped control rows; C3; C4; C6's class strings; the wiring states `product.overlay` SATISFIED and `product.integration`, `product.list`, `product.command`, `product.bot_on`, `product.bot_modded` NOT_APPLICABLE; and an uninstall removing the template, its controls and the instance.
- **C11** [test] `ProductCategoryTest` and `ProductInstallTest` assert the listing holds nine products with `twitch_chat` fourth on the Products shelf and the `product` category count 4.
- **C12** [unverified] Installed through `/products/twitch_chat` on `overlabels.test`, an OBS link created from the product's last step, and the overlay opened in Chrome from a `VITE_CHAT_HOSE=1` build: with generated chat, messages rendered with badge art, Twitch and third-party emote images, per-chatter name colours and the broadcaster stripe. Writing `layout`, `background`, `lifetime`, `font`, `font_size`, `accent`, `show_badges`, `twitch_colors` and `name_color` through `writeValue()` plus `ControlValueUpdated::dispatch()` changed the rendered overlay without a reload, in all three layouts.
- **C13** [unverified] Before C5 the computed `font-family` was the literal `"var(--ol-c-font)"`; before C7 the `top` layout placed the newest message at y = -2360 with fifty messages in the window. Both were observed in Chrome on the pre-fix document and are the reason those two lines read as they do.

### Unchanged
- `RecipeInstaller`, `RecipeCatalog`, `ProductController`, `ProductSetup` and `WiringFacts` are not in the diff: the product is repo content installed through the same `installOverlay()` path `chat_tower` uses, and a product with no integration, list, command or bot already resolves to NOT_APPLICABLE on those wires.
- `useTwitchChat.ts`, `chatSlots.ts`, `ircParser.ts` and `OverlayRenderer.vue` are not in the diff: the overlay uses the `chat` foreach, `msg.*` fields, `badge_images` and `data-key` morphing exactly as `/help/chat` documents them.
- `resources/js/dev/chatHose.ts` and its build gate are not in the diff: the hose was used to verify C12 from a local build and stays out of production builds.
- The chat window cap stays the account's `foreach_caps.chat` preference; the product has no `max_messages` control and the manifest's third note says where the limit lives.

### Risk
None for existing data. The five Google Fonts families load from `fonts.googleapis.com` inside the overlay, the same arrangement `chat_tower`'s overlay uses; a browser source blocked from that host falls back to `Albert Sans`, then the system font.
