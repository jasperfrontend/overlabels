## Audit of OL-2609-089 - feat(products): Twitch Chat, a fourth product that installs one overlay whose look is twelve controls

**Audited:** 2026-09-25
**Commit:** f1871f906bfc83b09cd1e092326024d78d8e2251
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/recipes/twitch_chat/manifest.json @f1871f9` - `listed` true, `category` `product`, `requires_bot` false, `requires_integrations` `[]`, `installs` = `{"overlays": [{"ref": "chat", "file": "chat.md"}]}` only; @HEAD same values at `resources/recipes/twitch-chat-overlay/manifest.json:14-27` (moved by OL-2609-114) |
| C2 | CONFIRMED | `OverlayMarkdown::parse()` (unchanged f1871f9..HEAD) run via tinker on `chat.md @f1871f9` returns 12 controls in the claimed order, types text/text/number/boolean/color/color/color/text/color/number/boolean/number, no `expression`; @HEAD 13 with `skin` first (OL-2609-090) |
| C3 | CONFIRMED | `chat.md @f1871f9` - html:37-41 reads `layout`, `background`, `lifetime`, `show_badges`, `twitch_colors`; css:58-65 reads `font`, `font_size`, `name_color`, `text_color`, `accent`, `background_color`, `lifetime`, `emote_size`; union = the twelve declared keys, nothing else |
| C4 | CONFIRMED | `chat.md:54-144 @f1871f9` - css block has no `[[[if:`, `[[[elseif:`, `[[[foreach:`; bail test is `tagParser.ts:83 @f1871f9` (same @HEAD:83); every css tag is on a `--*` property of `.ol-chat` (lines 58-65) |
| C5 | CONFIRMED | `chat.md:58 @f1871f9` - `--font: [[[c:font]]];` unquoted; `tagParser.ts:141-158 @f1871f9` builds `--ol-` + key with non-alnum to `-`, giving `--ol-c-font`, and `"` is in `CSS_BOUNDARY` (line 79) so a quoted tag is rewritten inside the string; @HEAD `chat.md:56` unchanged |
| C6 | CONFIRMED | `chat.md:37 @f1871f9` - `ol-chat layout-[[[c:layout]]] bg-[[[c:background]]][[[if:c:lifetime > 0]]] fading[[[endif]]]`; `chat.md:134 @f1871f9` is the only rule naming `ol-chat-out`, delay `var(--life)`, fill `forwards`; @HEAD root gains `skin-[[[c:skin]]]` (OL-2609-090) |
| C7 | CONFIRMED | `chat.md:85-87 @f1871f9` - bottom `column`+`flex-end`, top `column-reverse`+`flex-end`, ticker `row`+`flex-end` (plus `align-items: center`); same @HEAD:83-85 |
| C8 | CONFIRMED | `chat.md:39 @f1871f9` - `data-key="[[[msg.id]]]"`; `OverlayRenderer.vue:479-482 @f1871f9` `getMorphNodeKey()` returns `getAttribute('data-key')`, passed as `getNodeKey` at line 496; @HEAD at 554-557 |
| C9 | CONFIRMED | `chat.md:41 @f1871f9` - `style="color: [[[msg.color]]]"` inside `[[[if:c:twitch_colors]]]`; line 40 badge span inside `[[[if:c:show_badges]]]` |
| C10 | CONTRADICTED | `tests/Feature/ProductTwitchChatTest.php @f1871f9` exists; passes @HEAD (8/8). Narrower than claimed: line 66 asserts `product.overlays.0.name` only, not that there is one overlay; lines 79-81 assert the mapped template is static, never a count of templates created; lines 117-119 assert only C4's first half (no if/elseif/foreach), not that every CSS tag is a bare tag on a `.ol-chat` custom property; lines 125-127 assert `fading`, `layout-` and `bg-` strings but not `ol-chat`. Everything else C10 lists is asserted |
| C11 | CONFIRMED | `ProductCategoryTest.php @f1871f9` - `has('products', 9)`, `twitch_chat` fourth in `shelfSlugs`, `categories.0.count` 4; `ProductInstallTest.php @f1871f9` - listed keys include `twitch_chat`, `has('products', 9)`; both pass @HEAD (slugs since renamed by OL-2609-114) |
| C12 | UNVERIFIABLE | tagged [unverified]; browser observation |
| C13 | UNVERIFIABLE | tagged [unverified]; pre-fix tree observation in Chrome |

### Surface
Complete.

### Findings
- **F1** [test] narrower than claimed - `tests/Feature/ProductTwitchChatTest.php:66,79-81,117-119,125-127 @f1871f9` does not assert the product page has exactly one overlay, does not count templates the install creates, does not assert C4's bare-tag-on-`.ol-chat`-property half, and does not assert the `ol-chat` class; either add those assertions or restate C10 in a new claim to what the test covers.

### Notes
- Tests were run @HEAD only (`php artisan test --filter='ProductTwitchChatTest|ProductCategoryTest|ProductInstallTest'`, 39 passed). A worktree @f1871f9 could not boot: its config still loads `Stevebauman\Location\LocationServiceProvider`, removed from vendor by OL-2609-097.
- Drift since ship is disclosed: OL-2609-090 added a thirteenth `skin` control and the `skin-*` root class (C2, C6, C10 counts); OL-2609-114 moved the recipe to `twitch-chat-overlay` and renamed slugs in all three tests; OL-2609-109 changed the manifest `ready_message`.
- OL-2609-090 moves twelve controls to thirteen without citing OL-2609-089 inline; it adds rather than reverses, so it is not raised as a finding.
