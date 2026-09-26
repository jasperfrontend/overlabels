## OL-2609-139 - feat(products): Chat Emote Bubbles, and an emotes loop for every overlay

**Shipped:** 2026-09-26
**Commit:** `git log --grep=OL-2609-139`

### Surface
- `resources/js/utils/emoteSlots.ts` - new: `EmoteOccurrence`, `EmoteUrlResolver`, `emoteOccurrences()`, `emoteHtml()`, `emoteSlots()`, `withEmoteSlots()`, `stableHash()`, `twitchEmoteUrl()`, `EMOTE_SLOT_PREFIX`, `TWITCH_EMOTE_CDN_SIZE`
- `resources/js/utils/emoteSlots.test.ts` - new file, 12 tests
- `resources/js/composables/useTwitchChat.ts` - new `emotes` ref and `setEmoteResolver()`; `EMOTE_WINDOW` (100) exported; `applyModeration()` generic over messages and occurrences; `flush()` fills and trims the emote buffer from the same queue
- `resources/js/composables/useEmoteParser.ts` - keeps the `EmoteFetcher`; new `emoteUrl(token)`
- `resources/js/components/OverlayRenderer.vue` - `templateUsesChat` matches `foreach:emotes` and `emotes.count`; `HTML_SAFE_FOREACH_FIELDS` gains `emotes: ['html']`; a watch on `twitchChat.emotes` calls `withEmoteSlots()`; `setEmoteResolver()` wired to `emoteParser.emoteUrl`
- `resources/js/utils/tagCompletions.ts` - `emotes` iterable, its 14 item fields, and the `!emotes` bang
- `resources/js/utils/tagCompletions.test.ts` - bang list, iterable coverage, and one new field test
- `resources/recipes/chat-emote-bubbles/manifest.json` - new: listed, category `product`, hero, one overlay, no integrations, no bot
- `resources/recipes/chat-emote-bubbles/bubbles.md` - new: the overlay document, ten controls
- `public/products/chat-emote-bubbles-hero.svg` - new, Jasper's artwork with the c2pa manifest and namespace stripped
- `public/llms.txt` - product table row
- `resources/help/reference/foreach-loops/emotes.md` - new reference entry
- `resources/help/pages/chat-emote-bubbles.md` - new guide, section `Bot & chat`
- `resources/help/pages/index.md` - link under Bot & chat
- `resources/help/pages/chat.md` - one See also bullet
- `resources/views/welcome/products.blade.php` - three `$facts` lines for the slug
- `tests/Feature/ProductChatEmoteBubblesTest.php` - new file, 8 tests
- `tests/Feature/ProductCategoryTest.php`, `tests/Feature/ProductBowlingTest.php`, `tests/Feature/ProductTwitchChatTest.php` - shelf positions and counts shifted by one for the new slug
- `CLAUDE.md` - a Chat Emote Bubbles section
- `docs/changelog/changelog-2026-09.md` - prose entry

