## Audit of OL-2609-123 - fix(products): cap a ticker chat message at about 200 characters instead of letting it run under the messages behind it

**Audited:** 2026-09-25
**Commit:** a7de35e08eccf8d25e1fb1eb5daf2ad50f50153d
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/recipes/twitch-chat-overlay/chat.md:101 @a7de35e` - `.layout-ticker .body { min-width: 0; max-width: 200ch; overflow: hidden; text-overflow: ellipsis; }`; the old `.msg` line is gone (line 95 now carries the flex declarations). Same @HEAD. |
| C2 | CONFIRMED (first sentence) | `chat.md:95 @a7de35e` - `.layout-ticker .msg { display: inline-flex; align-items: baseline; column-gap: 0.25em; white-space: nowrap; overflow-wrap: normal; }`; same @HEAD. The second sentence ("an inline body cannot take `max-width` or `overflow`") is a statement about CSS layout, not about the tree - see F3. |
| C2a | CONTRADICTED | `chat.md:149 @a7de35e` - `.skin-bubbles .name::after { content: none; }` is true. "the other skins, whose `.name::after` carries its own margin" is false for two skins: `chat.md:142 @a7de35e` `.skin-terminal .name::after { content: " "; margin: 0; }` and `chat.md:187 @a7de35e` `.skin-cards .name::after { content: none; }`. The "quarter em is what a collapsed space measured" half is a measurement, not checkable in the tree (F3). Same lines @HEAD. |
| C2b | UNVERIFIABLE | tagged [unverified] |
| C3 | CONFIRMED | The only chat.md hunk @a7de35e is lines 95-101, both `.layout-ticker` rules; `.layout-bottom`/`.layout-top` at `chat.md:83-84` are context, not changed; base `.msg` keeps `overflow-wrap: anywhere` at `chat.md:92 @a7de35e`. Same @HEAD. |
| C4 | CONFIRMED | `database/migrations/2026_09_22_160000_cap_ticker_message_length_on_installed_chat_overlays.php:22 @a7de35e` - `OLD_RULE` is the verbatim old line; `:47` `->where('css', 'like', '%'.$from.'%')`; `:52` `str_replace($from, $to, ...)`. Neither `OLD_RULE` nor `NEW_RULES` contains a `%` or `_` LIKE wildcard. Unchanged @HEAD. |
| C5 | CONFIRMED | `tests/Feature/ChatTickerOverflowTest.php:51-74 @a7de35e`: old line between `html {...}` and `.mine {...}` (57), edited variant (59), `up()` then asserts old rule absent (65), `.body` rule present (66), prefix/suffix intact (67-68), edited row unchanged (69); `down()` then `toBe($old)` (73), which also proves `up()` wrote `NEW_RULES` verbatim. `php artisan test --filter=ChatTickerOverflowTest`: 3 passed, 15 assertions. |
| C6 | CONTRADICTED | `ChatTickerOverflowTest.php:28-49 @a7de35e` asserts the `.body` rule has `max-width: 200ch`, `overflow: hidden`, `text-overflow: ellipsis`, `min-width: 0` (35-40), the `.msg` rule has `display: inline-flex` and `column-gap: 0.25em` (46-47), and the old line is gone (48). It does not assert `align-items: baseline`, `white-space: nowrap` or `overflow-wrap: normal` on `.layout-ticker .msg`, three of the five declarations C2 names. Test passes. |
| C7 | CONFIRMED | Migration `:46` and `:51 @a7de35e` use `DB::table('overlay_templates')`; the only imports are `Migration` and `DB` (lines 3-4); no `App\Models` reference. Unchanged @HEAD. |
| C8 | UNVERIFIABLE | tagged [unverified] |
| C9 | UNVERIFIABLE | tagged [unverified] |

### Surface
Complete.

### Findings
- **F1** test narrower than claim - C6 says "caps a ticker message body" asserts C2, but `tests/Feature/ChatTickerOverflowTest.php:46-47 @a7de35e` checks only `display: inline-flex` and `column-gap: 0.25em`, not `align-items: baseline`, `white-space: nowrap` or `overflow-wrap: normal`; either add those three assertions or record the narrower scope in a new claim.
- **F2** contradicted [code] claim - C2a says the skins other than Bubbles get their spacing from a margin on `.name::after`, but `resources/recipes/twitch-chat-overlay/chat.md:142 @a7de35e` gives Terminal `margin: 0` (a `" "` content instead) and `chat.md:187 @a7de35e` gives Cards `content: none`; a new claim should restate which skins depend on the `column-gap`.
- **F3** compound claims with uncheckable halves tagged [code] - C2's second sentence (an inline body cannot take `max-width`/`overflow`, so the body must be a flex item) and C2a's "a quarter em is what a collapsed space measured" are statements about browser layout and a measurement, and nothing in the tree can confirm them; split them out as `[unverified]` claims.

### Notes
- Unchanged lines hold: `resources/recipes/twitch-chat-overlay/manifest.json:6 @a7de35e` is `"version": 2` and not in the diff; `app/Services/Recipes/RecipeInstaller.php:347 @a7de35e` reads `'css' => $doc['css']`; OL-2609-119's diff does not touch `manifest.json`, which matches the precedent cited. No frontend file in the diff.
- No later commit touches the recipe, the migration or the test (`git log a7de35e..HEAD` on those paths is empty).
- The third test, "installs the capped rule fresh", does not install anything. It only parses the recipe and asserts the old line is absent, the same assertion as line 48. No claim relies on it.
- The test uses `DatabaseTransactions`, not the `RefreshDatabase` that CLAUDE.md's Testing line describes. 124 Feature files do the same, so this is not treated as a contradiction.
