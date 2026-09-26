## Audit of OL-2609-139 - feat(products): Chat Emote Bubbles, and an emotes loop for every overlay

**Audited:** 2026-09-26
**Commit:** e1e73d34 (also HEAD at audit time; shipped tree and HEAD are identical)
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/utils/emoteSlots.ts:95-131 @e1e73d34` - `splitByEmotePositions()` (`useEmoteParser.ts:29-59`) emits one `emote` segment per position; text segments are `split(/\s+/)` and offered to `resolve` only when given; `found.map((..., n) => ({ id: \`${message.id}:${n}\`, n, ... }))`. Same at @HEAD |
| C2 | CONFIRMED | `emoteSlots.ts:77-85 @e1e73d34` - basis `0x811c9dc5 ^ salt`, per char XOR then `Math.imul(h, 0x01000193) >>> 0`, `return h % 100`; `:120-122` `x: stableHash(id, 1), y: stableHash(id, 2), seed: stableHash(id, 3)`. Same at @HEAD. See Notes for the empty-input edge |
| C3 | CONFIRMED | `emoteSlots.test.ts @e1e73d34` - `:30` three PogChamps -> ids `bomb:0..2`, `n` `[0,1,2]`; `:43` Kappa with no resolver; `:50` resolver sees `KEKW` between/after Kappa; `:75` two builds equal, occurrences differ; `:90` 500 hashes in `[0,100)`; `:99` >80 distinct. `npx vitest run resources/js/utils/emoteSlots.test.ts` - 12 passed |
| C4 | CONFIRMED | `resources/js/composables/useTwitchChat.ts:165 @e1e73d34` `nextEmotes.push(...emoteOccurrences(change.message, resolveEmote))`; `:167-168` same `change.action` to both lists; `:177-178` `slice(nextEmotes.length - EMOTE_WINDOW)`; `:73` `EMOTE_WINDOW = 100`. Same at @HEAD |
| C5 | CONFIRMED | `useTwitchChat.ts:137 @e1e73d34` - `(m.messageId ?? m.id) !== action.messageId`; `ChatMessage` has `id` and no `messageId` (`ircParser.ts`), `EmoteOccurrence.messageId = message.id` (`emoteSlots.ts:123`). Same at @HEAD |
| C6 | CONFIRMED | `resources/js/composables/useEmoteParser.ts:242-257 @e1e73d34` - `twitchEmoteMap.get(token)` (filled from `/api/overlay/emotes/` at `:137`) -> `.replace(/\/1\.0$/, '/2.0')`; else `fetcher?.emotes.get(token)`, null on `!emote` or `.modifier`; `size = Array.isArray(sizes) ? Math.min(1, sizes.length - 1) : 1`; `fetcher` is null until `initialize()` (`:72`, set `:105`). Same at @HEAD |
| C7 | CONFIRMED | `resources/js/components/OverlayRenderer.vue:154-159 @e1e73d34` - `/\[\[\[foreach:\s*(chat|emotes)\s+as\s/` and `/\[\[\[(chat|emotes)\.count\b/`; `:1066` gates `scheduleEmoteParser()`, `:1074` gates `twitchChat.connect()`. Same at @HEAD |
| C8 | CONFIRMED | `OverlayRenderer.vue:532 @e1e73d34` - `emotes: ['html']`; `emoteSlots.ts:143-145` - `alt="${encodeHtml(e.name)}" src="${encodeHtml(e.url)}"` plus a literal class. Same at @HEAD |
| C9 | CONTRADICTED | `emoteSlots.test.ts:108 @e1e73d34` asserts the exact `<img>` shape (C8). `:114-119` sets `name: 'a"b<c'` AND `url: 'x"y'` but asserts only on the name (`not.toContain('"b<')`, `toContain('a&quot;b&lt;c')`); no assertion inspects the `src` attribute, and no url containing an angle bracket is tested. Name escaping is asserted; url escaping is not |
| C10 | CONFIRMED | `resources/recipes/chat-emote-bubbles/bubbles.md @e1e73d34` - Controls table `:204-213` lists the ten keys; reads in html `:34,36` and css `:51-56,84` are exactly those ten; `:84` is the `:nth-last-child(-n + [[[c:max_bubbles ?? 40]]]) { display: block; }` line; css `:47-196` has no `[[[if:` or `[[[foreach:`; `popping` appears in the html (`:33-41`) only at `:34` inside `[[[if:c:pop_after > 0]]] popping[[[endif]]]`. Same at @HEAD |
| C11 | CONFIRMED | `tests/Feature/ProductChatEmoteBubblesTest.php @e1e73d34` - `:46` shelf `products.1`, hero, `['Overlay']`; `:73` static, ten keys in order, no `ExternalIntegration`/`OptionSet`/user-scoped control; `:105` read == declared, count 10; `:116` selector line, no `[[[if:`/`[[[foreach:` in css; `:128` popping under the `if`; `:137` `WiringFacts` states; `:150` uninstall. `php artisan test --filter=ProductChatEmoteBubblesTest` - 8 passed |
| C12 | CONFIRMED | `resources/js/utils/tagCompletions.test.ts @e1e73d34` - `:162` `!emotes` in the bang label list; `:167` some template includes `[[[foreach:emotes as `; `:144` `e.html, e.url, e.name, e.n, e.x, e.y, e.seed, e.author`. `npx vitest run resources/js/utils/tagCompletions.test.ts` - 30 passed |
| C13 | CONFIRMED | `php artisan test --filter=LlmsTxtProductsTest` - 4 passed; `--filter=ProductCategoryTest` - 13 passed, `ProductCategoryTest.php:60 @e1e73d34` asserts `product => ['chat-checkin', 'chat-emote-bubbles', 'chat-tower', 'follower-bowling', 'twitch-chat-overlay']` |
| C14 | UNVERIFIABLE | tagged [unverified]; browser observation, not checkable in-repo |
| C15 | UNVERIFIABLE | tagged [unverified] |

### Surface
Complete. 23 paths in `git show --stat e1e73d34`; 22 listed plus the claim file. `public/products/chat-emote-bubbles-hero.svg` contains no `c2pa` (grep count 0 @e1e73d34).

### Findings
- **F1** test narrower than claim - `resources/js/utils/emoteSlots.test.ts:114-119 @e1e73d34` ("escapes the name and the url anyway") gives the occurrence `url: 'x"y'` and then asserts nothing about the rendered `src`; C9's "a name or url containing quotes and angle brackets is escaped" is pinned for the name only. Add an assertion such as `toContain('src="x&quot;y"')` (and an angle bracket in the url) or reword C9 to name only.

### Notes
- Unchanged verified @e1e73d34: none of `chatSlots.ts`, `useConditionalTemplates.ts` (`resolveIterable`), `ChatPresets`/`ChatDesigner`/`products/design.vue`, `resources/recipes/twitch-chat-overlay/`, or `app/Models/User.php` are in the diff.
- C2 edge: `stableHash('', salt)` returns a negative number (`0x811c9dc5 ^ 1` is a signed int32 before any `>>> 0`; `node` gives `-36` for `('', 1)`). Unreachable through `emoteOccurrences()` since ids are always `${message.id}:${n}`, but the exported function is not in `[0, 100)` for every input.
- C11's popping check (`ProductChatEmoteBubblesTest.php:128`) is a `toContain` of the `if` fragment, not an assertion that `popping` appears nowhere else in the html; C10 was confirmed by reading `bubbles.md` directly.
- `CLAUDE.md:749 @HEAD` still says `chat.N.html` is "the ONLY foreach field rendered unescaped"; that line was already stale (`:806` calls `badge_images` the second entry) and this commit adds a third (`emotes.html`) without touching it or naming `HTML_SAFE_FOREACH_FIELDS` in the new section at `:752`.
- `ProductCategoryTest.php:52 @e1e73d34` test name still reads "files the four products" while its body now asserts five.
