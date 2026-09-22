## OL-2609-123 - fix(products): cap a ticker chat message at about 200 characters instead of letting it run under the messages behind it

**Shipped:** 2026-09-22
**Commit:** `git log --grep=OL-2609-123`

Twitch allows 500 characters in a message and the chat overlay's ticker layout is one line. A long
message ran off the right edge of its own box and under the four to seven messages behind it. The
ticker body is now clipped with an ellipsis, and a migration puts the same rule into overlays
installed before the recipe had it.

### Surface
- `resources/recipes/twitch-chat-overlay/chat.md` - the `.layout-ticker .msg` rule becomes an inline-flex row aligned on the baseline; a `.layout-ticker .body` rule added with `min-width: 0; max-width: 200ch; overflow: hidden; text-overflow: ellipsis`
- `database/migrations/2026_09_22_160000_cap_ticker_message_length_on_installed_chat_overlays.php` - new file: rewrites the exact old rule in `overlay_templates.css` to the new pair; `down()` reverses it
- `tests/Feature/ChatTickerOverflowTest.php` - new file, 3 tests

### Claims
- **C1** [code] The recipe's `css` block contains `.layout-ticker .body { min-width: 0; max-width: 200ch; overflow: hidden; text-overflow: ellipsis; }` and no longer contains `.layout-ticker .msg { white-space: nowrap; overflow-wrap: normal; }`.
- **C2** [code] `.layout-ticker .msg` declares `display: inline-flex; align-items: baseline; column-gap: 0.25em;` in addition to the `white-space: nowrap; overflow-wrap: normal;` it had. The clip needs the body to be a flex item: an inline body cannot take `max-width` or `overflow`.
- **C2a** [code] The `column-gap` is there because a flex row drops the whitespace text nodes between the spans, and `.skin-bubbles .name::after` is `content: none`, so Bubbles in ticker had nothing left between a name and its message. A quarter em is what a collapsed space measured in inline flow, so the other skins, whose `.name::after` carries its own margin, keep the spacing they had.
- **C2b** [unverified] The first cut of this change lacked the gap; on overlabels.test with the Bubbles skin on ticker every message read as `Chatter2Lorem Ipsum`, name jammed to body. Jasper saw the same.
- **C3** [code] The stacked layouts are untouched: no rule in the diff matches `.layout-bottom` or `.layout-top`, and the base `.msg` rule keeps `overflow-wrap: anywhere`, so a long message still wraps there.
- **C4** [code] The migration matches `overlay_templates.css` with `LIKE '%<old rule>%'` on the verbatim old line and `str_replace`s only that line; a template whose CSS does not contain it byte for byte is not updated.
- **C5** [test] `ChatTickerOverflowTest` "rewrites the old ticker rule on an installed overlay, and leaves any other CSS alone" runs the migration's `up()` against one template holding the old line between two other rules and one holding an edited variant, asserts the first gains the new rules with its other rules byte-identical and the second is unchanged, then runs `down()` and asserts the first is back to its original CSS.
- **C6** [test] The same file's "caps a ticker message body" asserts C1 and C2 against the parsed recipe, including `min-width: 0`, without which a flex item would not shrink below its content width and the cap would do nothing.
- **C7** [code] The migration uses `DB::table('overlay_templates')` with literal names and references no Eloquent model.
- **C8** [unverified] On overlabels.test, in the designer with the layout on ticker, a cloned message given a 500-character body measured 1481 px wide with `scrollWidth` 4922, computed `overflow: hidden` and `text-overflow: ellipsis`, and no two of the fifteen messages in the row overlapped. `200ch` at the 22 px default came to 2583 px, wider than the 1920 px source, so in practice the frame's own width is the cap at default sizes and `200ch` bites at smaller fonts or wider sources.
- **C9** [unverified] `php artisan migrate` on the local database rewrote exactly one template, the account's installed chat overlay, and left zero holding the old rule.

### Unchanged
- `manifest.json` keeps `version` 2. OL-2609-119 changed this same document's head without a bump and repaired installed copies by migration; this follows it. `RecipeInstaller` reads the document from disk at install (`'css' => $doc['css']`), so a new install gets the new rule with no bump either way.
- No frontend file is in the diff. `chatSlots.ts` and `useTwitchChat.ts` still hand the overlay the whole message; the cap is the product overlay's CSS, so a hand-written chat overlay is not affected.

### Risk
A streamer who edited their installed chat overlay's CSS and changed or removed the old ticker
line keeps their version. Their ticker still overflows; the recipe is the place to see the fix.