### Claims
- **C1** [code] `emoteOccurrences()` yields one item per Twitch emote position in `message.emotes` and, when a resolver is given, one per whitespace-split token of the remaining text the resolver answers with a URL; items are numbered `n` from 0 in text order and keyed `${message.id}:${n}`.
- **C2** [code] `stableHash()` is FNV-1a folded to `[0, 100)`; `x`, `y` and `seed` are `stableHash(id, 1|2|3)`, so two builds of the same message produce identical numbers.
- **C3** [test] `emoteSlots.test.ts` asserts C1 (three PogChamps give three items keyed `bomb:0..2`; a Twitch emote needs no resolver; the resolver sees tokens between Twitch emotes) and C2 (identical across rebuilds, different per occurrence, 500 hashes all in range with more than 80 distinct values).
- **C4** [code] `useTwitchChat.flush()` pushes `emoteOccurrences(message, resolveEmote)` for every added message, applies the same `ModerationAction` to both lists, and trims the emote list to `EMOTE_WINDOW` from the front.
- **C5** [code] `applyModeration()` matches `delete_message` on `messageId ?? id`, so a `CLEARMSG` removes a message from `messages` and every occurrence from that message from `emotes`.
- **C6** [code] `useEmoteParser.emoteUrl()` returns the proxy's Twitch URL with `/1.0` replaced by `/2.0`, else the fetcher emote's `toLink()` at size index `min(1, sizes.length - 1)` when the emote has a `sizes` array and `1` otherwise, and null for an unknown token, a modifier, or before `initialize()` has run.
- **C7** [code] `OverlayRenderer.templateUsesChat` is true for a source containing `[[[foreach:emotes as` or `[[[emotes.count`, which is what gates the chat socket and the emote library for a template that only draws emotes.
- **C8** [code] `emotes.N.html` is the only `emotes` field in `HTML_SAFE_FOREACH_FIELDS`, and `emoteHtml()` builds it from `encodeHtml(name)` and `encodeHtml(url)` only.
- **C9** [test] `emoteSlots.test.ts` asserts C8's shape and that a name or url containing quotes and angle brackets is escaped.
- **C10** [code] `bubbles.md` declares ten controls (`look`, `bubble_size`, `bubble_color`, `speed`, `direction`, `max_bubbles`, `pop_after`, `spawn`, `spawn_x`, `spawn_y`) and reads exactly those ten; its CSS holds `.bubble:nth-last-child(-n + [[[c:max_bubbles ?? 40]]]) { display: block; }` and no `if` or `foreach` block; its HTML carries `popping` only under `[[[if:c:pop_after > 0]]]`.
- **C11** [test] `ProductChatEmoteBubblesTest` asserts C10, the shelf position (`products.1`, hero path, install chip `Overlay`), the install creating one static overlay with those ten controls in order and no integration, option set or user-scoped control, `WiringFacts` states, and uninstall removing the overlay and its controls.
- **C12** [test] `tagCompletions.test.ts` asserts the `!emotes` bang is offered, that some bang expands to `[[[foreach:emotes as `, and that `e.html`, `e.url`, `e.name`, `e.n`, `e.x`, `e.y`, `e.seed`, `e.author` complete under the alias `e`.
- **C13** [test] `LlmsTxtProductsTest` passes with the new row, and `ProductCategoryTest` passes with five products on the shelf in slug order `chat-checkin, chat-emote-bubbles, chat-tower, follower-bowling, twitch-chat-overlay`.
- **C14** [unverified] Viewed in Chrome on `overlabels.test` with the `VITE_CHAT_HOSE=1` build: forty bubbles shown out of 93 buffered after a 40-message burst; a bubble's `translateY` kept decreasing across two further bursts on the same DOM node; the heart look at the cannon spawn with `pop_after` 4 reached its edge and popped; the snow look sank from random spawn points. No frame-rate number was taken: the tab was in the background for part of the session and `requestAnimationFrame` did not fire there.
- **C15** [unverified] Not viewed in OBS.

### Unchanged
- The `chat` loop, `chatSlots.ts`, the chat window cap, the chat display filters and the Twitch Chat Overlay product: the emote buffer is fed from the same queue after the same filter, and nothing on the chat side is in the diff.
- `resolveIterable` and the DSL: the loop works because `emotes.N.field` keys land in the data map, exactly as `chat` does.
- The chat designer (`ChatPresets`, `ChatDesigner`, `products/design.vue`): this product has no designer; its settings are the overlay's Controls tab.
- `User::PREFERENCE_DEFAULTS['foreach_caps']`: `emotes` is not a foreach cap. `EMOTE_WINDOW` is a fixed buffer bound and the shown count is the template's `max_bubbles` control.

### Risk
`[[[c:max_bubbles ?? 40]]]` inside a selector sends the overlay's stylesheet to the slow path (whole
CSS re-substituted and the `<style>` swapped on every data change). Verified in Chrome that running
CSS animations survive the swap; not measured under a chat firehose. The hero SVG's text says
"styled 100+ ways", which is the artwork's claim, not the product's: three looks and a colour.
