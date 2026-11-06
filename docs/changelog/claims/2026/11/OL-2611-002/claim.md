## OL-2611-002 - feat(products): Twitch Chat Plinko, the sixth product

**Shipped:** 2026-11-07
**Commit:** `git log --grep=OL-2611-002`

### Surface
- `resources/js/plinko/plinkoTag.ts` - new file; `PLINKO_TAG`, placeholder pre-pass, `isDropCommand()`
- `resources/js/plinko/plinkoPhysics.ts` - new file; board geometry, `stepBall()`, `collideBalls()`, `slotPoints()`
- `resources/js/plinko/plinkoBoard.ts` - new file; `mountPlinkoBoard()`: canvas, queue, name tags, scoreboard DOM, moderation
- `resources/js/plinko/plinko.test.ts` - new file
- `resources/js/composables/useTwitchChat.ts` - `onMessage()` and `onModeration()` taps
- `resources/js/components/OverlayRenderer.vue` - `templateUsesPlinko`, placeholder pre-pass, lazy board chunk, chat taps, chat connect gate
- `app/Services/TemplateDataMapperService.php` - `plinko_board` in `COMPUTED_TAGS`, mapped to `''`
- `resources/js/components/product-icons/TwitchChatPlinko.vue` - new file; 1-2-3-4-5 dot icon
- `resources/js/components/product-icons/registry.ts` - `twitch-chat-plinko` entry
- `resources/recipes/twitch-chat-plinko/manifest.json` - new file; the listed product
- `resources/recipes/twitch-chat-plinko/plinko.md` - new file; the overlay document
- `public/products/twitch-chat-plinko-hero.svg` - new file; hero source
- `public/products/twitch-chat-plinko-hero.jpg` - new file; hero and share image
- `public/products/twitch-chat-plinko/play-board.svg` - new file; pitch page illustration
- `public/llms.txt` - product table row
- `resources/help/pages/twitch-chat-plinko.md` - new file; help guide
- `resources/help/pages/index.md` - links the guide under Bot & chat
- `CLAUDE.md` - "Twitch Chat Plinko" section
- `tests/Feature/ProductTwitchChatPlinkoTest.php` - new file
- `tests/Feature/ProductCategoryTest.php` - six products, eleven listed
- `tests/Feature/ProductInstallTest.php` - eleven listed, slug list
- `tests/Feature/ProductSlugRenameTest.php` - `twitch-chat-plinko` added to `$bornHyphenated`

### Claims
- **C1** [code] `replacePlinkoTags()` replaces every `[[[plinko_board]]]` with a `div` carrying `data-plinko-board` and no brackets.
- **C2** [code] `isDropCommand()` matches `!<command>` as the first word only, case-insensitive; `!dropped` and `type !drop` do not match.
- **C3** [code] `useTwitchChat` calls every `onMessage()` tap before the feed's filters, skipping only messages whose login is in `filters.hiddenLogins`.
- **C4** [code] `useTwitchChat` calls every `onModeration()` tap for each CLEARMSG and CLEARCHAT action.
- **C5** [code] `OverlayRenderer.vue` joins Twitch chat when `templateUsesChat` or `templateUsesPlinko` is true.
- **C6** [code] `OverlayRenderer.vue` imports `@/plinko/plinkoBoard` dynamically, only when the template HTML contains the tag.
- **C7** [code] `mountPlinkoBoard().drop()` ignores a drop whose login already has a ball queued or in flight.
- **C8** [code] The board releases at most `--plinko-rate` balls per second (default 10) and keeps at most `--plinko-max-balls` in flight (default 150).
- **C9** [code] `moderate()` removes the matching ball or queue entry for `delete_message`, and additionally the chatter's score and recent landings for `purge_user`, `purge_login` and `clear_all`.
- **C10** [code] Scoreboard names are written with `textContent`; the board module sets no `innerHTML`.
- **C11** [code] `readConfig()` reads the `--plinko-*` custom properties once, when the board mounts.
- **C12** [code] `TemplateDataMapperService::mapTwitchDataForTemplates()` returns `plinko_board` as an empty string.
- **C13** [code] The manifest declares `requires_bot` false, category `product`, one overlay and no integrations.
- **C14** [test] `plinko.test.ts` asserts 400 seeded drops all land within 10 s, none leaves the pyramid, more than 40% land within two slots of the middle and under 10% in the four outermost slots combined.
- **C15** [test] `plinko.test.ts` asserts `collideBalls()` separates two overlapping falling balls and leaves a landed ball in place.
- **C16** [test] `ProductTwitchChatPlinkoTest` asserts the install creates one static overlay containing the tag and no controls, integrations or Lists.
- **C17** [test] `ProductTwitchChatPlinkoTest` asserts the overlay head loads Bunny Fonts and not Google.
- **C18** [test] `ProductTwitchChatPlinkoTest` asserts the pitch page renders with the hero and the play illustration.
- **C19** [unverified] In headless Firefox with a stand-in Twitch socket, 20, 200, 2000 and 20000 players dropping at once each held 61 fps.
- **C20** [unverified] In the same setup, a repeated `!drop` did not grow the line, a CLEARCHAT removed that chatter from the line and the scoreboard, and a CLEARMSG kept that chatter from ever getting a ball.

### Unchanged
- `useTwitchChat`'s feed filtering, window and moderation of `messages` and `emotes`: the taps are added before and beside them; those paths are not in the diff.
- `[[[checkin_globe]]]`'s pre-pass and chunk, which the Plinko wiring mirrors in the same renderer, are not changed.
- The bot and its command cooldown: Plinko never goes through the bot, and the bot repo is not touched.

### Risk
None for existing overlays: the board code only loads, and chat only opens, for a template that contains the tag.
