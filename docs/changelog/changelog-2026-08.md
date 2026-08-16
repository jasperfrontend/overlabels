# CHANGELOG AUGUST 2026

> Oh, and happy birthday to me. Jasper turns 44 today, and celebrated by finally giving his own repo a licence. 🎂

## August 16th, 2026 - feat(help): tutorials, and an index that starts with what you want

The help index opened with "Why Overlabels" and "Manifesto". Both are worth reading and neither is what someone arrives wanting. People turn up with a goal - *put chat on screen* - and the docs answered with a table of contents organised around our concepts instead of their intent. Its own frontmatter described it as a clusterf\*ck.

`/help` now opens with **I want to...** and four tutorials that each end with something on screen. The concept guides sit under **How it works** below them, and the reference under **Look something up**. Nothing was deleted; it was reordered around intent.

- **Show chat on screen** - the whole feature is one loop, then badges, per-chatter styling, the window size and the display filters.
- **Show my latest follower** - one tag, then the two things that actually matter around it: what to draw when there is no follower yet, and the difference between a value that sits there and an alert that fires.
- **Show my last 5 subscribers** - a foreach list, with gifted subs credited to the person who paid. It leads with the thing people get wrong: there is no `limit 5` in the loop, the count is a foreach cap applied server-side.
- **Show my latest donator, from any source** - the one nobody finds alone. Five donation services each track their own latest donor and none of them is true about your stream. `latest()` plus the automatic `_at` companion every control carries picks the real one. It also warns that the `argmax()` variant is currency-naive, since comparing 50 JPY against 40 EUR will confidently pick wrong.
- **Tutorials needed no new machinery.** They are ordinary pages under `resources/help/pages/tutorials/`, so the route, the `.md` twin, the sitemap and the search index all picked them up from the filesystem. Writing the file was the entire job.
- **Both HelpContext guard tests earned their keep.** The first draft gave all four tutorials `templates.show?type=static`, which `chat.md` already owned - the "no context resolves to more than 3 pages" test caught the link farm forming on its first commit. The panel-copy test caught a heading one character over the 40-char limit. Each tutorial now claims one distinct context, or none.

## August 16th, 2026 - feat(help): one documentation site instead of four

Help was built four different ways. Prose pages under `/help` were an Inertia app with one markdown pipeline; the reference under `/help/reference` was server-rendered Blade with a second, incompatible one; the Alt+R palette bundled a third copy of the reference into the app; and `/updates` rendered markdown in the browser with a fourth. Two of them defined `.help-prose`, and the two definitions were loaded on the same page.

The visible half of that was: no search box on any prose page, a reference that could not use a callout, guides that could not use a copyable tag, and no way to link between the two. The invisible half was worse - every prose page served a shell with no content in it, so the guides were unreadable to anything that was not a browser.

Everything under `/help` is now one Blade layout, one renderer, one stylesheet, one search index. No URL moved.

- **The prose pages joined the reference's rail, not the other way round.** Server-rendered HTML was already the right answer for documentation and the reference already knew it - the prose half was the odd one out. A test now asserts, for every page of every kind, that the entire rendered body appears byte-for-byte in the response. That fails for the whole corpus at once if anything ever puts these back behind a shell. The cost is the app sidebar: `/help` is a document now, not a screen inside the app. In-app links still work through the same 409 + `X-Inertia-Location` hard-load the reference has used since it went server-rendered.
- **`HelpMarkdown` is the one pipeline**, so every feature reaches every document. Guides gained click-to-copy `[[[tag]]]` widgets, the reference gained callouts, TOCs and KaTeX, and `[[wikilinks]]` now resolve across the whole corpus instead of only within the reference. Stage order is load-bearing and commented: wikilinks must run while the triple-bracket tags are still intact, because the regex relies on its lookbehind to stay out of them.
- **Soft breaks stay per-kind, and that is not an oversight.** Reference entries are written one statement per line and need `<br />`; guides are prose wrapped at ~100 columns and would break mid-sentence. One global setting is wrong for one corpus or the other, so the caller decides. Pinned by a test in both directions.
- **The sitemap is derived from the corpus now.** The hand-written list had rotted by fourteen pages - `/help/chat`, `/help/builder`, `/help/blocks`, `/help/lists`, `/help/expressions`, `/help/rendering`, `/help/testing`, `/help/tokens`, `/help/overlays-vs-alerts`, `/help/for-creators`, `/help/for-designers`, `/help/lists-realtime` and both `/help/bot/*` subpages were all missing from it. Writing a page is now the whole job of getting it indexed, and the test that pins this was verified to fail against the old array.
- **One search box, over all three kinds.** It sits in the layout, so it is on every help page rather than only the reference, and results carry a Tutorial/Guide/Reference badge. `public/help-index.json` is the new unified index; `public/help-reference-index.json` is still published byte-identically, because it is a documented public contract linked from the reference page as "BYOF".
- **Tutorials get their groundwork with no new machinery.** A page under `resources/help/pages/tutorials/` is a tutorial at `/help/tutorials/<slug>`, and routing, the `.md` twin, `HelpContext` and the sitemap all pick it up with no new code, exactly as the `bot/` pages already did. The kind is derived from the directory. No table, no controller, no route.
- **`.ov-tag` had been silently unstyled for months.** The reference's own stylesheet coloured it with `hsl(var(--primary))`, but the tokens in `app.css` are already complete `hsl(...)` values, so every one of those declarations resolved to `hsl(hsl(0 0% 9%))` and was dropped as invalid. That same stylesheet was unlayered while `help-prose.css` sits in `@layer components`, so it also beat the good typography wholesale on the reference pages. Deleting it fixed both.
- **Three documents were making a claim that is no longer true.** The help index, `for-machines/markdown-endpoints`, and `llms.txt` all told machine readers that bare help URLs return an app shell and to always append `.md`. That was accurate when written and is not any more. The `.md` form is still the better fetch - it is the source, with no navigation around it - but it is no longer a correctness requirement, and all three now say so.
- **Landing on a deep reference entry no longer opens the sidebar scrolled to the top**, with the highlighted entry hundreds of pixels below the fold. An active entry beats the remembered scroll position, since it is the more specific answer to "where am I".

## August 16th, 2026 - fix(overlay): emote sizing is yours now

Every emote image was being written with its styling baked into the tag: `style="display:inline;vertical-align:middle;height:1.5em;"`, stamped on by us, three times over, in three different places in the code.

Inline styles beat every selector you can write. So `1.5em` was not a default, it was a decision, and the only way past it was `!important`.

The same three declarations now live in the overlay's base stylesheet as a `.overlay-emote` rule, loaded before your CSS. Nothing looks any different, but this works now:

```css
.overlay-emote { height: 1em; }
```

- **1.5em is still the default**, and it earns it: an `em` scales with whatever text the emote sits in, so a feed stays proportional at any overlay size. A pixel value would not.
- Twitch emotes also carry `twitch-emote`, so you can size those separately from 7TV, BTTV and FFZ ones if you want.
- Badges never had this problem - they always shipped class-only. Emotes just predated that decision and nobody went back.
- A test now fails if inline styles reappear on generated markup, because the tempting fix for "my emotes are the wrong size" is to reach for the nearest string template.

## August 16th, 2026 - feat(chat): how many chat messages is now yours to decide

Chat was the only foreach loop with a limit you could not change. Fifty messages, take it or leave it, while Subscribers, Goals, Followers and Followed channels all had a box on the settings page. That was an oversight rather than a decision.

**Chat messages** now sits with the rest of them under Foreach loop limits, same box, same 1 to 50 range. Want a four-line feed? Or one? Set it and reload the overlay.

- **Nothing changes unless you change it.** The default is 50, exactly what it was.
- **It is a real limit, not a trim at the end.** Setting it to four means the overlay keeps four messages, rather than keeping fifty and drawing four of them. Counting your way out of a loop by index was always possible, but that is a workaround, and a workaround is not an interface.
- **It is the only one of the five applied in your browser.** The other four get sliced out of the Twitch data before your overlay ever sees it. Chat cannot work that way, because your overlay reads chat straight from Twitch and nothing passes through us. Same promise to you, different place it happens.
- Lowering it takes effect immediately rather than waiting for the next message to push the old ones out.

## August 16th, 2026 - fix(overlay): 7TV, BTTV and FFZ emotes were broken on production only

Third-party emotes rendered as plain text on overlabels.com while working perfectly on a local machine. Twitch emotes were fine in both. Same code, same browser, same account.

The cause was a single character. The emote library protects its base class like this:

```js
if (new.target.name === Emote.name) throw new Error('Base Emote class cannot be used');
```

That compares class names at runtime. The production minifier renamed the base class to `e` - and all four of its subclasses to `e` as well. So the check meant "is `e` equal to `e`", which it always is, and every single emote threw on construction. Development builds are not minified, so it only ever happened in production.

- **Twitch emotes survived by accident.** They come from position data on the chat message itself and never construct one of the library's objects, so they sailed past the broken guard. That is exactly what made this look so strange: the same message could show a Twitch emote and the literal text "Sadge" side by side.
- **Nothing said a word about it.** The seven emote sources are loaded together and the results were being thrown away, so seven different failures could happen in silence. They are now loaded by name, and anything that fails says which one it was and why. There is also a warning for the case where everything succeeds and still nothing is cached, which looks identical on screen.
- **The library's own build already worked around this** - its author passes `--keep-names` to their minifier. Bundling the library's source through our build quietly opted out of that. We now keep names too, which costs about 18 kB on a chunk that is already loaded lazily.
- **A test now inspects the actual built file**, because no ordinary test could ever have caught this. Every test runs against unminified source, so the entire suite stayed green while production was broken.

## August 16th, 2026 - docs(chat): the chat overlay, written down

Chat went from nothing to a complete feature in a day, and none of it was documented. [Twitch Chat in an Overlay](/help/chat) covers the lot: the foreach loop, every per-message tag, badges and emotes, what happens during a collab, the display filters, and the four chat controls.

It shows up on its own in two places: when you are looking at a static overlay, and on the Chat settings page. Those are the two moments you would want it.

- **It answers the questions the feature raises rather than listing tags.** Why a partner's subscriber badge does not draw during a collab. Why the counters show a smaller number than the feed. Why hiding a chatter is not the same as moderating them. Those are the things that look like bugs until someone explains them.
- **A worked example at the bottom** you can paste in and have a chat feed, badges and all.
- One existing test had to change, and the change made it better. It used "a static overlay has no help page" as its control for *nobody claimed this*. All three overlay types have a page now, so the control became a type that cannot exist - which tests the matching rather than which pages happen to be written.

## August 16th, 2026 - feat(chat): badges, as actual badges

```
[[[foreach:chat as msg]]]
  <span class="badges">[[[msg.badge_images]]]</span>
  <b>[[[msg.author]]]</b>: [[[msg.html]]]
[[[endforeach]]]
```

Chat badges have been available as names since the feed shipped this morning - `broadcaster subscriber`, which is what CSS wants and is genuinely useful. Now the artwork is available too, the same emblems Twitch's own chat draws.

- **The names did not change.** `[[[msg.badges]]]` still gives you `broadcaster subscriber` for styling. The art is a second, separate tag, because a template that wants a coloured border per badge should not be forced to load images to get it.
- **The version matters, and it was being thrown away.** A 3-month and a 12-month subscriber badge are different pictures from the same set, so the parser now keeps `subscriber/12` alongside the bare name rather than discarding the number.
- **A collab partner's subscriber badge is not our subscriber badge.** During a shared chat session, a message duplicated in from another channel carries that channel's badge versions, and the art for their channel-specific badges lives somewhere we never fetched. Rendering our own emblem for it would be worse than rendering nothing: it would state something false about a viewer. So foreign messages get the badges that are true everywhere on Twitch - moderator, VIP, staff, broadcaster - and their channel-specific ones simply do not draw.
- **The art comes from our server, never from chat.** This is the second field ever allowed to render as unescaped HTML, next to the emote-parsed message body, so it earns that the hard way: every image URL comes from the manifest our own server fetched from Twitch and is pinned to Twitch's CDN, the alt text is escaped anyway, and a badge name we do not recognise produces no element at all rather than being pasted into the output. Three tests cover those, and each was checked to fail with the guard removed.
- **Only templates that draw badges pay for them.** Most chat templates want the class names and nothing else; those never fetch the manifest. Same discipline as the emote library earlier today.
- 36px art. Twitch draws badges at 18px, so it stays crisp when OBS scales your source without costing four times the bytes for something the size of a full stop.

## August 16th, 2026 - feat(chat): decide what your chat overlay draws

A new Chat page in settings, with two switches: hide messages starting with `!`, and a list of chatters whose messages your overlay skips.

Both default to off, and that is the actual design decision. Deciding for you that bot commands are clutter, or that a particular chatter is not worth rendering, is not this app's call to make. So neither is a default; they are both a thing you turn on.

- **This is not moderation, and the page says so out loud.** Hiding something here keeps it off your overlay. The message is still in chat, still in the VOD, and every viewer and moderator still sees it. Deletions are the separate mechanism that actually removes things, they are not optional, and your overlay honours them whatever these settings say.
- **The filter runs at the door, not at the window.** A hidden message is dropped as it arrives rather than skipped when drawing. Otherwise a chatter spamming commands would quietly push fifty real messages off the overlay while showing nothing themselves.
- **A hidden chatter is hidden wherever they type from**, including from another channel during a shared chat collab. Any other reading would be a surprise.
- **The hidden list is not on the guest list for anything else.** It rides in the overlay payload because it has to - your overlay talks to Twitch directly, so the server never sees a message and cannot filter one for you. Everywhere else it is left out on purpose, including the ordinary serialisation that carries your locale and loop limits around the app. There is a test that fails if someone adds it.
- **The textarea takes a mess.** Extra blank lines, stray commas, a leading `@`, the same name twice, mixed case: all fine. Throwing a validation error at someone pasting a list when the fix is obvious would just be rude.
- No migration. It lives in the preferences column that already holds your locale and foreach caps.

## August 16th, 2026 - feat(chat): four chat controls, counted server-side

Chat has been renderable in an overlay since this morning, but only as a feed. These are the numbers: `chat_messages_this_stream`, `unique_chatters_this_stream`, `latest_chatter_name` and `latest_chat_message`, usable anywhere a control tag is, including alerts and bot replies.

The overlay is not what counts them. It reads chat directly from Twitch for display and it never phones home, so the numbers come from the bot instead, which was already watching chat for command handling.

- **One summary every 30-60 seconds, not one request per message.** The bot aggregates in memory per channel and POSTs a single summary, so Laravel sees roughly 60-120 requests an hour per channel instead of thousands. The alternative - subscribing to `channel.chat.message` by webhook - would have been one synchronous POST per message into a handler doing six to ten database writes, and a webhook that falls behind risks Twitch disabling that user's *other* subscriptions.
- **The bot sends who talked; the server decides who is new.** The summary carries the distinct logins from that window only. Deduplicating across the whole stream happens here, because the bot is a thin relay with no notion of where a stream begins and would lose the set every time it restarts.
- **The unique-chatter count never counts down.** It is backed by a cached set, and a cache flush mid-stream would otherwise restart it from zero and walk the number backwards live on stream. It holds its peak instead and resumes climbing. The flip side is that the go-live reset has to clear the set as well as the control, or the counter spends the next stream frozen at the last one's total. Both halves have a test that was checked to fail without them.
- **Two of the four reset at go-live and two do not.** The counters promise "this stream" and are on the reset list. The `latest_*` pair are most-recent values, and every equivalent across the five donation services persists across streams - sweeping those into the reset is exactly the bug that took four months to notice last time.
- **The counts are native-only.** Shared Chat messages duplicated in from a collab partner show up in the feed but are not counted, so a collab cannot inflate your numbers. The feed showing more than the counter counts is deliberate.
- **`latest_chat_message` is stored exactly as typed.** Running `strip_tags()` over it looks like the careful thing to do right up until someone types "i <3 you" and the overlay renders "i ", because it eats everything from the `<` onward. Escaping belongs at render time, where it already is.

## August 16th, 2026 - perf(overlay): the emote library waits its turn

The emote library was fetched by every overlay on every load, roughly 77 kB and seven requests to BTTV, 7TV, FFZ and our own proxy. It exists for exactly two alert fields and the chat feed, so most overlays were paying for nothing.

The obvious gate turned out to be the wrong question, which is worth recording because it is an easy mistake to repeat.

**"Is this overlay targeted by an alert?" is not the test.** An alert with no targeting fires on EVERY static overlay - that is the deliberate backward-compatible default from March. So almost every overlay can host alerts, and gating on targeting would have quietly stopped emotes rendering in most people's sub alerts. The real question is whether any alert that can fire here actually renders `event.message.text` or `event.user_input`, which the server can answer exactly because it already assembles that candidate set for the CSS preload.

- **The honest measurement: that gate helps rarely.** Checked against real data before shipping - every single account with alerts had at least one untargeted alert using a message field, so the gate alone would have changed nothing for any of them. It is kept because it is exact and free, and it does help an account with no emote-using alerts at all, but it is not where the win is.
- **The win is deferral, not skipping.** Nothing needs this library at mount. An alert fires minutes or hours into a stream, and a chat message rendered plain for one flush is invisible because the slots rebuild the moment the library is ready. So the load is scheduled for idle time instead of racing the overlay's own first paint, with a 2 s ceiling so an overlay that never goes idle still gets it.
- **Anything that needs it now still gets it now.** The alert path starts the load immediately rather than waiting for idle, and cancels a pending idle callback so the two cannot race.
- **The preload flag is a hint, not a contract.** An alert template edited to use a message field after an overlay mounted is not in `alerts_need_emotes`, so the alert path loads the library itself. That costs one alert its emotes instead of every alert for the rest of the stream.
- **Eight tests pin the gate**, and three of them were verified to fail against the naive targeting-based version - including the exact case that would have broken sub alerts for everyone.

## August 16th, 2026 - feat(chat): Twitch chat in an overlay

```
[[[foreach:chat as msg]]]
  <div class="msg [[[msg.badges]]]">
    <span style="color: [[[msg.color]]]">[[[msg.author]]]</span>
    <span>[[[msg.text]]]</span>
  </div>
[[[endforeach]]]
```

That is the whole feature. Drop it in any static overlay and chat appears.

The overlay connects to Twitch **directly**, over anonymous IRC-over-WebSocket. Nothing is relayed through Overlabels: no ingestion cost, no metering, no added latency, and chat that keeps working while Overlabels is down. Chat is public and an anonymous `justinfan` login needs no credentials, so no secret ever reaches an OBS browser source.

- **No DSL change was needed.** `resolveIterable` already synthesizes an array from flat dotted keys with no registration anywhere, so putting `chat.0.author` and `chat.count` into the data map is enough for `foreach` to work. The feature is a data source, not new syntax.
- **Deleted messages disappear, and that is the part that actually matters.** CLEARMSG removes one message, CLEARCHAT purges a user or clears the room. A slur a moderator removed staying on the overlay would be burned into the stream, the VOD, and every clip of it. Timeouts purge by user id, falling back to login when the id tag is absent - never to "clear everything", because failing open wipes the overlay on every timeout and failing closed leaves the banned chatter on screen.
- **Shared Chat is handled.** A message duplicated in from a collab partner exposes `[[[msg.source_channel]]]`, empty for a native message, so a template can mark it rather than passing a partner's audience off as its own. Their badges come from the channel they typed in, matching Twitch's own UI.
- **The window is 50 messages, applied at most five times a second.** The batching is not a throughput fix - the renderer does a 50-message feed in under half a millisecond now. It is an animation fix: the overlay re-renders by replacing innerHTML, which kills any CSS transition mid-flight, so message-enter animations need the breathing room. 200 ms of latency on chat is invisible.
- **The socket only opens if the template renders chat.** Same discipline as the emote library: an overlay that never shows a message should not hold a WebSocket open for an entire stream.
- **Validated against live traffic, not just fixtures.** Pointed at an 86,000-viewer chat: 73 lines, 73 parsed, 56 messages, and 5 real moderation actions caught. The window correctly stayed at 50 through four timeouts, which is the proof that a targeted purge is not being mistaken for a room clear.
- **Chat text is escaped like any other value**, including the `[[[` defusing added yesterday, so a chatter typing a tag gets a tag rendered as text rather than resolved.

### Emotes

`[[[msg.html]]]` renders the message with Twitch, 7TV, BTTV and FFZ emotes as images. `[[[msg.text]]]` stays available as plain escaped text, so a template picks.

This required a deliberate hole in the escaping, and it is worth knowing exactly how narrow it is.

- **It reuses the alert emote pipeline** rather than growing a second one, so an emote works in chat the moment it works in an alert. Twitch emote positions come from the IRC `emotes` tag, which is more accurate than matching by name: it cannot false-positive on a word that merely contains an emote's name.
- **The exemption is keyed by iterable, not by field name.** `{ chat: ['html'] }` means `msg.html` inside a chat loop and nothing else. A different loop with a field called `html` is still escaped, and there is a test for exactly that.
- **Bracket defusing still applies inside the html field**, and this is the part that keeps yesterday's fix intact. `encodeHtml` never touched `[`, so skipping only the entity-encoding would let a chatter typing `[[[c:kofi:total_received]]]` land literally in the output and be resolved by the substitution pass - the injection hole from #230, reopened through a side door. Emote markup contains no square brackets, so defusing costs it nothing.
- **The safety guarantee lives with the producer.** The emote parser escapes every piece of chatter text before adding any markup, so the only tags in that slot are `<img>` elements this app generated. The type is named `EmoteRenderer` rather than `string` to make that contract something you have to opt into.
- **A caller with no emote pipeline emits no html slot at all**, rather than one that merely claims to be safe.

**Still to come:** badge artwork (badges are names today, which is what CSS wants anyway), the four chat controls, and the filtering settings page.

## August 16th, 2026 - perf(build): every page was downloading a code editor it wasn't showing

Vue had no explicit home in the chunking config, so Rolldown parked it inside the first manual chunk it could find, which happened to be `codemirror`. Every entry needs Vue. So every entry pulled a 678 kB code editor along with it: the overlay, which contains no editor anywhere, and the dashboard's first paint, where the editor lives on lazily-loaded pages that had not been visited yet.

Vue now has its own chunk, and CodeMirror is left to the three components that actually import it.

| critical path, gzipped | before | after | |
|---|---|---|---|
| overlay | 282.4 kB | 90.3 kB | -68% |
| dashboard | 327.8 kB | 135.8 kB | -59% |
| events feed | 339.6 kB | 147.4 kB | -57% |
| map | 393.7 kB | 201.6 kB | -49% |

Roughly 192 kB gzipped off the front of every page in the app, from one config change and no source changes at all.

- **`manualChunks` could not do this, and failed silently.** It is a compatibility shim over Rolldown. Returning a chunk name for a vendor package works, which is why the codemirror, websocket and leaflet groups all landed and looked healthy. Returning one for Vue was ignored outright: no warning, no build error, no chunk. The config read as correct while doing nothing, and the only symptom was a large download. The fix is Rolldown's native `advancedChunks`, and all four groups moved over to it.
- **Group order is load-bearing.** Groups are evaluated in order, so Vue is listed first and claims its modules before the codemirror pattern can absorb them. Reorder them and the bug comes straight back.
- **Verify chunking with a build, never by reading the config.** That is the actual lesson here, and it is now a comment in `vite.config.mts`. This had been quietly true for months.
- **Nothing regressed.** All five entries were measured before and after. The welcome page is unchanged because it never loaded Vue in the first place. CodeMirror is now imported by exactly three chunks: the template code editor, the builder's source sync, and the admin updates editor.
- **The emitted chunk graph has no cycles**, checked across 203 chunks and 1123 import edges. Regrouping chunks is exactly the kind of change that can introduce a circular import and a runtime TDZ error that no test would catch, so it was worth confirming rather than assuming.

## August 16th, 2026 - perf(overlay): the emote library is no longer in everyone's critical path

With `foreach` fixed, the next thing you could feel on an overlay load was the 7TV/BTTV/FFZ emote library. It was a static import, and `manualChunks` only splits codemirror, websocket and leaflet, so it rode along inside the overlay's entry bundle: downloaded, parsed and evaluated before anything painted, on every overlay, every load.

Only two fields are ever emote-parsed, `event.message.text` and `event.user_input`, and both belong to alerts. So an overlay that no alert template even targets was paying the full price for a feature it could never use.

It is a dynamic import now, which makes it its own chunk.

| | before | after |
|---|---|---|
| overlay entry, raw | 382.6 kB | 35.6 kB |
| overlay entry, gzipped | 84.8 kB | 12.2 kB |

That is 72.6 kB gzipped off the critical path of every single overlay load, and the entry is 90% smaller. The library still downloads, as its own 72.8 kB gzipped chunk, but only once something actually asks for emotes.

- **Nothing had to be restructured, because the layering was already right.** It is tempting to read "the static overlay carries 7TV so that alerts can use it" as a mistake. It is not one. Alerts render inside the static overlay's DOM, so that is the only JS context in existence, and emote sets are per-channel rather than per-alert, so fetching once at mount and reusing is exactly what stops a cheer from hitting 7TV every time. The eagerness was the bug, not the placement.
- **Safe to make async because nothing was ever waiting on it.** `OverlayRenderer` calls `initialize()` fire-and-forget with a `.catch()`, and `parseEmotes()` already returned its input untouched while `isReady` was false. The download window just joins the fetch window that was always there.
- **It fails better than it used to.** A chunk that does not load, from a network blip or a stale reference after a deploy, now leaves the parser null and renders emotes as plain text. The same failure used to take the entry bundle, and therefore the entire overlay, down with it.
- **The type import is load-bearing.** `import type { EmoteParser }` is erased at compile time, so it keeps `InstanceType<typeof EmoteParser>` working without pulling the library back into the bundle. Changing it to a value import silently undoes this whole change, with no test failing to tell you.
- **Still eager, still worth doing later:** `initialize()` runs on every mount regardless, firing roughly seven requests (BTTV, 7TV and FFZ global plus channel, and the Twitch proxy). The render response already preloads compiled CSS for every alert template that could fire on this overlay, which is a ready-made signal for whether any of this is needed at all.

## August 16th, 2026 - perf(templates): a foreach copied your whole account, once per item

Every `foreach` iteration started by cloning the entire render payload. Not the item, the whole thing: every control on the account, every list, every Twitch field, copied fresh for each row of the loop.

That made rendering quadratic. The payload grows with the data being looped over, so doubling the item count doubled both the number of copies and the size of each one. A 50-message chat feed spent roughly three quarters of its render time copying keys it never read.

The loop scope is now prototype-linked to the payload instead of copied from it. Lookup walks the chain, which costs nothing to set up, and own properties still shadow outer keys, so an alias that collides with a payload key wins exactly as before.

| items | before | after | |
|---|---|---|---|
| 10 | 0.31 ms | 0.14 ms | 2.3x |
| 25 | 1.68 ms | 0.24 ms | 6.9x |
| 50 | 6.84 ms | 0.48 ms | 14x |
| 100 | 31.9 ms | 0.97 ms | 33x |
| 500 | 525 ms | 5.07 ms | 104x |

- **The curve is linear now.** That is the real result, not any single row. Before, every item you added made every other item more expensive. The 500-item case went from half a second to five milliseconds because the penalty compounded hardest where there was most of it.
- **Payload size stopped mattering.** The control experiment: 25 items rendered with 300 unrelated keys sitting in the payload alongside them used to cost 3.18x the same 25 items rendered lean. It is now 1.02x. Loop cost is a function of what you loop over, not of how much you own.
- **This is not a chat feature.** It lands on every `foreach` that already exists: poll choices, subscriber and follower lists, hype train contributors, list-driven wheels and leaderboards. Anyone iterating anything got faster today, and the users who got the biggest lift are the ones with the most controls, who were paying the largest hidden multiplier.
- **Two lookups had to learn about inheritance.** `resolvePath` checks `in` rather than `hasOwnProperty`, or flat dotted keys like `twitch.stream.is_live` fall through to dot-walking and resolve to nothing against a flat payload. `resolveIterable` uses `for...in` rather than `Object.keys`, or a nested loop cannot see the iterable it is meant to iterate. Both are pinned by tests that fail loudly if reverted.
- **Eleven tests were written first, against the old code.** They pin the scoping contract rather than the implementation: outer keys readable from inside a loop, dotted outer keys, namespaced control keys, `loop.*` counters, scoped-shadows-outer, no leakage past `endforeach`, and outer data surviving a nested loop. All eleven passed before the change and after it, which is the only reason to trust a rewrite of the hottest path in the renderer.
- **`OverlayTemplateController` still merges every control and list into the payload wholesale** (the tag allowlist covers only the Twitch half). That is why the multiplier was your whole account rather than your template. It is now a hardening question rather than a performance one, and it is worth doing on its own: the reasons it is not a one-line change are expression controls reading each other's values, list foreach caps that would silently truncate long lists, and `template_tags` being a stored column that can go stale.

## August 16th, 2026 - chore: the rename's last scaffolding comes down

The Bot Commands rename shipped behind two compatibility shims, because the bot deploys from its own repo and the two sides are never updated in the same instant. Both are now redundant, and this removes the app's half.

- **`POST /api/internal/bot/expressions/fire` is gone**, along with the test that pinned it. The bot has shipped and deployed its switch to `/commands/fire`, so the alias was answering nobody.
- **Verified against the bot's `main` before deleting**, not assumed: it POSTs to `/commands/fire` and its dispatcher accepts `type === 'custom'` only. An alias is only safe to remove once you have looked at what is actually calling it.

That closes the four-step sequence a cross-repo rename needs: the bot learns the new value while still accepting the old, the app switches behind an alias, the bot drops the old value, the app deletes the alias. `CLAUDE.md` now describes it that way rather than as three steps, with this rename as the worked example, because the fourth step is the one that is easy to forget and leaves dead surface area behind forever.

It also records a trap found on the bot side: a local dispatcher in `bot.js` must never share a name with the API function it imports, or the inner call resolves to itself and recurses forever on every fire. `node --check` caught it; the bot repo has no test or lint script, so that is the only net there is.

## August 15th, 2026 - refactor: "expression" meant six things, and now it means one

A joke made in passing, "oh you mean bot expressions, expression controls and control's expressions?", turned out to be an accurate census. The word was doing six jobs: jsep formulas in Expression Controls, user-authored chat commands, the pipe formatter, the string an alert speaks, the string an alert posts to chat, and a `bot_commands` table that held something else entirely.

It was not only ugly. It was already producing wrong links in shipped documentation. Four places said "Bot Expressions" and pointed at `/help/expressions`, which is the Haversine-distance math page. Someone reading about aliases and clicking through to learn about chat commands landed on trigonometry.

**The user-authored chat commands are Bot Commands now**, which is what the database has said all along: the trigger column is `command`, the chat verb is `!ol cmd add`, and every other bot in the ecosystem calls them commands. The July rename to "Expressions" was copy-only and the code never followed it.

- **Three renames, because the name was occupied.** `bot_expressions` becomes `bot_commands`; the table that held it, a per-user registry of which built-in verbs are on and at what tier, becomes `bot_builtins`. The wire had already made this call: the command map has emitted `type: "builtin"` for those rows since the day it was written.
- **Both table renames run in one migration** because they are a swap and one name has to be vacated before the other can take it. Postgres does DDL transactionally, so it is atomic.
- **Index and constraint names move too.** They do not follow a table rename in Postgres and they share one schema-wide namespace, so leaving them would have meant a future `unique(['user_id', 'command'])` on `bot_commands` colliding with the identically named constraint still sitting on `bot_builtins`.
- **`expression` became `reply`**, which is what that column holds: the templated text the bot speaks. `hidden_from_commands` became `hidden`, since on a table now called `bot_commands` the old name read circular. Aliases carry the same toggle and the docs promise the two vocabularies match one for one, so it moved with them.
- **`tts_expression` and `bot_message_expression` became `tts_message` and `chat_message`.** Neither was ever a formula. Both hold a templated string an alert renders and sends somewhere.
- **`ExpressionFormatter` became `PipeFormatter`** and moved to `App\Services\Messages` alongside `AlertMessageRenderer`. It formats `[[[tag|round:2]]]`, has nothing to do with jsep, and was the last thing standing between this change and the sentence being true.
- **"Expression" now means a jsep formula in an Expression Control. Nothing else, anywhere.**

**The bot deploys from its own repo, so this ships in three steps.** The command map's `type` discriminator goes from `expression` to `custom`, and the fire endpoint moves from `/expressions/fire` to `/commands/fire`. Both are cross-repo contracts, and the two sides deploy independently.

- **The bot ships first**, accepting `custom` and `expression` alike. If that branch is not live before the app starts emitting the new name, every custom command in every channel goes quiet in the gap.
- **The old fire path stays as a deprecated alias** so the URL switch can happen on the bot's own schedule instead of during the same deploy window. There is a test pinning it, to be deleted alongside the route.
- **`/help/bot/expressions` 301s to `/help/bot/commands`**, the `.md` variant included, because llms.txt named that exact URL and crawlers hold it.
- **`expressions` stays as a search keyword in the command palette.** A retired name is exactly the thing search should still answer to.

Migrations and past changelogs were left alone. They are the record of what was true when they ran, and rewriting them would have produced a history that never happened. The one design doc that got swept up was reverted for the same reason.

## August 15th, 2026 - fix(templates): a tag typed in chat could resolve inside a foreach

Overlay data is not trustworthy, and the rule that keeps that survivable is old and well documented: a value the renderer substitutes is data, and is never re-read as template source. `tagParser.ts` has carried that note since day one, calling out donor names and chat messages containing `[[[c:foo]]]` as exactly the thing it exists to stop.

It held for the pass it was written about, and not for the boundary between two passes. `renderTemplateSource` resolves conditional and foreach blocks first, substituting each loop item's values into the loop body, and then runs the single tag-substitution pass over the whole result. Anything a foreach wrote in the first step was, by the second step, indistinguishable from something the author had typed.

So `!enter [[[c:kofi:total_received]]]` in chat, in any channel whose overlay iterates a chat-writable list, put a live tag into the template. `ListAppendService` stores chatter text verbatim, and `encodeHtml` escapes `& < > " '` but has no reason to touch `[`.

The reach was worse than "a template leaks a tag it already uses". `OverlayTemplateController` allowlists the Twitch data and then merges controls and lists in wholesale, so the payload sitting in the renderer holds every control on the account: all five donation services' running totals, the latest donor name, amount and message, every private counter, and the contents of every list. None of it needs to be mentioned in the template to be readable.

- **The fix already existed twenty lines up.** `[[[raw]]]` has always entity-escaped its brackets, with a comment explaining that a stray `[[[...]]]` in the data must not re-enter the outer pass. That is the same defusing, applied to the same pass, for the same reason. It just was not on the value path. It is now a named helper both branches call.
- **Defusing is unconditional, not "only when the value already looks like a tag."** One attacker usually controls several fields of the same item, and `[` in one plus `[[c:x]]]` in the next concatenates into a live tag while neither half looks dangerous alone. There is a test for exactly that split.
- **Entities, not stripping, so nothing is silently eaten.** A browser renders `&#91;` as `[`, so `[AFK] brb` still reads as `[AFK] brb`. The CSS sink gets the same treatment, because it runs the same two passes with only the HTML encoding turned off, and an inert entity in a stylesheet beats a resolved tag.
- **This was disclosure, not script execution.** HTML escaping was never the thing that failed, and a test pins that it still is not.
- **The server render path was never affected.** PHP's `extractForeachTags` expands a loop only to discover which data keys to ship; it never substitutes. This was the browser's half of the pipeline alone.
- **Vitest is here now, and this is why.** Pest cannot reach any of it. The template pipeline is pure TypeScript, it is what decides what a stranger's string is allowed to do inside an overlay, and it had no automated coverage at all. Eleven tests, six of which were watched failing before the fix went in, and a step in `tests.yml` next to typecheck so they run on every PR. Scope is deliberately the pure logic - the DSL, the tag parser, the renderer - which is why there is no jsdom and no component test.

## August 15th, 2026 - fix(bot): a chat reply that missed its moment is dropped, not posted late

If the bot was offline for six hours, every message queued in that time went out the instant it came back. A `!wins` answer, a sub thank-you and a gamejam round result, all landing at once, all replying to conversations that ended hours ago. Chat replies are the most perishable thing in the app: outside a window of seconds they are not just worthless, they are actively worse than silence, because a bot answering a question nobody remembers asking reads as broken.

The claim path now drops anything older than 60 seconds instead of delivering it.

- **The cutoff has to live at claim time, not in the prune.** A daily sweep cannot help: the bot reconnects and claims its backlog long before the sweep next runs. `BotOutboxController` is the moment of decision, so that is where the decision belongs.
- **60 seconds is 30x the 2 second poll** (and the push usually delivers inside a second), so ordinary jitter can never trip it. It is also long enough to survive a container swap - a deploy should not silently eat replies queued while the bot restarts, and there is a test pinning exactly that.
- **Dropped rows are marked, not deleted.** `discarded_at` makes "did the bot eat my message" answerable for as long as the prune keeps the row, and makes a run of discards legible as what it is: the bot flapping. Deleting them would have been simpler and would have thrown away the only signal that this is happening at all. The count and the age of the oldest are logged on every discard.
- **Fresh and stale are split inside one transaction over one locked row set**, so a message can never be both delivered and discarded, and nothing is left pending for the next poll to find.
- **The prune got simpler, not more complex.** It now sweeps on `created_at` regardless of state. Unsent rows used to be exempt because an unclaimed row was still owed to a channel - that stopped being true the moment the claim path started discarding stale ones, so the exemption became dead weight.
- **Four tests were verified to fail** with the cutoff disabled, including the one that matters: six hours of backlog, claimed, and nothing handed to the bot.

## August 15th, 2026 - feat(bot): chat replies go out on a push, and the outbox stops growing forever

Two things, both downstream of getting Reverb actually working this morning.

**Replies are pushed, not waited for.** The bot polls `bot_chat_outbox` every 2 seconds, so a reply waited an average of one second and up to two before anyone saw it. The app now nudges the bot the instant a message is queued, over the same `bot-channels` connection it already holds open for command-map refreshes.

- **The 2 second poll stays exactly as it is.** It is not being lengthened to "claim" the win. `REVERB_HOST` spent months pointed at the wrong host and the polls are the only reason nobody noticed - a push is a latency optimisation, and the poll is what keeps a missed one costing seconds instead of a silent bot. Shortening the poll to 1s was considered and rejected: it doubles the request rate forever to halve an average wait that the push removes entirely.
- **The event deliberately carries no payload.** The bot claims every pending row in one atomic transaction, so the only thing it needs to know is that something is there. Shipping the message itself would put chat text on a public channel and need per-user routing, for a fetch that happens anyway.
- **One nudge per request, however many messages.** A gamejam round writes several rows in a loop; the bot drains all of them in one claim, so the rest would be broadcasts spent on nothing. Stamping `sent_at` does not nudge either - that is the bot taking a message, and announcing it would be a broadcast per delivery, forever.
- **`BotCommandMapAnnouncer` became `BotPushAnnouncer`** with two methods rather than growing a second class with a copy-pasted dedupe bag. One scoped instance now covers both nudges.
- **Verified end to end against a real Reverb**, not just asserted in isolation: the app dispatched both events, Reverb relayed them, and a real bot listener ran both handlers. The unit tests prove dispatch; only a round trip proves delivery, which is the exact gap that let this morning's bug through.

**`bot_chat_outbox` is pruned.** It was never swept - there are prune schedules for access logs, Twitch events, external events, overlay reports and list snapshots, and the outbox simply got missed. Delivered rows accumulated from the day the table shipped.

- **Delivered messages are deleted after 7 days.** The outbox is a queue, not a log: once the bot has posted a row, its only remaining value is answering "why did the bot say that" for a few days. Nothing else reads it.
- **Only rows the bot actually claimed are swept.** An unsent row is still owed to a channel, whatever its age, so deleting one would silently drop a message the bot was going to post.
- **This was never a performance problem**, which is why it hid. The table is indexed on `(sent_at, id)`, so the every-2-seconds poll costs time proportional to *pending* rows - almost always zero - regardless of how many delivered rows sit behind them. It was growing disk, not latency.

## August 15th, 2026 - fix(bot): a command you just made now works immediately

`!ol cmd add wins So far, Jasper has won [[[counter:wins]]] times` replied `added !wins`, and then `!wins` did nothing at all. Not an error, not a hint - silence. A minute later it worked perfectly.

The bot keeps a map of which commands exist in which channel and refreshes it on a 60 second poll. A command it has never heard of is dropped without a word, which is correct behaviour: chat is full of other bots' commands and replying to all of them would be intolerable. There is also an instant push path over Reverb, and it worked fine - but the app only ever raised `bot.channels.changed` when a user toggled the bot on or off. Creating a command, from the dashboard or from chat, told the bot nothing.

So this was never about the new tags. It has been true for every expression and alias since `!ol` shipped. What changed is that "author a command from chat in one line" invites you to try it one second later, so a latency nobody noticed became the first thing you meet.

- **An observer on all six models the command map is built from**, not a `dispatch()` at each save site. Those sites are spread across the settings controllers, the `!ol` chat-admin service, recipe installation and list management, and missing one would bring the bug back for a single command type - the kind of thing that hides for months. `saved` covers renames and disables too, which move the map just as much as a new row.
- **A test derives the model list from `BotCommandController`'s own imports** rather than a second hardcoded copy, so a seventh command type fails the suite until it is observed as well. Every behavioural test in the file was confirmed to fail with the observer registration removed.
- **Announcements are coalesced per request.** `BotChannelsChanged` is `ShouldBroadcastNow` and broadcasts are the metered resource here; signing up seeds seventeen-odd `BotCommand` rows in a loop, which would otherwise be seventeen synchronous broadcasts for one click. The bot re-reads the whole map either way, so the second broadcast carries nothing the first did not.
- **The announcer is bound with `scoped()`, not `singleton()`** - caught by a test that failed for what looked like a fixture problem. A queue worker boots the container once and holds plain singletons for the life of the process, so the dedupe list would never have emptied and every job after the first would have silently skipped announcing.
- **Nothing is announced for a user who has not turned the bot on.** The map only lists opted-in users, so a signup with the bot off changes nothing the bot can see. That is the difference between one wasted broadcast per registration and none, and turning the bot on is already announced by the settings controller.
- **No change to the bot itself.** The Reverb listener, the refresh and the 60 second poll were all already there; only the app end was missing. `syncChannels` diffs against the current subscription set, so reusing the existing event costs nothing when only a command changed. The poll stays as the fallback for when Reverb is unreachable.
- **Two pre-existing gamejam tests were faking the bus before creating their fixtures**, so seeding a bot-enabled user's commands now counted against `assertNothingDispatched`. Moved the fake after the setup, which is what they meant.

## August 15th, 2026 - feat(bot): random rolls and counters, in the command you're writing

`!ol cmd add steven your Steven Level is [[[rand:0-69]]]%! Kappa.` and `!ol cmd add wins So far, Jasper has won [[[counter:wins]]] times`. Two new bot-only tags, both authored entirely from chat, neither needing a trip to the dashboard first.

The parts were mostly already here and hard to find. `counter` has been a control type since February and `!increment` has been a built-in for as long; random has been a control mode with `config.random` plus a min and max. What was missing was any way to *create* one without opening the web UI, and any way to bump one *as part of the command firing* rather than as a separate moderator command. Counting a thing meant routing chat into a List and reading `[[[c:list:donors:count]]]` back out, which works and which nobody should have to think of.

- **`rand:` is a pure read, `counter:` is a read with a declared effect - and the effect does not live in the tag.** `BotCounterService::bump()` runs once in `BotExpressionService::fire()`, before the resolver is called, so `BotExpressionResolver` stays exactly as read-only as it was. That split is the whole design: the builder's live preview and the validator both resolve without firing, so neither can move a number while you type. Two tests pin it and were confirmed to fail when the increment is moved into `lookup()`.
- **Writing `[[[counter:wins]]]` twice in one message still counts one win.** The bump is driven by the deduplicated key list, not by tag occurrences - the tag declares *that this command counts*, it is not an instruction that runs per tag. Also verified to fail with the dedup removed.
- **A counter IS an ordinary user-scoped Control**, provisioned on save and again on fire, never `source_managed` - because `setValue()` and `update()` 403 on those, which would have put the counter out of reach of the `!set` / `!increment` / `!reset` built-ins. So the same row is readable from every overlay as `[[[c:wins]]]`, correctable with `!set wins 40`, and it broadcasts on every bump. Chat and the on-screen graphic move in the same instant, which is the thing a chat-only counter cannot do.
- **`counter:` counts, `c:` only looks.** Same lookup, one of them declares an increment. That is the entire mental model, and it means "show the total without adding to it" needs no new syntax.
- **Zero lexical change to the DSL.** `rand:0-69` and `counter:wins` were already valid tag keys - digits, hyphens and colons are all in the shared spec's `keyRest` class - so both parse under the existing regex with pipes and `??` defaults working for free. `[[[rand:0-1000000|number]]]` renders `847,215` because `rand:` is an ordinary tag rather than a special form. Verified against `Dsl::tagPattern()` rather than reasoned about.
- **Both namespaces are bot-only, and the divergence is declared in `resources/dsl/dsl.json` rather than left to drift.** `namespaces` entries now take an optional `scope`; absent still means every runtime. An overlay re-renders on every event, so a random tag there would reroll unpredictably - that is what `config.random` plus `random_interval` is for - and a render is not a fire, so an effect-carrying tag has no meaning in one.
- **Negative rand ranges are refused at save time, not resolved to nothing.** `[[[rand:-5-5]]]` has three readings and nobody rolls a negative Steven Level. Malformed ranges and unusable counter keys both fail `!ol cmd add` with a one-line reason, because the alternative is a command that quietly goes blank mid-stream. A counter tag pointing at an existing text control is refused too.
- **A near miss is caught while you write it, not live in front of an audience.** Four mistakes used to save cleanly and then fail quietly: `[[[rnd:0-69]]]` and `[[[countr:wins]]]` fell through to the bare-tag branch and resolved to empty, so the number simply vanished mid-sentence; `[[[counter wins]]]` and `[[rand:0-69]]` never matched the tag pattern at all and went out to chat character for character. Worst case, the whole message resolved to nothing and the bot said nothing, which reads as a dead command. All four are now refused at save time with a message that names the actual mistake and, where there is an obvious one, the fix: *"There's no 'rnd' tag, so nothing would show up where you put [[[rnd:0-69]]]. Did you mean 'rand'?"*
- **Keying the namespace check on the colon is safe, and was checked rather than assumed:** of the 68 bare Twitch tags, none contains one. So a colon means the author was reaching for a namespace, and an unrecognised one is always a mistake rather than a value that happens to be empty. The bracket-run check falls out of the same idea from the other side - strip everything that parses, and anything still holding brackets is by definition something the resolver would have left for chat to display.
- **The under-bracket check only fires on something that really is a tag**, so `[[shrug]]` and `[[citation needed]]` still save. Pinned by a test, because the cheap version of this check would have made ordinary bracketed prose unsavable.
- **This also catches block syntax in a bot expression.** `[[[if:...]]]` and `[[[foreach:...]]]` work in overlays; the bot resolver does no block processing, so they were going out as literal text. Pre-existing, same class, free to fix here.
- **Bare tag names are deliberately NOT checked.** `[[[chanel_title]]]` still saves, because a tag that is legitimately empty right now looks identical to a misspelled one, and refusing those would block a pile of perfectly good commands. The help page says so and points at `Alt+R`.
- **49 tests**, covering the purity split, the dedup, per-occurrence rolls, cross-user and service-managed control scoping, idempotent provisioning, and every one of the near-miss messages. `!ol help tags` explains both tags in chat.
- **A help page of its own, at `/help/bot/random-and-counters`.** The Bot Expressions page already opens with a warning that it is overwhelming, so adding two more tags to it would have made the page worse and buried the feature. That page now carries a short pointer instead, and the beacon offers the new page on the Bot Expressions settings screen. It deliberately does not also claim `settings.controls` - that route was already at the three-page cap, and the page is about writing commands.

## August 15th, 2026 - fix: the Allowed IPs field says what it accepts, and says why it refused

Chasing the README's bogus "IP or CIDR range" claim into the token UI found that the UI never made the claim - but it also never said ranges were unsupported, and refused them with a message that named no field.

- **The claim was only ever in the README.** The create-token form says "Comma-separated IP addresses" with an exact-address placeholder, and `allowed_ips.*` validates with Laravel's `ip` rule, which rejects `203.0.113.0/24`. Nothing user-facing needed correcting. Verified rather than assumed, because the interesting outcome here was "there is no bug".
- **The real defect was next to it: `createToken()` swallowed the 422.** Every failure became `alert('Failed to create token')`, so a user who typed a range got no field, no reason, and no way to guess. It now surfaces the validation messages, rewriting Laravel's `allowed_ips.0` into "Allowed IPs" - accurate is not the same as useful when the reader is a streamer, not a developer.
- **The hint text now rules ranges out up front**, and adds the sentence the field was missing: leave it empty unless your connection has a fixed IP. Most home connections do not.
- **My own help page had it wrong too, one day old.** `/help/tokens` warned that a range "will match nothing at all and lock your own overlay out". It cannot: validation refuses it at save time. Downgraded from `[!WARNING]` to `[!NOTE]` and corrected - overstating a danger that the code already prevents is its own kind of inaccurate.
- **`OverlayTokenAllowedIpsTest` pins all of it (4 tests).** The CIDR rejection was verified to fail by loosening the rule to `string`, then restored. It also pins the exact-match behaviour against a neighbouring address inside the `/24` a user might expect to work, and pins that the allowlist is skipped entirely when no client IP is passed - which is why the help page calls this a fixed-IP convenience and not a security boundary. Loosening the validation to accept ranges without teaching `isValid()` to understand one now breaks the suite instead of the user's overlay.

## August 15th, 2026 - docs: three help pages for what the README used to be the only home for

Slimming the README yesterday left four topics with no page to link to. Three now have one: [How an overlay renders](/help/rendering), [Testing your alerts](/help/testing), and [Overlay Access Tokens](/help/tokens). Onboarding stays cut on purpose.

- **Writing them against the code rather than the old README caught two things the README had wrong.** It claimed overlay tokens could be bound to "a specific IP or CIDR range" - `OverlayAccessToken::isValid()` does `in_array($clientIp, $this->allowed_ips)`, an exact string match, so `203.0.113.0/24` matches nothing and would lock the user out of their own overlay. It also described the `/testing` commands as "blurred by default and revealed on hover"; they are behind a "Show command" checkbox, and clicking a row copies to the clipboard. Both are now documented as built, and the CIDR line is a `[!WARNING]` because getting it wrong is silent.
- **Onboarding was cut rather than written up, on Jasper's call.** The pipeline still copies a starter kit, which is good, but the experience around it reads as a one-shot ceremony you must not get wrong - and the friction it was mitigating has since been solved in context by the "Add to OBS" button on `templates/show` and `templates/edit`. A help page describing the current flow would preserve something that wants replacing.
- **`rendering.md` deliberately declares no `context:`.** The obvious claim is `templates.show`, and `HelpContextTest` asserts that `templates.show?type=static` resolves to **nothing** - a bare `templates.show` would match it and fail that test. `templates.edit` was the other candidate and already carries two pages, so claiming it would sit exactly on the three-page cap and hand the failure to whoever writes the next page. It joins `manifesto`, `math` and `for-creators` as a page you go to rather than one that finds you.
- **The other two claim a context each, both with headroom.** `tokens.md` takes `tokens.index`, joining `lists-realtime` at two of three. `testing.md` takes `testing.index`, which no page had claimed.
- **The index entries are not optional.** `HelpPageTest` has a test that every markdown page is reachable from `/help`, so a new page without an index line fails the suite rather than quietly existing at a URL nobody links to.
- **Every internal link was resolved against the router, and Prettier was run before committing.** Seven internal links across the three pages, all checked against `route:list`. `npm run format:check` covers `resources/`, which is where help pages live, and Prettier formats code inside markdown fences - so a new page with a misindented fence is a CI failure now, not a cosmetic nit.

## August 14th, 2026 - docs: the README stops competing with the help pages

767 lines down to 216, 4192 words down to 1093. The README had grown a second copy of the documentation - tag tables, control types, conditional operators, a section per donation integration, the full render pipeline - and every one of those had a better version at `overlabels.com/help` that is indexable, searchable and actually in sync with what is deployed. The README now points at them instead of paraphrasing them badly.

- **The help pages are the SEO surface, and a duplicate in a repo README competes with them for nothing.** Every page under `/help` renders as indexable HTML with its own `title`, `description` and `canonical`. The README version had no canonical, no search, and drifted the moment a feature shipped - it had grown far enough to contradict itself, stating on line 339 that connecting a service provisions all its controls automatically and on line 369 that Ko-fi's are added from presets by hand. The first is what `DonationIntegrationController` actually does; the second is what it did before the August consolidation.
- **All 19 help links were verified against the router, not eyeballed.** `HelpPage::all()` registers one route per markdown file, so a renamed page silently 404s a hardcoded link. Every URL in the new README was checked against `route:list --path=help` output, plus `public/llms.txt`, which is a static file and therefore does not appear in the route list at all.
- **What stayed is what a GitHub visitor cannot get from the website.** Self-hosting and its env vars, the tech stack, the licence and its relicensing history, contributing, and the sustainability answer. Those are repo questions, not overlay-authoring questions.
- **Limits got compressed rather than dropped, because two of them are safety-relevant.** The script/iframe/embed stripping and the token model are the two things a security-minded reader looks for first, so they survive as four lines under `## Limits` instead of two full sections.
- **Four topics had no help page to link to.** The render pipeline, onboarding, the `/testing` page, and token security in full. Three of them got one the next day - see the entry above. Onboarding deliberately did not.
- **The AGPL section keeps the relicensing paragraph and drops the boilerplate copyright block.** The history of the never-granted MIT line matters and is not written down anywhere else. The GPL warranty notice is in `LICENSE`, where it is legally operative, and repeating it in the README added 15 lines of text nobody reads.

## August 14th, 2026 - feat: the event binding on an alert now has a shape

`/templates?type=alert` showed which event fires each alert as a bare run of coloured text under the description. It read like an unstyled hyperlink, and colour was doing the identifying on its own. It now carries the same provider icon the events feed has used since July - and so do the alert's own show and edit pages, which never named the bound service at all.

- **This is the pairing `EventsTable.vue` already ships, applied to the three other surfaces that answer "what event is this".** Shape carries the source, colour reinforces the event type. No new component, no new abstraction - `ProviderIcon` and `useEventColors` both existed, they just were not used together here.
- **Neither the show page nor the edit page told you which service an alert was assigned to.** Not an icon, not a word of text: the only way to find out was to open the Triggers tab and read the toggles. Both headers now read `Ko-fi Alert / Public / Ko-fi Donation`, icon and label sharing the event's colour.
- **Neither needed a backend change.** The `triggers` prop already carries `assigned.twitch[]` and `assigned.external[]` with the event type and service on them, so nothing new is queried or shipped.
- **`Heading.vue` gained `icon` and `afterTitle` slots, and the title row is deliberately not always a flex container.** With neither slot filled it is a bare `<div>` around the `<h2>`, rendering identically to what the 20-odd existing callers already had. `templates/show.vue` drops its hand-rolled `<h2>` + badge and goes through `Heading` like every other page.
- **The shared derivation had to move to a plain `<script>` block.** `firstAssignedEvent()` belongs beside the `TriggerData` interface it reads, but `<script setup>` permits *type* exports and not runtime ones. `vue-tsc` passes either way - it is a template-compiler rule, not a type error - so this only shows up as a blank page. The two-block SFC is the documented fix.
- **All three surfaces inherit the same visibility rule for free.** `buildTriggerData()` is owner-only and alert-only, so `triggers` is null for a static overlay and for anyone viewing someone else's template. That is the same scoping the list has, where mappings are eager-loaded `where('user_id', ...)` - you see your own bindings, everywhere.
- **`eventLabel()` moved from `TemplateCollection.vue` into `useEventColors.ts`.** Three callers now need it, and it belongs beside `EVENT_TYPE_LABELS` rather than in one of the components that renders it. Net removal of scattered logic, not a new layer.
- **`firstEvent()` now returns `source` and `service` as separate fields, and the split is load-bearing.** `source` is always set, including `'twitch'`, because the icon and the colour need it. `service` is set for external bindings only, because it drives the label prefix - reusing `source` there would render "Follow" as "Twitch Follow".
- **That fixes a fallback that could never fire.** Twitch bindings previously carried no source at all, so an event type missing from `EVENT_STYLES` could not fall back to Twitch purple - it went straight to slate. The source fallback in `resolveStyleByType()` was unreachable for every Twitch event.
- **Six Twitch event types were named in `EVENT_TYPE_LABELS` but had no colour.** All three `channel.hype_train.*` (now orange) and all three `channel.goal.*` (now blue). Both hues were unused; one label renders per row, so the only real requirement is that they are clear of the other eight.
- **`SOURCE_STYLES` was two services short of `SERVICE_LABELS`.** Throne and GPS fell through to grey. Both are bindable - Throne exposes `donation`, GPS exposes four event types including `location_update` - so this was reachable, not theoretical. They use Tailwind names rather than invented brand hexes, and a comment now says the two maps have to stay in step.
- **GPS had no provider icon either, so it drew the fallback block.** Added as `0x6F22`, a map pin, following the procedure in `providerIcons.ts`: 8 filled cells, and a minimum Hamming distance of 6 against all six existing icons *and* the fallback, brute-forced rather than eyeballed. The "Seven distinct gestalts" comment above the map is finally true.
- **The list stays a three-line row.** Moving the binding up beside the template name was considered and rejected: names here get long, and the third line survives a narrow screen where a second column would not.

## August 14th, 2026 - ci: the linter now actually says no

Third and last step of the formatting chore. `lint.yml` ran Pint, Prettier and ESLint in write mode for thirteen months: it fixed the code, exited 0, and threw the result away when the runner was destroyed. All three now run in check mode, so the job reports instead of pretending.

- **The gate is six characters and two script names, and it only became possible after the cleanup.** `pint --test`, `npm run format:check`, `npm run lint:check`. Flipping these before the 261-file sweep would have turned CI red on every branch at once, which is why it was the last of three PRs rather than the first.
- **`lint:check` is new; the other two already existed.** `format:check` has been in `package.json` since the Laravel scaffold and was never wired up. ESLint had only `eslint . --fix`, so the check-mode twin was added alongside it, following the same naming as the pair that was already there. `npm run lint` and `npm run format` are untouched - fixing locally is still one command, CI just will not do it for you.
- **Each gate was verified to fail before being trusted.** A green run on a clean tree proves nothing, so one deliberate violation per language was planted and confirmed to break the build: a `$a=1;` PHP method for Pint, an over-indented `.ts` export for Prettier, and an unused `const` for ESLint (`@typescript-eslint/no-unused-vars`). Each returned a non-zero exit, and each returned 0 again once the probe was deleted.
- **Node is pinned and installed from the lockfile now, which a check-mode gate needs and a write-mode one did not.** The job used a bare `npm install` on the runner's default Node with no `actions/setup-node` step. That is survivable when the output is discarded; as a gate it means a Prettier minor could resolve differently on CI than locally and fail a PR that is genuinely fine. Now `node-version: 22` and `npm ci`, matching `tests.yml`.
- **Permissions dropped from `contents: write` to `contents: read`.** The write scope existed only for the commit-back step. Nothing in this job writes to the repo any more, so it should not be able to.
- **The commented-out auto-commit step is deleted, and the reasons are written where it used to be.** It sat there from the repo's first commit (`783b81fc`, 24 July 2025) untouched - nobody disabled it, it shipped that way in the Laravel scaffold. Leaving a plausible-looking block commented out is an invitation to uncomment it, and doing so would need `contents: write`, would not work on fork PRs (this repo is public and AGPL, so outside PRs are the point), could retrigger CI from its own push, and - the part nobody would predict - would have rewritten the help pages' teaching examples, since Prettier formats code inside markdown fences.
- **Both workflow files were parsed with `symfony/yaml` before committing.** A malformed workflow does not fail loudly, it simply never runs - which is the same class of silent-green problem this entire chore was about. Both parse, and every step resolves.

## August 14th, 2026 - style: 261 files of accumulated drift, formatted once

CI has been generating these exact edits on every push for thirteen months and throwing them away. This is that backlog, applied deliberately instead of discarded silently. No behaviour changes: 1287 tests pass with **3968 assertions, identical to before**.

- **The config was fixed first, so the sweep normalises in one direction.** `.prettierrc` carried `tabWidth: 4` with overrides back to 2 for `.vue`, `.ts` and `.yml` - which left `.css`, `.json`, `.js` and `.mjs` orphaned on the old Laravel-starter default. Formatting with that config would have pushed eleven files *from* 2-space *to* 4-space, against both convention and how they are actually written. Prettier never touches PHP here (Pint owns that, and no Blade plugin is installed), so every file it formats is frontend, where 2 is the convention. The base is now `tabWidth: 2` and the override block is deleted as redundant.
- **The two indentation rules are now owned by one tool each.** PHP is 4-space via Pint's Laravel preset (PSR-12, no `pint.json`), everything Prettier touches is 2-space. Nothing sets indentation in two places any more.
- **Checked what Prettier wanted to expand before letting it.** Compact one-line entries in `resources/dsl/dsl.json` and the grouped function-name arrays in `engine.mjs` are deliberate readability choices, and Prettier disagrees with both. Measured rather than assumed: +7 lines and +19 lines respectively. Small enough that consistency wins; had it been hundreds, they would have earned an ignore rule like the help pages did.
- **The help pages stayed untouched, which is the previous PR working.** A real write-mode run modified 262 files and exactly **0** under `resources/help`. That rule is now load-bearing rather than theoretical.
- **Two of these tools delete code, so the suite is the actual check.** `prettier-plugin-organize-imports` removes and reorders imports; Pint's `no_unused_imports` did the same to three test files. An import removed wrongly is a runtime error, not a style nit. The identical assertion count either side is what rules that out - equal *pass* counts would still hide a test that quietly stopped asserting.
- **Both check-mode gates now exit 0.** `npm run format:check` and `pint --test` are clean, which is what makes the next step possible: `lint.yml` can finally run them in check mode instead of write mode. That is the whole point of this commit and it is deliberately not in it.
- **Nothing here was chosen; it is thirteen months of one tool's opinion, applied at once.** Reviewing it line by line is not a good use of anyone's evening. The verification is the build, the typecheck, the linter and 1287 tests, all green.

## August 14th, 2026 - fix(format): Prettier was one uncommented line away from rewriting the docs

`resources/help/**` is now in `.prettierignore`. Found while investigating why CI runs Pint and throws the result away, which turned out to be the less interesting half of the story.

- **Prettier formats code inside fenced blocks, and the help pages are mostly fenced code.** On `resources/help/pages/blocks.md` it collapses a four-line `<div class="item">` example onto one line, and mangles the CSS sharing that ```html fence because CSS is not valid HTML. That example exists to show a streamer exactly what to type. Reformatting it is not a style fix, it is a content edit. 30 of the 80 help pages Prettier wanted to touch contain fences.
- **The pages are served verbatim, so this is user-visible.** Every help page has a `.md` twin that `llms.txt` points at, which is the whole point of the contextual-help work. A mangled example is shipped to both people and language models.
- **The damage was latent, not active, and that is the uncomfortable part.** `lint.yml` runs `npm run format` (write mode, not `--check`), so CI has been generating these edits on every push for months and discarding them when the runner dies. The step that would have committed them is commented out - and has been since the repo's first commit, `783b81fc "Laravel? Pretty cool!"`, 24 July 2025. Nobody disabled it; it arrived that way in the Laravel scaffold and `lint.yml` has never been edited since. That accident is the only reason 80 doc pages are intact.
- **Which inverts the obvious fix.** "CI throws away its own work, just uncomment the commit step" would have silently rewritten the documentation on the next push. The ignore rule has to land first, before anything makes those fixes durable.
- **Verified by running the thing that would have caused the damage.** With the rule in place, `npm run format` in write mode modifies 220 files and exactly **0** under `resources/help`. Before the rule, `--check` listed 301 files; after, 221.
- **Scoped to the whole directory because it is uniformly content.** All 166 files under `resources/help` are markdown. Same reasoning as the existing `resources/views/mail/*` entry: content directories stay out of the formatter.
- **The 220 remaining files are real drift and are deliberately not fixed here.** Pint wants 38 more. Both get their own pass; this PR is the one line that has to precede it.

## August 14th, 2026 - chore(ops): the backup moved to 16:00, because nobody reads an alarm at 03:30

The database backup ran at 03:00 UTC for the reason everyone picks 03:00: backups go at night, when the box is quiet. Someone pointed out that this is inherited convention rather than a decision, and they were right. It now runs at 16:00 UTC, which is 18:00 in Amsterdam.

- **There was never a load window to hide in.** The dump is about 1.5 MB and takes roughly two seconds. It was not competing with traffic at any hour, so the entire benefit of the small hours - avoid peak, avoid contention - was worth precisely nothing here. The convention was imported from systems where a dump takes an hour and saturates the disk.
- **There is no globally quiet hour, and aiming for one is a category error.** The users are streamers in every timezone. 03:00 in Amsterdam is mid-afternoon in Sydney and late evening in New York. A "quiet" local small hour is quiet in exactly one place, and it is not a place anyone is streaming from.
- **What 03:00 did reliably deliver was an alarm nobody could hear.** Both the Discord shout and the Healthchecks alert fire within half an hour of the run, so a failure meant a phone buzzing at 03:30 beside a sleeping solo operator. That resolves two ways: wake up and debug object storage at 03:30, or sleep through it and find out hours later. The second is what actually happens, which makes the alert decorative. An alert timed for when the only person who can act on it is unconscious is not a smaller alert, it is a broken one.
- **Nothing was traded away for this.** The dump is still up to 24 hours stale in the worst case, exactly as before - the retention window did not change size, only position. Both providers' retention rules are lifecycle policies measured in days, so neither R2 nor Scaleway needed touching.
- **A test pinned the old time, which is why this was not a one-line edit.** `BackupDatabaseTest` asserts the cron expression is `0 3 * * *`, so changing `routes/console.php` alone would have gone red. It now pins `0 16 * * *`. The companion assertion that `config('app.timezone')` is UTC is the load-bearing half: flipping the app to `Europe/Amsterdam` would leave the cron expression byte-identical while silently making the real run time drift an hour with DST.
- **Moving a scheduled job breaks a dead-man's switch exactly once, and the doc now says so.** Healthchecks measures the gap between pings, not wall-clock time. Shifting the run later by N hours makes that one interval 24 + N hours, which trips a false alert unless Grace is widened for a single cycle. The trap is that the obvious fix does not work: a manual `php artisan backup:database` never touches the switch, because the pings are attached to the schedule and not the command, deliberately, so that a dead scheduler cannot be masked by a hand-run backup.
- **The word "nightly" was wrong in seventeen places.** Config comments, `.env.example`, `.kamal/secrets.example`, `config/deploy.yml`, the Dockerfile, `CLAUDE.md` and the deploy doc all described a nightly job. Swept to "daily". Changelog entries were deliberately left alone: they are a record of what was true when written, not documentation to keep current.
- **Pint wanted to reformat 38 unrelated files and was not allowed to.** Running it after editing surfaced pre-existing style drift across models, controllers and migrations that has nothing to do with this change. All 38 were reverted. The drift is real and worth its own pass - `lint.yml` runs Pint but its commit-back step is commented out, so CI has been fixing style into a void for some time - but a schedule change is not the place to bury it.

## August 14th, 2026 - feat(ci): the types were being checked by memory

`vue-tsc` has been a devDependency for months and nothing ever ran it. Not the build, not either workflow, not an npm script - there was no `typecheck` script to run. Vite strips types with esbuild without checking them, so a type error could pass the build, pass the linter, pass 1287 tests and land on main. The only thing standing between a broken type and production was remembering to type `vue-tsc --noEmit` by hand.

- **It was already clean, which is the surprise.** The worry going in was a triage batch: turn on a gate that has never run and inherit fifty errors. `vue-tsc --noEmit` exited 0 on the first try. Months of running it manually had genuinely held the line - the gate just makes that durable instead of dependent on mood.
- **The gate was verified to fail before it was trusted.** A green run on a clean codebase proves nothing; a script that checks the wrong files is also green. Two throwaway probes were planted and confirmed to break the build - a `const streamerName: string = 42` in a `.ts` file, and a `const viewers: number = 'not a number'` inside a `.vue` SFC - each returning `TS2322` and exit code 2. Both were then deleted and the clean run re-confirmed.
- **The `.vue` probe is the one that mattered.** Almost all of this codebase is Single File Components, and a plain `tsc` cannot read them at all. Proving the `.ts` case only would have left the gate looking real while ignoring the overwhelming majority of the code it is supposed to guard.
- **It went in `tests.yml`, not `lint.yml`, and that is deliberate despite `lint.yml` being the obvious home.** The linter workflow runs a bare `npm install` on the runner's default Node with no `actions/setup-node` step, so it can resolve versions the lockfile never saw. `tests.yml` pins Node 22 and installs with `npm ci`. A type gate is only as trustworthy as the tree it runs against, so it belongs with the exact one.
- **Placed immediately after `npm ci`, before composer.** The check needs only `node_modules` and committed source - `resources/js/types/ziggy.d.ts` is tracked rather than generated, so it does not wait on `ziggy:generate`, the database or the build. A type error now fails in seconds instead of after the full install-migrate-build chain.
- **This is why the `list_writer` union gap sat unnoticed in June.** A legitimate control type was missing from the `OverlayControl.type` union in `types/index.d.ts` and nothing complained, because nothing was looking. That class of latent inconsistency is what the gate is for.
- **Unrelated to the TypeScript 7 question, and not a step toward it.** TS 7 remains a hard block: it ships without a public compiler API, so `vue-tsc` cannot embed it and `typescript-eslint` caps its peer range at `<6.1.0`. Adding this gate does not move that, and running the check more often does not make the upgrade any closer.

## August 14th, 2026 - chore(deps): Pest 5, and the boring kind of major upgrade

Pest 4.7.8 to 5.1.1, which drags PHPUnit 12.5 to 13.3, `php-code-coverage` to 14.3, all five Pest plugins to 5.x and the entire `sebastian/*` line to new majors. Fifty-odd packages, three of the biggest version numbers in the dev tree, and not one line of test code changed.

- **The pre-flight is the whole story, and it is two checks.** PHPUnit's own upgrade advice is to get the suite clean on 12.5 with no deprecation warnings before going anywhere near 13. Running with `--display-deprecations --display-phpunit-deprecations` on the old version reported nothing, which is the signal that says the upgrade is a lockfile change rather than a project. The second check was grepping for every API PHPUnit 13 removed - `Assert::isType()`, `assertContainsOnly()`, `containsOnly()`, `testClassName()`, `Configuration::includeTestSuite()`/`excludeTestSuite()`, `--dont-report-useless-tests`, `#[CoversNothing]` on methods, `#[RunClassInSeparateProcess]` - plus the newly hard-deprecated `any()` matcher. Zero hits across the project.
- **A grep that finds nothing is worth exactly nothing until you prove it can find something.** "No matches" is the same output whether the codebase is clean or the pattern is broken, so the removed-API sweep was re-run against `expect(`, `createMock`, `getMockBuilder` and friends: 1271 hits across 94 files. Only then does the empty result mean anything.
- **The assertion count is the real proof, not the pass count.** 1287 passed and 6 skipped both before and after, which is reassuring but weak - a test that quietly stops asserting still passes. The suite reports **3968 assertions on both sides**, identical. That is what rules out a test having been silently neutered by a changed matcher.
- **The six skips are the same six, and they are environmental.** All are `BackupDatabaseTest` cases skipping on `pg_dump is not on PATH`, which is a Windows dev box fact and has nothing to do with Pest. Checked by name rather than by count, since six-before and six-after would look identical even if the set had changed entirely.
- **PHP 8.4 is now a floor rather than a preference, and CI already met it.** Pest 5 raises the minimum from 8.3, and PHPUnit 13 drops 8.3 support outright. Both `tests.yml` and `lint.yml` were already pinned to 8.4, and `composer.json` already required `^8.4.1` with the platform pinned to 8.4.1. Nothing to do, but it is the check that would have turned a green local run into a red pipeline.
- **None of this reaches production.** The Dockerfile installs with `--no-dev`, so Pest and PHPUnit have never been in the deployed image. The blast radius is the test suite and CI, which is why a major of this size is a reasonable thing to do on a Tuesday.
- **CI needed no edit either.** `tests.yml` invokes a bare `./vendor/bin/pest` with no version-specific flags, so there was nothing pinned to the old major to update.
- **`composer.json` moved by two lines.** The `require-dev` constraints for `pestphp/pest` and `pestphp/pest-plugin-laravel`, both `^4.0` to `^5.0`. No test file, no config file, no `phpunit.xml`, no `tests/Pest.php`.
- **Two new transitive packages and one departure.** `sebastian/file-filter` and `sebastian/git-state` arrive as PHPUnit 13 dependencies; `composer/xdebug-handler` drops out. Noted because a lockfile that gains packages during an upgrade is worth reading rather than skimming.

## August 14th, 2026 - chore(deps): the routine sweep, and one advisory worth clearing

Both trees audited, everything in range applied, nothing widened. `composer audit` was clean before and after. `npm audit` was not: one high-severity advisory, now closed.

- **The advisory was real but the exposure was narrow, and both halves matter.** `brace-expansion` 5.0.8 has a DoS via unbounded intermediate arrays ([GHSA-rgw5-rvv9-x895](https://github.com/advisories/GHSA-rgw5-rvv9-x895)), and it reaches the project by exactly one path: `eslint -> minimatch -> brace-expansion`. That is a devDependency, so nothing in it is in the bundle an overlay serves to OBS. Worth fixing because it costs one line, not because a streamer was ever exposed to it.
- **Fixed with an override rather than `npm audit fix`, because the automated fix wanted to do something else too.** `audit fix --dry-run` proposed the brace-expansion bump *and* the installation of 37 packages including `lightningcss-linux-x64-gnu`, one of the two Linux-only optionals that are supposed to stay MISSING on a Windows dev box and resolve in the production image. Pinning `brace-expansion: ^5.0.9` in the existing `overrides` block gets the fix and nothing else, and puts it next to the `ws` and `shell-quote` guards that are already there for the same reason.
- **The one update that needed a second look was `aws/aws-sdk-php` 3.390.5 -> 3.392.2.** That library is what writes the nightly backups to R2 and Scaleway, and the SDK's checksum defaults are precisely what the `r2` disk was hardened against when it started sending CRC32 trailers the bucket would not always accept. Both disks still pin `request_checksum_calculation` and `response_checksum_validation` to `when_required` and both keep `throw => true`, so the guards that make a failed upload loud are intact. A patch-line bump inside 3.x should not touch any of it, but this is the one dependency here whose regression would surface at 03:00 UTC as a missing backup rather than as a red test.
- **`inertiajs/inertia-laravel` 3.1.1 -> 3.3.1 closes a gap rather than opening one.** The server adapter was two minors behind a client already installed at `@inertiajs/vue3` 3.6.1. Moving the PHP side forward narrows that spread.
- **Composer moved 34 packages, npm 59, and neither manifest gained a range.** `composer.json` is untouched. `package.json` changed by the single override line and nothing else, which is the check that separates a sweep from an upgrade.
- **What is deliberately still stale.** `typescript` stays at 6.0.3 with 7.0.2 available: still a `.0`, still nothing broken, and moving it would drag `vue-tsc` and `typescript-eslint` along for no functional gain. The two Linux-only optionals still report MISSING, as they should on Windows. `npm outdated` now lists exactly those three and nothing else.
- **Pest 5.1.1 is out and is not in this commit.** It is past the `.1` mark, so it is a fair conversation now rather than an automatic no, but it is not a lockfile bump: it drags PHPUnit 12.5 to 13.3, `php-code-coverage` 12.5 to 14.3, all five Pest plugins to 5.x, and the entire `sebastian/*` line to new majors, across 105 test files. That gets its own branch and its own PR, or it turns a boring sweep into a debugging session.
- **Verified in that order for a reason.** `npm run build` and `npm run lint` first, since a broken toolchain makes the test results meaningless, then the suite: 1287 passed, 6 skipped, 0 failures. Lint ran with `--fix` and changed no files, so the three modified paths are the two lockfiles and the one override line.

## August 14th, 2026 - feat(share): an overlay you can hand to a person or to an LLM

Tell a language model about your Overlabels overlay and you could paste the HTML and the CSS, and then run out of ways to explain it. There was no way to say which controls it reads, which integrations have to be connected first, or what the alert is bound to. The public preview page had the same hole from the other side: it showed `head`, `html` and `css` and nothing else, so nobody could tell what an overlay needed until after they had copied it.

Public overlays now have a markdown twin at `/overlay/{slug}/public.md`, and the preview page grew the panels it was always missing. One `OverlayShareService::document()` call feeds both, so the page and the document cannot drift.

- **The URL was already the code; it was just incomplete.** No new share format, no short-code table, no export file. `/overlay/{slug}/public` has been the share link for months and already had a working Copy button. Appending `.md` to a URL to get its plain source is also already the convention here - it is how every help page works, and llms.txt points at it. This extends that convention from prose to overlays and adds nothing else.
- **Controls were the actual gap, and they are not a relation.** `$template->controls` is the wrong answer. An overlay's controls come from three places: template-scoped rows it defines, user-scoped service-managed rows (`c:kofi:donations_received` belongs to your account, not to the overlay), and Lists. So the document is assembled by scanning the tags out of `head`/`html`/`css` and resolving each one, which is also the only method that works for a reader who does not own the overlay.
- **Values are shared for controls the overlay defines, and never for service controls.** `latest_donor_name` holds a real person's name and `total_received` is revenue. Those rows are not read at all; service controls are described from the driver's `getAutoProvisionedControls()`, which is canonical and user-independent. The split is not a judgement call - it is exactly what `Kit::fork` persists, so the document cannot promise more than the Copy button delivers. Removing the filter was verified to put a donor's name straight into the published document.
- **Expression formulas live in `config`, not `value`, and nearly shipped invisible.** The first draft printed the value column and rendered a nine-curve Lissajous overlay as nineteen blank cells: complete-looking and worthless. Found by rendering a real overlay from the dev database rather than trusting the tests, which were all green.
- **Triggers are described but flagged as not copied.** An alert's event bindings belong to the author's account and `fork()` does not replicate them. They are included because they are the single best explanation of why `[[[event.bits]]]` is in the markup, and labelled so the document does not misrepresent what copying gives you.
- **A timer's `started_at` is not a property of the overlay.** Runtime state is stripped out of every control config for the same reason service values are omitted: it describes when the owner last pressed a button, not how the overlay works.
- **The fence adapts to the source.** Template HTML is arbitrary text and can contain a markdown fence in a comment, which with a fixed ``` would truncate the document at that point. The fence is sized to the longest backtick run in the content.
- **`rel="alternate" type="text/markdown"` in the head, shared per route.** Accurate here in a way it is not for llms.txt, which is why that one uses `rel="llms-txt"` instead. A test asserts the link does not leak onto pages that have no markdown twin, since that would send crawlers to 404s.
- **Kept under the `overlay.*` route namespace, unlike `reports.store`.** Nothing in the frontend resolves it - the preview page is handed the URL as a prop - so Ziggy's blanket `!overlay.*` deny never comes into it.
- **The `.md` does not count as a view.** `view_count` is a human-interest number on the preview page, and crawler fetches would drown the signal.
- **Builder-composed overlays needed no special handling, which was not obvious up front.** A `metadata.builder` static looked like the case where "the whole overlay" is not one row. It is not: placements carry full head/html/css snapshots and the compiled result is written to the three fields, which is all the render pipeline ever reads. The shared source is already complete; the document just names the blocks it was assembled from.

## August 14th, 2026 - feat(share): kits get the same treatment, and one route leaves the auth wall

A kit is nine overlays that are meant to be installed as a set. `/kits/{id}.md` now returns the kit and every overlay in it, each described exactly as its own `.md` describes it, with one aggregated list of the integrations the whole kit needs.

- **It composes the overlay service rather than reimplementing it.** A kit document is N overlay documents plus a header. `OverlayShareService::body()` was split out of `markdown()` so a kit can nest whole overlays under its own headings, and a test asserts an overlay renders identically standalone and inside a kit. The moment the two describe controls differently, one of them is wrong.
- **Heading depth is a parameter, not a regex.** The obvious way to nest is to shift `#` levels in the finished string. Template CSS is full of lines starting with `#`, so that would rewrite an id selector inside a fenced code block into a heading. The depth is threaded through the section renderers instead.
- **A kit and its overlays have separate visibility flags, and nothing relates them.** `KitController::store()` validates only that the templates belong to you, so a public kit may legitimately contain private overlays. None do today - that is luck, not a constraint. The kit flag gates the document; each overlay's own flag gates whether its source appears in it. A private one is listed by name and type, because copying the kit copies it too and hiding it would misdescribe what you get, but its source, controls and triggers are withheld. The count of withheld overlays is stated before the contents table, not discovered at the bottom.
- **This is the one kit route that sits outside `auth.redirect`, and that asymmetry is the point.** `kits.show` requires a login; a URL you hand to a language model cannot. It is safe to open precisely because of the rule above: every line of source in the document is already readable at that overlay's own public `.md`, so it exposes nothing new. Both directions are pinned by tests - the `.md` serves a logged-out visitor, and the HTML page still redirects them.
- **The gate is not where it looks like it is.** Neutralising the `is_public` filter that builds the per-overlay documents did not leak anything, because the early return in `overlaySections()` is what actually withholds a body. Found by breaking it and watching the tests stay green; both gates are now broken-and-verified, and the real one carries a comment saying not to reduce it to an `isset()`.
- **`kits/{kit}` would happily match `3.md`.** It has no numeric constraint, so the markdown route only wins by being registered first. A test resolves the URL through the router and asserts which route it lands on, rather than trusting registration order to stay put.
- **The affordance only appears on public kits.** `markdownUrl` is null otherwise, so the owner of a private kit is never offered a link to their own 404.

## August 14th, 2026 - feat(ui): fifty grey boxes with the domain name at the top

Forty-four `window.confirm()` calls and six `window.alert()` calls, spread across kits, templates, controls, lists, tokens, six integration pages, the admin panel and account deletion. Every one of them an unstyled grey box with the domain name at the top, ignoring the theme entirely, in an app whose whole pitch is that your overlays look like you made them on purpose.

- **Promise-based, because callback-based cannot do this job.** The app already had `triggerLinkWarning(doThis, warning)`, which works when the guarded code is a single call. It cannot express `if (!confirm(x)) return;` in the middle of an async function that continues afterwards - and that was the shape of most of the 44. `await confirm(...)` converts every one of them by inserting a word, leaving the surrounding logic untouched. `useLinkWarning` still exists and its three call sites are unchanged; it is now a nine-line wrapper over the same dialog.
- **Missing an `await` is the failure this design invites, so a test guards it.** A forgotten `await` yields a Promise, promises are always truthy, and `if (!promise)` is false - so the dialog appears and the guarded action fires immediately behind it, without waiting for an answer. TypeScript has nothing to say about `!somePromise`; it is perfectly legal. `NativeDialogsTest` scans every `.vue` and `.ts` for a `confirm(`/`alert(` call that is not awaited, which catches both a missing await and a relapse to the native functions in one assertion. Verified to go red by removing a single `await`.
- **The dialog is a singleton, and a singleton silently does nothing where it is not mounted.** It lives in `AppLayout`, which covers everything except two full-screen pages that deliberately render outside it. A second test walks every page that awaits a dialog, and fails if it neither uses `AppLayout` nor mounts `ConfirmDialog` itself. That test found a real bug the moment it was written: the gamejam room builder had been converted and would have hung forever on a failed save, waiting on a dialog that nothing was rendering.
- **Every exit path answers the promise.** Button, Escape, backdrop click, the host component unmounting mid-dialog, and a second dialog opening on top of the first - all route through one `settle()`. An unanswered promise is not a visual bug, it is a caller frozen forever at its `await`, and the only way to be sure is to have one place that can end it.
- **Red is now reserved for destructive actions.** The old modal had exactly one confirm button and it was red, which was right for the token warning it was built for. Applied uniformly it would have painted "Copy this kit to your own account?" in alarm colours. Tone defaults to `danger` rather than neutral, on the grounds that a forgotten tone should over-warn on a delete rather than under-warn; the benign copy actions opt into `neutral` explicitly.
- **The buttons say what they do.** "Continue" is the label you write when one dialog serves every purpose. The confirm button now reads Copy, Delete, Disconnect, Revoke, Regenerate, Reset, Restore, Run - so the button and the sentence above it cannot drift apart, and the destructive ones cannot be dismissed by muscle memory.
- **`\n` in a message still means a line break.** Two of the longest warnings were written as multi-paragraph strings for the native dialog. `whitespace-pre-line` keeps them readable rather than collapsing them into a wall, and the two that opened with a shouted first line now use the title slot instead.
- **The one call site that could not be a straight swap.** The public preview page copies via a real form submit, and used `confirm()` inside `@submit` to decide whether to `preventDefault()`. A promise cannot answer in time to make that decision, so the handler now always cancels the native submit and calls `form.submit()` on accept - which bypasses the handler rather than re-entering it.
- **It also unfreezes browser automation.** A native dialog blocks the renderer, so any tooling driving the page stops dead until a human clicks the box by hand. That is how this started: the Copy button could not be clicked without stubbing `window.confirm` first.

## August 13th, 2026 - fix(kits): the Copy button produced something called a Fork

Copy a public kit and it lands in your account titled "Fork of Midnight Purple". The button that made it is labelled "Copy kit to your own account", and the blurb directly above it says "Copy any kit to use it in your own overlays". Three words for one idea on a single screen, and the one the app chose to show you was the one that is never supposed to appear in front of a user.

- **The literal string was load-bearing, which is why this was not a one-character fix.** Public-kit discovery filtered itself with `where('title', 'not like', 'Fork of%')`. Renaming the prefix without touching that line would have quietly dropped every public copy into the discovery list - six of them already exist, five owned by other people. Discovery now filters on `forked_from_id` being null, which is the actual question being asked and cannot be broken by editing display copy.
- **The two were perfectly correlated, so the swap was safe to make.** Every kit with a `Fork of` title had `forked_from_id` set, and every kit with `forked_from_id` set had the title - checked both directions before changing the filter, rather than assuming the column had been populated consistently since forking shipped.
- **Existing rows were renamed rather than left as a second dialect.** A migration rewrites the six `Fork of ...` titles to `Copy of ...`. Leaving them would have meant the word survives in the UI for exactly as long as those kits exist, which is indefinitely.
- **The sweep found a third synonym nobody had noticed.** The kit detail page asked "Clone this kit to your account? This will also clone all templates within the kit." So the same action was Copy on the card, Fork in the result, and Clone in the confirm dialog, depending on which surface you happened to touch it from.
- **`TemplateCard` and `TemplateCollection` had drifted apart on the same menu item.** One said "Copy template", the other "Fork template" - the same row rendered by two components, one of which had already been fixed at some point and the other not. This is the failure mode `CollectionList` exists to prevent, in a pair that predates it.
- **Internal naming was deliberately left alone.** `fork_count`, `forked_from_id`, `handleFork`, `ForkImportWizard`, the `/kits/{id}/fork` route and the `GitFork` icon import all stay. The rule is about what a streamer reads, and renaming a route is how you earn a Ziggy failure that no PHP test catches. Only strings that reach a screen were touched.
- **The admin panel still says Forks, on purpose.** Four spots under `/admin` use the word in table headers and counts. Nobody but the operator sees them, they are the surface where the internal column name is the clearer label, and sweeping them would have widened a copy fix into a tour of the admin views.
- **Found by looking at the page.** This shipped and sat there; 1245 passing tests had nothing to say about it, because no test asserts what a title says. It surfaced within seconds of actually clicking the button in a browser.

## August 12th, 2026 - fix(builder): block previews ignored the overlay's own CSS

Place a block that paints itself orange, then use "Your CSS and fonts" to override it to green. Save, and the overlay goes green as instructed. The Builder canvas stays orange, because the block previews never knew that panel existed.

- **It was a missing wire, not a broken cascade.** `custom_css` and `custom_head` live in `useBuilderState` and had exactly two readers: the editors that write them, and `composeBuilderTemplate` at save time. Nothing carried them down to `BuilderPlacement`, which built each preview iframe from the block's own snapshot and nothing else. Every rule you typed was correct and simply not present.
- **The preview is now a one-cell copy of the compiled document.** Same `#builder-root` and `#blk-{instance}` wrappers, same `.builder-cell` class, block CSS scoped to the cell, your CSS scoped to the root and emitted last - the exact structure and order `composeBuilderTemplate` writes. The cascade agrees with the compiler because it is the same construction, not because the easy case happens to come out the same.
- **Appending the CSS raw would have worked, then quietly stopped working.** Two divergences make it a trap. `prefixCss` *replaces* a leading `:root`/`html`/`body` rather than prefixing it, so `body { background: black }` is the canvas backdrop once compiled but would have painted every single block black in preview. And a bare `.value` override only ties a block's `.value` because both sides pick up an id; unscoped, that relationship is gone. Scoping both sides keeps them exactly one id apart, which is what the specificity note in the compiler has always been about.
- **`.builder-cell` targets something now.** The Style panel lists it first, described as "On every block wrapper in this overlay". Until this change the previews had no such element anywhere, so the panel's top recommendation was the one target guaranteed to appear dead.
- **Previews update on demand, not as you type.** Live-binding the CSS would rebuild the srcdoc of up to forty iframes on every keystroke, each a full document load carrying its own copy of a stylesheet with no length limit. The canvas shows the last version you sent it, and a "Send to preview" button appears when the editors have moved on.
- **The signal lives on the CSS editor, because that is what is on screen.** The first attempt put a banner above the canvas, which is exactly where it is useless: the editors sit below the canvas, so by the time you are typing in them the banner is off the top of the screen. Worse, it was a block of UI that grew into existence on a keystroke - typing `greeng` shoved the editor down, deleting the stray `g` yanked it back up. Now the editor's own border turns orange, and the label row carries the note and the button. That row is always rendered and only fades, so nothing on the page moves in either direction.
- **The collapsed panel still says something.** The header dot already meant "this overlay has custom CSS"; it turns orange while changes are unsent rather than gaining a second dot beside it, so the header does not twitch every time the state flips.
- **Copy says which side is stale.** "The saved overlay uses what is in these editors either way" - what you typed is what saves, always. Only the preview lags, and the wording has to rule out the reading where your CSS quietly did not take.
- **The editors now catch up with the sanitizer.** Paste a `<script>` into the fonts editor and the server strips it out of `metadata.builder.custom_head` on the way in, as it has since that field existed - the saved overlay never carries it, and the preview iframes are `sandbox=""` so it could not have run there either. But nothing re-seeds the Builder after a save, so the script stayed in the editor: still visible, still shipped to the previews, and re-reported as a fresh removal on every subsequent save, with no way to make the warning stop short of reloading the page. The save path now cleans the editors the same way it already cleaned the plain code editors. The server remains the actual defence; this is only about the buffer telling the truth.
- **Saving from `/builder` finally says what it removed.** The edit page has always toasted "Removed N unsafe patterns"; the Builder's own create page computed the same number and threw it away, so an overlay created with a script in it was cleaned in total silence. It could not toast: it saves over axios and then navigates to the new template, so the component reporting the outcome unmounts before anyone could read it. A Laravel session flash cannot bridge that either - the controls-import POST runs in between, and flash data survives exactly one request, so it would be aged out before the destination rendered. The notice is stashed in `sessionStorage` and claimed by the template page on mount, read-and-clear so a reload does not replay it. sessionStorage rather than localStorage on purpose: a notice whose navigation never happened should die with the tab, not resurface days later announcing something that did not just occur.
- **The applied snapshot sits next to the typed value, with `serialize()` warned off it in comments.** It has to: the canvas consumes it and the style panel controls it, and they are siblings. The risk this trades into is a save that writes the previewed CSS instead of the typed CSS, which is why the pairing is commented at the definition rather than left to be inferred.
- **A block's own `body {}` rules preview correctly too now.** They previously applied page-wide inside the iframe while compiling down to just the block wrapper. Same fix, no extra code - it falls out of previewing through the real pipeline.

## August 10th, 2026 - feat(ops): a second backup provider, because one bucket is one account

The nightly Postgres dump has been going to Cloudflare R2 since August 5th. That is a backup, but it is one bucket behind one login: an account suspension, a billing lapse, a leaked token or a fat-fingered bucket delete takes out every copy at once, and none of those are exotic. The same dump now also ships to Scaleway Object Storage.

- **The dump is taken once and pushed to both.** Two separate dumps would be two different databases seconds apart, and you would not know which one you were restoring. One file, one key, byte-identical copies - the test asserts the contents match, not just that two objects exist.
- **Every destination is attempted even after one fails.** This is the whole point and it is easy to get wrong: a fail-fast loop means a Cloudflare outage also costs you the Scaleway copy, which is the exact correlated failure the second provider exists to prevent. `uploadAll()` never throws for an upload failure; it collects a `disk => error` map and lets the caller decide the exit code.
- **A partial success is still a failure.** One leg landing exits 1, alerts Discord with which leg died and which copies survived, and flips the Healthchecks switch red. The quieter option - exit 0 because you do still have a backup - was considered and rejected: a destination that silently stopped working would then surface only in a Discord message you might scroll past, and you would find out you had been running on one copy for months at the worst possible moment.
- **The bucket name is configuration, not documentation.** It lives in `SCW_BUCKET` and nowhere else - not in the docs, not in the changelog. One place to change it, no second copy to drift, and nothing about the storage layout is restated in prose that can quietly go stale. The region is pinned separately in `SCW_REGION` because it is part of the endpoint hostname rather than a lookup key.
- **Two of the four Scaleway credentials are enough.** `SCW_ACCESS_KEY` and `SCW_SECRET_KEY` do it. `SCW_DEFAULT_ORGANIZATION_ID` and `SCW_DEFAULT_PROJECT_ID` are Scaleway-CLI concepts that never appear in an S3 request - the S3-compatible API resolves the project from the access key - so they are deliberately not deployed. No Scaleway CLI is installed anywhere, and no new dependency was added: it is the same `s3` Flysystem driver R2 already uses.
- **Region is part of the endpoint hostname and is not global.** A bucket in `fr-par` returns 404 NotFound on the `nl-ams` host, which reads as "the bucket has been deleted" rather than "wrong host" and would send you hunting in entirely the wrong place. Documented next to the identical R2 jurisdiction trap.
- **The CRC32 checksum pin was copied across deliberately.** aws-sdk-php >= 3.337 adds integrity trailers by default that R2 has been inconsistent about accepting; rather than find out whether Scaleway is too on a night the backup is needed, both disks pin `when_required`.
- **Verified against the real bucket, not just fakes.** A 9.1 MB dump uploaded to Scaleway and passed the read-back size check, then the test objects were deleted. The partial-failure path was exercised for real as well: with R2 credentials absent locally, `r2` failed first and `scaleway` still got its copy, exit 1. Two of the new tests were verified to go red when `uploadAll()` is reverted to fail-fast.
- **The bucket's retention and privacy were read back off the API, not trusted from the dashboard.** The lifecycle rule is `expire-backups-30d`, status Enabled, all objects, 30-day expiry, plus a 7-day abort on incomplete multipart uploads - that last clause matters because aws-sdk-php switches to multipart above 16 MB, which the gzipped dump will cross on its own as the database grows, and orphaned parts consume the 1 GB quota while appearing in no object listing and being immune to the expiry rule. Privacy was checked three ways: owner-only ACL, no website config, and a `ListObjectsV2` with credentials explicitly disabled returning `AccessDenied`. The dump holds every user's Twitch refresh tokens, so an unauthenticated request actually bouncing beats a toggle that reads "Private".
- **What is still not proven, and is written down as such:** no Scaleway object has been restored yet. The R2 leg has a full verified restore behind it; this one has a verified upload. The docs say so rather than implying parity.
- **Still not 3-2-1 in the strict sense.** Two providers gives the "2" and the offsite copy twice over, but both legs are cloud object storage reached over the same network by the same command, so a bug in that command writes the same broken object to both. Listed under known gaps instead of being quietly claimed as solved.

## August 10th, 2026 - fix(updates): a blog post could delete the app's navigation

`/updates/overlabels-development-highlights-july-2026` rendered with no sidebar at all. The post body showed up fine, the whole left-hand navigation was simply gone. Every other post on the site was unaffected, which is what made it look like a routing or layout problem rather than a content one.

- **A post's compiled CSS was being injected globally.** `updates/show.vue` takes the `compiled_css` stored on the row and appends it to `<head>` as a plain `<style>` tag. That sheet is a flat set of utility rules, so `.hidden{display:none}` from a post applied to the entire document, not just the post.
- **Unlayered beats layered, whatever the specificity.** UnoCSS emits its output unlayered; Tailwind v4 keeps every utility inside `@layer utilities`. In the cascade an unlayered declaration outranks a layered one outright, so the post's `.hidden` beat `.md\:block` without being more specific or coming later. Verified against the deployed bundle rather than assumed: `.md\:block{display:block}` does sit inside `@layer utilities{`.
- **The sidebar is built out of exactly that pair.** `Sidebar.vue` gives the desktop wrapper `hidden md:block` and the fixed panel `hidden ... md:flex`. Both collapsed to `display:none`. `SidebarInset` carries no `hidden`, which is why the article kept rendering and only the navigation vanished, and why it read as a layout bug.
- **One post in nine triggered it.** Checking the stored CSS of every published post found `.hidden{display:none}` in the July highlights post and nowhere else. It also globally redefined `.fixed`, `.block`, `.grid` and `.border`; those happen to match Tailwind's values, so they were overriding the shell silently rather than visibly.
- **It does not reproduce locally.** The only post in a fresh database is `welcome-to-overlabels`, whose body carries no classes at all, so its compiled CSS is the inert `--un-*` variable block and nothing collides. Reproducing needs that specific post's markdown.
- **The fix is `@scope`, not a layer.** Wrapping the sheet in a layer would not have helped, since a later layer still wins; the rules had to stop matching shell elements altogether. `@scope (#updates-post)` confines them to the post container, which now wraps the excerpt and the body and nothing else. The back link, title and tags stay outside it deliberately, since they are app chrome rather than author content.
- **Scoping happens at injection, not at compile time.** That fixes all nine existing rows without a re-save, and keeps `compileTailwindCss` untouched for overlay templates, where global output is correct and has to stay that way.
- **Failing without `@scope` support is the safe direction.** A browser that does not understand the at-rule drops the whole block, costing the post its custom utilities but leaving the app navigable. Better than the current failure, which is losing the navigation.

## August 10th, 2026 - feat(controls): rebuild the Add Control modal as a two-step picker

The Add Control dialog was one scrolling column of form inputs. Only the Text type fit on a 1080p screen. Number, Counter and List writer ran off the bottom entirely, taking the Save button with them, and the only type that got the layout right was Expression, because it happened to open in two columns.

- **The vertical fit is structural now, not tuned.** The dialog is a fixed-height grid of `auto / minmax(0,1fr) / auto`. Only the middle band scrolls and the footer sits outside it, so Save is reachable for every type no matter how much configuration that type needs. Nothing here depends on a control having few enough fields.
- **Two screens instead of one.** Pick shows the eight control types as cards, each with a one-line pitch and a demo of what it actually renders. The Timer demo ticks. Ready-made service presets sit alongside in their own column, each showing the exact tag to paste. Configure keeps the choice visible in a rail next to the form: the card, what it is good for, and a click-to-copy tag that fills in as you name it. Editing and duplicating skip the picker, since the type is already settled in both cases.
- **Each column owns its own search.** The first pass had one search box serving both, sitting above the left column while its most visible effect was on the right one. A control that manipulates something across the page is a UX bug, not a rough edge. Splitting it required the search to live outside what it filters, since a search matching nothing would otherwise take its own input down with it and leave no way to undo the query.
- **Card hover never worked.** It used `group-hover:`, which compiles to a descendant selector (`.group:hover .group-hover\:x`) and therefore cannot match the element carrying `.group` itself. The cards *were* the group. Hover was dead while `focus-visible:` worked fine, because that one is a plain self-variant, and that asymmetry is exactly what gave it away in review. Both are self-variants now.
- **Preset selection was order-dependent.** It was a combobox `v-model` plus a watcher that reset the form whenever the value emptied. Clearing the preset and then setting a type let the watcher run after the synchronous code and clobber the type that had just been set. It is explicit functions now, with no watcher in the path.
- **Text control copy promised something the control does not do.** A text control never fills itself in, so "Now playing: Hollow Knight" implied an automatic update that does not exist. The demo is "Hello world", the placeholder is "Welcome message", and the good-for bullets describe writing your own words. Anything genuinely live belongs in a Ready-made control or an Expression. A comment in the catalog records the rule so the next round of copy does not reintroduce it.
- **`PRESET_GROUPS` replaced sixteen near-identical computeds**, a `show*` and an `available*` for every service. Adding a ninth service is now one row in a table rather than two more computeds and eight more lines of template.
- **Placeholders come from the catalog, and the key placeholder is derived rather than written.** A Switch suggesting "Death counter" teaches the wrong thing about what a switch is for, so each type carries its own example. The key placeholder is slugified from the name placeholder using the same function that derives the real key, which means the pair demonstrates the actual transformation instead of being two strings that can drift apart.
- **The gradients came out after review.** The first pass gave each type an accent gradient wash, under an explicit brief to go wild on design. Shown to a second pair of eyes, it read as "too vibecoded" and every gradient was removed. Type identity is carried by border colour and icon, which is the quieter and more durable answer.
- **Accent classes are full literals in the catalog.** Tailwind only generates what it can see spelled out in source, so building a class string at runtime produces an element with no colour at all. Two such bugs were written and caught during development, both by grepping the built CSS rather than by reasoning about it.

## August 9th, 2026 - chore(cleanup): delete parse_tags.js

A 38-line debugging script in the repo root that read `resources/templates/template-tags.json` and printed its shape to the console. Committed once in `b069963e` during the `TemplateDataMapperService` refactor and never touched again.

- **Its input no longer exists.** `resources/templates/template-tags.json` is gone, so the script would throw on its `readFileSync` before printing anything. Dead in the strong sense, not merely unused.
- **Nothing referenced it but `.dockerignore`**, and that line existed only to keep it out of the image. Removed alongside it, since an ignore rule for a file that does not exist is just a smaller piece of the same litter.
- **`tmp_screenshot.png` stays.** It is still in use, and its `.dockerignore` line stays with it.

## August 9th, 2026 - fix(deps): nanoid 3.3.16 to 3.3.18 for CVE-2026-67213

Dependabot alert 92, high severity: custom nanoid generators can loop indefinitely when size is zero. Fixed in 3.3.17; the lockfile picked up 3.3.18.

- **Transitive and build-time only.** One `nanoid` in the tree, required by `postcss` at `^3.3.16`, which arrives via `tailwindcss` and `@tailwindcss/vite`. GitHub labels the scope "runtime" because those two sit in `dependencies` rather than `devDependencies`, but postcss runs under Vite at build time and nanoid never reaches a browser bundle. The practical exposure was nil; the fix was a three-line lockfile change, so the calculus never got interesting.
- **`npm update nanoid` was enough** because the existing `^3.3.16` range already admits the patch. No manifest edit, no override, no forced resolution.
- **`npm run build` verified afterwards**, clean in 5.33s. A dependency bump that is not build-checked is a guess, even a patch-level one.
- **This is the security-advisory carve-out to "let majors cook"**, not a departure from it. Patch releases that close an advisory go in immediately; the waiting rule is about majors and feature minors.

## August 9th, 2026 - fix(license): GitHub reported the licence as "Other"

The relicense landed and GitHub's API still returned `{"key":"other","name":"Other"}`. The community checklist went green because that only asks whether a licence file exists, but the repo sidebar said "Other" and anything reading the licence programmatically got nothing.

- **The header above the licence text was the cause.** GitHub runs [licensee](https://github.com/licensee/licensee), which normalises `LICENSE` and needs 98% similarity to a known licence. It strips copyright lines specifically, but the header also carried the FSF "This program is free software" notice, a title line and a separator rule. Running licensee locally scored the file at **88.91%** against AGPL-3.0, nowhere near the threshold and a long way from the ~98% that had been assumed when the header was written.
- **The fix is one line of header instead of nineteen.** `Copyright (c) 2026 JasperDiscovers`, a blank line, then the verbatim text. That line matches the copyright regex licensee strips, so what remains is the canonical document. Scores **98.86%** via the Dice matcher.
- **Pure verbatim with no header scores 100% via the Exact matcher**, and is what most AGPL projects ship. It was not chosen because the copyright line is wanted in the file; the 98.86% result is deterministic rather than fuzzy, so the smaller margin costs nothing in practice.
- **The FSF notice moved to the README's licence section**, which is its actual home. It is the "how to apply these terms to your program" boilerplate, not part of the licence, and it was never doing any work inside `LICENSE`.
- **The licence body is still byte-identical to `gnu.org/licenses/agpl-3.0.txt`**, re-verified with `cmp` after the change.
- **Verified before pushing this time, not predicted.** `licensee detect .` on the working tree returns `License: AGPL-3.0`, with `LICENSE`, `README.md` and `package.json` all matching. The previous round asserted GitHub would detect it cleanly on reasoning alone and was wrong by nine percentage points.

## August 9th, 2026 - docs(community): CONTRIBUTING and a code of conduct

The last two items on GitHub's community checklist, written now rather than earlier because both were downstream of the licence. CONTRIBUTING is where the sign-off requirement lives, and that requirement did not exist until there was a licence for contributions to be made under.

- **The most useful line in CONTRIBUTING is "open an issue before starting anything substantial"**, followed by the note that this project grows by accretion rather than redesign. A rewrite of a working area is the pull request most likely to be declined here, and someone deserves to know that before spending a weekend on one, not after.
- **House rules for user-facing copy are written down for the first time.** No em dashes, "Copy" and never "Fork", gender-neutral wording, render nothing when data is missing, `text-foreground` for body copy, `cursor-pointer` on anything clickable. These are small, they are enforced strictly, and until now they existed only as review comments after the fact.
- **Setup is a link, not a copy.** The README already documents self-hosting with the full environment variable list; duplicating it into CONTRIBUTING would produce two sets of instructions that drift apart, and the one nobody updates is the one people read.
- **DCO rather than a CLA, and the difference matters.** `git commit -s` certifies that a contributor had the right to submit their work. It does *not* assign copyright, and it does *not* grant the right to relicense their contribution later. A CLA would, at the cost of friction that deters casual contributors. With zero outside contributions to date nothing is foreclosed either way, so the decision waits until someone actually opens a pull request.
- **Contributor Covenant 2.1, verbatim**, with the one `[INSERT CONTACT METHOD]` placeholder filled with the same address `SECURITY.md` uses. It governs the repository's issues and pull requests. It is not a claim about what the software does for streamers, which stays true to Overlabels not being a safety tool.

## August 9th, 2026 - chore(license): Overlabels is AGPL-3.0-or-later

The repository has never had a licence. `composer.json` said `"license": "MIT"`, which arrived in `783b81fc "Laravel? Pretty cool!"` as part of the starter kit's default metadata and was never a decision anyone made. Meanwhile GitHub reported no licence at all, because there was no `LICENSE` file to report. Those two facts contradicted each other in public on every commit.

- **AGPL-3.0-or-later, not MIT**, because Overlabels is a hosted service whose sustainability plan is still unwritten. MIT lets anyone take this codebase closed and run a competing hosted Overlabels; the AGPL's section 13 means anyone who hosts a *modified* version has to offer its source to the people using it. It keeps the open-source claim in `SECURITY.md` honest without handing away the one thing that is actually the product.
- **The cost of copyleft is normally that it deters contributors, and here that cost is currently zero.** 1398 commits, all mine, plus dependabot. Zero forks. This is the cheapest moment the decision will ever be available.
- **The licence body is byte-identical to the FSF original**, verified with `cmp` against `gnu.org/licenses/agpl-3.0.txt`. The copyright line sits in a header above it rather than inside it, because the document's own terms permit verbatim copies only. The header is the FSF's recommended notice, whose "or (at your option) any later version" is the part that actually encodes the `-or-later`.
- **Four manifests now agree**: `composer.json` switched off MIT, `package.json` gained a `license` field it never had, `package-lock.json` picked the same value up in its root entry via `npm install --package-lock-only` (one line, no dependency churn), and no third-party `license` field in the lockfile was touched, since those describe other people's packages.
- **Forward-only.** No history rewritten, no force-push, no existing commit touched. Code already distributed while the manifest claimed MIT was distributed under that claim and nothing here retroactively changes that. With zero forks the practical exposure is theoretical, but the README says what happened rather than pretending the switch was clean.
- **The README distinguishes the code from your templates.** The Copying section promises "no licensing restrictions" on public templates, and a licence section three screens further down is exactly where someone would conflate the two. The AGPL governs the source; your overlays remain yours.

## August 9th, 2026 - chore(github): issue forms that ask the questions I was going to ask anyway

GitHub's community standards checklist wants five files. Two of them earn their place on merit and the rest are paperwork for a repo recruiting contributors, which this one is not. These are the two.

- **The bug form asks where in Overlabels and where you were viewing it**, because those two answers do most of the narrowing. An overlay misbehaving in OBS but fine in a browser tab is a different bug from one that is broken in both, and that question used to cost a round trip every single time.
- **It asks for the overlay slug and explicitly not the URL.** The part after `#` in an overlay URL is the access token, and someone reporting a rendering bug should not have to already know that before pasting what looks like the obvious identifier.
- **The console field explains how to get one.** OBS browser sources cannot show console errors, so "check the console" is useless advice on its own; the field tells you to open the same URL in a normal tab and press F12.
- **Only two fields are required**, plus a checkbox confirming this is not a security report. The audience here is streamers, and a form that demands a commit SHA and a minimal reproduction is a form that gets abandoned.
- **Security reports are routed away from the issue tracker** in three places: the intro text, a contact link that sends you to `SECURITY.md`, and that required checkbox. `SECURITY.md` asks for private disclosure and an issue form is exactly where someone would ignore it by accident.
- **The idea form asks what you were trying to do on stream** before it asks what you want built, and asks how you work around it today. The workaround is frequently the smaller feature.
- **The PR template is a checklist for me**, since I am the only person opening pull requests here. It pins the changelog entry, the migration rollback, and the two rules I break most often: em dashes in user-facing copy, and saying "Fork" where the UI says "Copy".
- **No licence file yet.** `composer.json` still claims MIT from the Laravel skeleton while the repo has none, which is a contradiction rather than a missing checkbox, and it is a product decision that deserves its own sitting rather than a drive-by fix.

## August 8th, 2026 - fix(previews): control tags resolve in previews

`getSampleTemplateData()` returns 53 keys and every one of them is a static Twitch tag. There is not a single `c:` key in it, and there never was, so no control has ever resolved in any preview on any surface. `[[[followers_total]]]` worked and `[[[c:myname]]]` did not, which is the entire pattern behind "control-heavy overlays show basically nothing in preview".

- **The values were already on the page.** The edit route ships `userScopedControls` and the template's own `controls` for the controls manager; the Builder holds `BuilderControlDef`s for the blocks placed in this session. None of it reached the preview, which received `sampleData` and nothing else. No new endpoint, no new query - the bag handed to the renderer now carries `c:` entries built from data the page already had.
- **Key derivation mirrors the render query exactly**, because a preview that resolves a different set of keys than the overlay is worse than one that resolves none. Source-managed controls use the namespaced broadcast key (`c:kofi:donations_received`), everything else uses `c:` plus the raw key, and each gets its automatic `_at` companion in Unix seconds. Membership matches too: every template-scoped control, plus user-scoped ones that are source-managed - which is what `userScopedControls` already filters to.
- **Timers are computed rather than read**, since their stored `value` is not the number on screen. That logic already existed as `resolvePreviewValue()` inside `ExpressionBuilder.vue`; it moved to `controlPreview.ts` and the modal now imports it, because leaving a second copy behind is what the previous commit was about.
- **Random-mode numbers deliberately return their stored value** instead of rolling a fresh one. The server rerolls on each render, but a canvas whose numbers change on every keystroke reads as broken.
- **Builder control definitions carry no timestamps**, so their `_at` companion is left absent rather than faked to `now()`. An overlay that has never been saved has no last-write time and should not claim one.
- **Verified against real local data**, rendering template 232 with its own controls: `[[[c:bb_block_contents]]]` went from empty to "Hello Jasper" and `<img src="">` became `<img src="https://jasper.monster/sharex/...">`.

## August 8th, 2026 - fix(previews): previews render through the same pipeline the live overlay uses

Every preview surface had its own tag substituter, and all three were the same twelve lines copied around: loop over the sample data, build a literal regex per key by string interpolation, replace. `resources/dsl/dsl.json` opens with a comment forbidding exactly this, and the reason is on display here - a regex built as `[[[${tag}]]]` can only match a bare tag, so `[[[followers_total|number]]]` never matched its own sample value even though the value was sitting right there in the same object.

- **What happened to a tag that did not match depended on which preview you opened.** The Builder canvas and the composed-overlay preview both followed up with a catch-all `replace(/\[\[\[[^\]]*]]]/g, '')`, which deleted it. The overlay create page did not, so the same tag stayed on screen as raw `[[[c:bb_block_contents]]]`. One bug, two symptoms, and the comment in `BuilderPlacement.vue` claiming it used the "same preview approach as the template create page" was close enough to stop anyone looking.
- **The catch-all strip also ate the block syntax**, which is the part that made control-heavy blocks look broken rather than merely empty. `[[[if:...]]]BIG[[[else]]]small[[[endif]]]` had its markers removed and rendered `BIGsmall`; a `foreach` rendered its body exactly once with the loop markers gone. Neither construct was ever evaluated in a preview.
- **All three now call `renderTemplateSource()`**, a small util that runs `processTemplate` then `replaceTagsWithFormatting` - the same two passes, in the same order, that `OverlayRenderer.parseSource()` runs. Previews cannot drift from what OBS shows, because there is no second implementation left to drift.
- **Deleting the strip is not a regression.** `replaceTagsWithFormatting` already resolves an absent value to `''`, so unmatched tags still vanish. They now go through `?? default` on the way, so `[[[c:donations ?? nothing yet]]]` shows its fallback instead of being erased along with everything else.
- **Locale reaches previews for the first time.** It comes off `auth.user.locale`, which `HandleInertiaRequests` already shares, so `currency` and `date` and `number` format the way that user has them set rather than not formatting at all.
- **Verified against the real modules rather than by reading them**, bundling the util with esbuild and running it in node: pipes resolve (`1,234`), `??` fires, both conditional branches select correctly, `foreach` emits one node per item, HTML values stay entity-encoded, and the CSS path stays unencoded so `.a > .b` survives.
- **`useHelpReference.ts` keeps its own tag regex on purpose.** It wraps literal tags in clickable `<code>` for the help pages, where resolving them is precisely what you do not want.

## August 7th, 2026 - feat(dashboard): a welcome card, so the dashboard opens with a greeting instead of four lists

The dashboard was four boxes with a list in each. Functional and clean, and not remotely inviting. There is now a card above them holding your avatar, your name, and one tile each for the four things underneath: static overlays, alerts, recent events, recent updates.

- **The tiles are the same four destinations the section headers already point at**, built from the same `route()` calls with the same parameters. Nothing new is reachable from here; it is the existing dashboard, said once at the top in a form you can hit without reading four headings first.
- **Each tile takes a different colour from the button palette**, which is the whole reason the card works. Four identical violet tiles would have been tidier and would have made you read all four labels every time. The colours are `overlabels-buttons.css` classes used as a palette rather than for their action semantics, which is noted in the component so nobody later "fixes" `btn-cancel` on the events tile into something that cancels.
- **Dismissable, and it remembers per device.** The state lives in `localStorage` under `overlabels:welcome-card-dismissed`, read synchronously during setup so a card you dismissed does not flash on screen before hiding itself. Storage failures (private mode) are swallowed and the card simply shows.
- **Dismissing leaves a small "Show welcome" button** where the card was, right-aligned above the grid. Dismissing something permanently with no way back is how a dashboard ends up with a feature nobody can find again.
- **The component owns both states rather than the page**, so `dashboard/index.vue` gained one tag and one import. The restore button has to appear exactly where the card was, and threading that through the page would have split one decision across two files.

## August 7th, 2026 - docs(help): what the `_at` companion actually holds

Section 8 of the math page called `_at` "the timestamp of its last change". It is `$control->updated_at`, so it moves on any write at all - including provisioning a control you have never received on. "Last write" is what it means, and the page now says so in both places it comes up.

- **The wording is not pedantry, it is the whole basis of the race.** `latest()` picks a winner by comparing these timestamps, so whether they mean "last change" or "last write" is the difference between the documented pattern working and returning something you did not expect.
- **The never-connected case is now stated explicitly**, because it is the reassuring half and it was missing: a service you have not wired up resolves to nothing, `toComparable()` maps that to \(-\infty\), and it loses every race. You can list all six services in a `latest()` call before connecting any of them.

## August 7th, 2026 - docs(help): the math page described a ticker that never existed

The Math Engine page said the engine runs "a shared 250 ms ticker" that fires "4x a second". It does not, and it never did. Time-dependent expressions have been driven by `requestAnimationFrame` since May 2nd, and the math page was written on July 28th - so this was not documentation drifting behind a change, it was wrong on the day it was authored. The 250 ms figure appears to have been borrowed from `OverlayRenderer.vue`, where **timer controls** genuinely do tick on a 250 ms interval. Different subsystem, same overlay.

- **The mechanism was wrong but the advice was right, so only the mechanism moved.** `now()` really does produce 1-second granularity - not because it is ticked slower, but because it returns integer seconds and `tickFrame` skips the write when the result string is unchanged. It is re-evaluated ~60 times a second and writes once. That distinction is now stated instead of being papered over with a fictional tick rate.
- **The 1000x rescaling trap is documented for the first time**, and it is the thing that actually bites. Swapping `now_ms()` for `now()` in a working formula does not slow it down, it produces a different wave: `sin(now_ms() / 600)` cycles every 3.8 seconds, `sin(now() / 600)` cycles every 63 minutes. The page told people to reach for `now_ms()` for sub-second motion and never mentioned that the divisor has to move with it.
- **"Prefer CSS animations, they run at the browser's frame rate" was true and is now false**, since the expression ticker runs at frame rate too. The recommendation survives on its real merit - compositor, no data writes, no re-render - rather than on a speed claim that stopped being true.
- **Two arithmetic errors went with it.** `sin(now_ms() / 500)` was labelled a "~3 Hz wave"; it advances 2 rad/s, so it is a 3.1 second period, about 0.32 Hz. And the pseudo-random one-liner in section 5 was described as changing "twice per second" - `now()` cannot change anything twice per second.
- **The engine's own address was stale.** The closing line still pointed at `resources/js/composables/useExpressionEngine.ts` as the whole engine; the evaluator moved to `resources/js/lib/expression-engine/engine.mjs` when the Node sidecar landed, and the composable is now a Vue wrapper around it.
- **A new subsection records that the editor preview does not tick at all.** `ExpressionBuilder.vue` re-evaluates on text change, nothing else, so every time-based formula looks frozen in the preview regardless of which function it uses. Toggling a space to force an update is a real technique and now it is a documented one.

Then the same audit found that the animation examples themselves were built on `now()`, which is the one thing the corrected section 10 says not to do. Nine formulas across sections 3, 4, 6 and 9 now use `now_ms()` with divisors scaled to milliseconds.

- **The headline example was a dead formula.** `0.5 + 0.5 * sin(2 * PI * now())`, presented as a "1 Hz pulse" and the first animation anyone would copy, is a constant `0.5`. `now()` is always an integer, \(\sin(2\pi n)\) is always zero, so the pulse never pulses. It sat on the page as a worked example of the page's own central technique. It is now `now_ms() / 1000` and genuinely oscillates 0 to 1 every second.
- **The rest moved because they were staircases, not waves.** The 6-second breathe had six distinct values per cycle, the Lissajous orbit five, the 4-second triangle four. They animated, technically, in the sense that a flipbook with four pages animates.
- **The two-second fade-in needed a unit conversion, and that conversion is now called out.** `_at` companions are Unix *seconds*, so `now_ms() - x_at` is off by a factor of 1000 and lands around 1.7 billion, which `clamp` pins to `1` - a fade that is permanently finished, failing silently and looking like nothing is wrong. The example multiplies the `_at` by 1000 and the page explains why.
- **The discrete examples were deliberately left alone.** The modulo wheel, the quantised `floor(now() / N)` random rolls, and the elapsed-time subtractions are all correct uses of `now()` - they want one value per second, which is exactly what it gives. Converting those would have been churn.
- **Every converted formula was executed against the real engine** with `Date.now()` stubbed, sampled across a full cycle, and checked for range, distinct-value count and return-to-start. The 1 Hz pulse went from one distinct value to eight.
- **The new cross-reference anchor was verified against the renderer, not assumed.** `addHeadingAnchors()` strips the leading section number, so the id is `pitfalls-and-things-that-will-not-work`, not `10-pitfalls-...` - which is what both a standard markdown slugger and the IDE's link checker expect. The IDE still flags it; the IDE is wrong.

## August 7th, 2026 - fix(controls): your latest cheerer stopped being erased at every go-live

`c:twitch:latest_cheerer_name` was wiped to a literal `"0"` the moment a stream started, and its 25 equivalents across Ko-fi, Streamlabs, Fourthwall, Buy Me a Coffee and Throne were not. Same idea, opposite lifecycle, and the only thing separating them was which code path created the row.

- **Nobody ever decided this.** `resetControls()` arrived in March with six presets, every one of them named `*_this_stream`. Resetting them *is* the feature and it was exactly right. Five weeks later the `channel.cheer` presets landed - explicitly "so bits payloads can drive overlays the same way donations do" - and the three `latest_cheer*` ones inherited the reset purely because they share `source='twitch'`. The filter predated the controls it was erasing.
- **So the reset broke the one thing that commit set out to build.** Bits parity with donations was the stated goal, and `latest_donor_name` persisting while `latest_cheerer_name` did not is precisely the parity failing. It stayed invisible for four months because you only notice when you race the two against each other.
- **The reset is now scoped by key, not by source.** The eight `*_this_stream` counters still reset at go-live, because that is the only thing implementing what their labels promise - they increment additively and nothing else ever zeroes them. The three `latest_cheer*` controls no longer do. A key earns its place on the list only if its label promises per-stream scope.
- **This is the pattern the GPS integration already used.** Its reset takes an explicit key list and deliberately leaves cumulative `distance` alone behind a separate manual button. The twitch path was the one place resetting by source with no filter.
- **`latest()` racing across services now behaves as documented.** Twitch was the only competitor whose `_at` moved at go-live, so it won every race at stream start holding `"0"`. Nothing about `latest()` changed - it was reporting the freshest write, correctly, the whole time.
- **You may notice this on your next stream.** An overlay that went blank at go-live and stayed blank until someone cheered will now keep showing the previous cheerer. That is what the label says it does, but it is a visible change rather than a silent one.
- **Nine tests pin it**, and the three that assert the new behaviour were verified to fail against the old filter. The rest cover the scoping that was already right - other users, other sources, and user-created controls that merely share a key name - so a future change cannot widen the blast radius unnoticed.

## August 7th, 2026 - fix(deps): Dependabot could not update anything, and had been failing silently

Dependabot tried to ship a security update for `league/commonmark` and died with "Your requirements could not be resolved to an installable set of packages". The interesting part is that it had nothing to do with commonmark, and it would have broken every future composer security update the same way.

Dependabot injects its own `config.platform.php`, and set it to `8.4` - which composer reads as **8.4.0**. That number is the floor of our `require: php ^8.4`. Meanwhile `nunomaduro/collision` pulls `symfony/console` v8.1.1, which requires `php >=8.4.1`. So resolution failed on a constraint three packages away from the one being updated, before commonmark was ever considered.

- **The PHP constraint was simply false.** `^8.4` claimed the app runs on 8.4.0. It cannot - symfony/console needs 8.4.1. Local dev is on 8.4.24 and prod is a FrankenPHP 8.4 image, so nothing ever surfaced it. `require.php` is now `^8.4.1`, which is the truth.
- **`config.platform.php` is now pinned explicitly to 8.4.1.** Dependabot's own error says "overridden via config.platform", so it honours one that already exists - that is the lever that actually fixes the automation. It also makes resolution identical on a laptop, in CI, and in the Docker build, rather than silently depending on whichever PHP the `composer:2` image happens to ship that week.
- **The six advisories were real but not reachable.** All denial-of-service via crafted markdown, five high and one medium, published the day before. Both CommonMark call sites read from `resource_path('help/reference')` and `resource_path('help/pages')` - repo files, authored by us. No user-supplied markdown reaches the parser and there are no markdown mailables, so exploiting it required commit access. Worth fixing, not worth panicking, and Dependabot being broken did not leave anything exposed.
- **The update moved exactly one package.** `league/commonmark` 2.8.3 -> 2.9.0, no installs, no removals, nothing else touched. `composer audit` now reports no advisories, and the suite passes 1233.

## August 7th, 2026 - feat(ops): a backup that never runs is now louder than one that fails

The Discord webhook only ever answered one of the two questions. It fires when the backup runs and fails. It cannot fire when the backup never runs at all, because the thing that would send the message is the thing that is down - and silence from a dead scheduler is indistinguishable from silence after a perfect night. Healthchecks.io alerts on the absence of a ping, which is the only shape of check that works when the failure mode is "nothing happened".

- **It is three lines, because Laravel already had this.** `pingOnSuccess()` and `pingOnFailure()` are built into the scheduler. No new command, no new service, no HTTP client of our own - the entire feature is a conditional on the existing schedule entry.
- **The ping hangs off the schedule, not the command, and that distinction is the whole point.** Attached to the command, a manual `php artisan backup:database` would satisfy the switch and reset the timer - so the one thing the switch exists to catch, a scheduler that has quietly stopped firing, could be masked by a human doing the backup by hand. On the schedule, only the scheduler can feed it.
- **Failures ping `/fail` rather than just staying quiet**, so a broken backup flips the check immediately instead of waiting out the 30-minute grace period. Healthchecks and Discord now agree rather than reporting on different clocks.
- **A Healthchecks outage cannot fail a backup.** `Event::pingCallback()` already catches transport exceptions and reports them rather than throwing - checked in the framework source rather than assumed, because a monitoring tool that can break the thing it monitors is worse than no monitoring.
- **The tests drive the real callbacks.** A recording HTTP client is bound in place of Guzzle and the event's success and failure paths are invoked with actual exit codes, so they assert the URLs genuinely requested rather than that some callback happens to be registered. Both were verified to fail with the ping block disabled.
- **An empty `HC_PING_URL` registers no pings at all**, so nothing breaks in dev or CI, and `rtrim()` guards the one input mistake that would 404 every failure ping: a trailing slash on the env var.

## August 6th, 2026 - docs(ops): the backup stopped being a guess

The nightly job fired on its own at 03:00 UTC, and the object it produced was pulled back out of R2 and restored into local dev. Zero errors, 54,061 rows across 61 tables, and then the actual application ran on top of it: Twitch login, templates and controls, a static overlay authenticating off its URL-fragment token, and a live follower alert arriving through EventSub and Reverb. Production was untouched - EventSub subscription count identical before and after.

- **The doc now records that, because "no restore test" had become false in both directions it claimed.** The R2 read path and the restore had never been exercised when that line was written; now they have, with the specifics worth re-checking against: row-for-row parity with every `COPY` block, 55 sequences ahead of their table's max id, 71 foreign keys validated, 0 pending migrations.
- **The remaining gap is stated more precisely than "manual".** Nothing detects a dump that uploads cleanly and will not restore. The nightly run proves the write path every night and proves nothing at all about the read path, which is the half you need on the worst day.
- **It names the three changes that should trigger redoing it**: the dump flags, the pinned client major, and the `r2` disk config. Each can break a restore while leaving the nightly run looking perfectly healthy, which is exactly the failure mode this whole exercise exists to catch.

## August 6th, 2026 - refactor(ui): five list designs for one idea

`TemplateTable` rendered no table. It was a table once, the contents got rewritten, the name stayed. Next to it sat `TemplateList`, which was that component copy-pasted and drifted. `UpdatesList` was a third copy of the same row. And `/triggers` and `/dashboard/lists` had each invented their own list from scratch, with their own borders, their own hover, and in the case of Lists no `:active` state at all. Five designs, one idea.

There is now one `CollectionList`, and everything that renders a collection of rows goes through it.

- **`.overlabels-background` was already the house row skin** - left accent bar, violet on hover, gradient wash on press - and four of the six surfaces already used it. The two that reinvented themselves simply hadn't found it. It is renamed `.collection-row`, which is what it is, and it no longer carries padding: every caller was overriding that anyway with a different value, which is how one skin produced four densities.
- **Rows navigate by stretched link.** The row is a plain container with an absolutely positioned `<Link>` covering it. That is strictly better than either pattern it replaces: `TemplateTable` used `role="button"` on a div with `router.visit`, which gives you no middle-click, no ctrl-click, no "open in new tab" and no "copy link address"; `TemplateList` nested its dropdown button inside the `<a>`, which is invalid HTML and meant clicking the kebab also navigated. Action buttons now just sit above the link on `z-10` instead of stopping propagation.
- **Actions stay visible below `md`.** They were `opacity-0` until hover, and a touch device has no hover, so on a phone the kebab menu was unreachable on every template row.
- **Merging the two template components resolved four behaviour bugs**, all of them cases where the copy had drifted from the original. `TemplateList` offered Delete on kit-bound templates the server rejects. It said "Fork template", which CLAUDE.md forbids in frontend copy. Its "Preview (inline)" and "Preview (new tab)" pointed at the same URL. And its "Copy link for OBS" copied a URL ending in the literal string `YOUR_TOKEN_HERE`. The merged `TemplateCollection` keeps the correct behaviour from each side, including the copy-confirmation checkmark, which only the list had.
- **The external event label was reading "bmac: donation".** It came from a hardcoded map covering Ko-fi and Streamlabs, written before the other three donation services existed. It now derives from `SERVICE_LABELS`, the thing that already exists for this, so Throne and Buy Me a Coffee read like Ko-fi always did and the sixth integration needs no edit here.
- **`/triggers` rendered its Twitch rows and its external rows as two copies of the same 30 lines.** They are one `v-for` over sections now. A shadowed trigger takes the accent bar amber rather than adding a second border in a different colour system.
- **Four hand-rolled empty states became `EmptyState`**, which also already existed.

`EventsTable` and `ControlsManager` keep their own row markup - they wrap each row in a popover and a collapsible respectively, so they are not the same shape - but they were already on the shared skin and still are, so they move with it.

## August 6th, 2026 - fix(routing): the Triggers page was called three different things

The nav said "Triggers". The URL said `/alerts`. The Inertia component said `events/index`. Three names for one page, and the only way to know they were the same page was to have written it.

- **The URL is `/triggers` and the route is `triggers.index`.** `events.index` was the worst of the three names, because `admin.events.index` is a genuinely different page - the raw event feed - so grepping for "events.index" returned two unrelated things.
- **The page folder is `resources/js/pages/triggers/`.** Moved with `git mv`, so the history follows it.
- **`/alerts` still resolves**, as an unnamed 301 to `/triggers`. Bookmarks and tabs left open in OBS keep working. It is unnamed on purpose: nothing should link there, and Ziggy skips unnamed routes, so it cannot be reached from the frontend by accident.
- **Five integration settings pages linked to "Alerts Builder"**, hardcoded as `href="/alerts"` rather than through Ziggy - which is why the rename would have broken them silently. They now say "Triggers" and point at `/triggers`, matching what the nav item is actually called.

## August 5th, 2026 - fix(docs): the restore procedure named a psql that cannot read our own dumps

Verifying the first real backup - by inspecting the `.sql` rather than restoring it - turned up two things the docs asserted confidently and wrongly.

- **The restore procedure pointed at psql 17, which aborts on our dumps.** Every dump pg_dump 16.14 writes now opens with `\restrict` and closes with `\unrestrict`, a psql meta-command added in PostgreSQL 18 and backpatched only as far as 17.6, there to stop a malicious object name smuggling psql commands into a restore. The local client is 17.5. By default it prints `invalid command \restrict` and carries on, restoring fine but with the guard silently inactive; under `ON_ERROR_STOP=on` it aborts with exit code 3. The procedure now uses the psql 18 binaries and sets `ON_ERROR_STOP=on` explicitly, so a partial restore is loud rather than a database that looks fine and is missing a table from the middle.
- **The Dockerfile justified its PGDG repo with the wrong operating system.** The comment claimed Debian bookworm ships only client 15. The FrankenPHP base is Debian 13 (trixie), which ships client 17 - it would have dumped a 16 server unaided, so the PGDG repo was never load-bearing the way the comment said. It stays, because pinning the client major explicitly beats letting it drift with the base image, but the comment now says that instead of inventing a constraint. The `$VERSION_CODENAME` lookup turns out to have been the part actually doing work: a hardcoded suite would have pointed a bookworm repo at a trixie system.

The dump itself checked out: completion marker present, 61 tables with 61 `COPY` blocks, 92 indexes, 71 foreign keys, zero `OWNER TO` or `GRANT` statements, and row counts identical to production on every table that does not grow by itself.

## August 5th, 2026 - feat(ops): production had no database backup

Linode's weekly VM snapshot was the only copy of production, and a VM snapshot is not a database backup. It is an image of a running disk taken while Postgres was mid-write, it restores as a whole machine or not at all, and on a bad day it is six days stale. Fifty-two megabytes of users, templates, overlays, controls and stream history, with a week-long worst case.

There is now a nightly `pg_dump` to a Cloudflare R2 bucket in the EU, at 03:00 UTC.

- **It adds no infrastructure.** The scheduler role already runs `schedule:run` every 60 seconds and already has `DB_HOST` and `DB_PASSWORD` injected, so the whole thing is one artisan command and one `Schedule::command()` line. No host cron to provision by hand, no fourth accessory container, nothing that lives outside the repo and has to be remembered.
- **The Dockerfile now pulls `postgresql-client-16` from PGDG rather than Debian.** `pg_dump` refuses to run against a server newer than itself, prod is Postgres 16.13, and Debian bookworm only ships client 15 - so the obvious `apt-get install postgresql-client` would have produced a backup system that fails on its first night with a version-mismatch error. The signing key is stored ASCII-armored and referenced with `signed-by`, which apt reads directly, so the runtime image does not need gnupg.
- **`--no-owner --no-privileges` is what makes the dump restorable.** Without them every statement references the `overlabels` role, and a restore into local dev - which runs as `postgres` - aborts on the first unknown role. The whole point of the file is the day you need to read it back, on a machine that is not the one that wrote it.
- **A dump under 10 KB is treated as a failure.** The schema alone is several hundred KB. The failure this guards against is not a crash, it is `pg_dump` exiting 0 having written nothing useful, which gives you thirty days of empty files and no idea until the day it matters.
- **The upload is verified by reading the object size back**, not by trusting the write. That is the difference between "we uploaded something" and "the backup is there". The `r2` disk is also the only one in `filesystems.php` with `throw => true`; the user-facing disks return false on failure, and a silent false here would report a failed upload as a successful backup.
- **Retention is an R2 lifecycle rule, not code.** Thirty days, set on the bucket. Deletion logic that runs nightly against a bucket full of backups is a thing that can have a bug, and its bug deletes backups.
- **The bucket is EU jurisdiction, and that is load-bearing in a non-obvious way.** It is what keeps the objects physically in the EU, and it is also baked into the S3 endpoint hostname - an EU bucket answers on `<account>.eu.r2.cloudflarestorage.com` and returns 403 on the plain host. That 403 looks exactly like a bad credential, so `R2_JURISDICTION` is pinned in `deploy.yml` with a comment saying to check it before rotating any keys.
- **The dump is deliberately not encrypted before upload.** EU jurisdiction plus Cloudflare's DPA and SCCs plus R2's own at-rest AES-256 is a defensible position without client-side encryption on top. The decider was the other direction: a passphrase living only in GitHub secrets is a single point of failure, and it fails by turning every historical backup into unrecoverable noise at the exact moment you need one.
- **Failures shout at Discord**, via `BACKUP_ALERT_WEBHOOK_URL`. Best-effort and optional - an unset webhook logs instead, and a webhook that itself fails cannot mask the backup failure underneath it.
- **The tests decompress the uploaded object and assert it contains real SQL.** Checking that a file of roughly the right size arrived would pass for a gzipped error message. The three end-to-end tests run a genuine `pg_dump` where the binary is available and skip where it is not, so CI exercises the real pipeline; the failure-path tests run everywhere, because a missing binary is itself a case the command has to survive.
- **`docs/deploy/database-backups.md` covers the restore**, including the four things that are supposed to break when prod data lands locally - `APP_KEY` mismatch on encrypted columns, external webhooks pointing at overlabels.com, overlay tokens stored as `sha256(plainToken)`, and EventSub subscriptions registered against the prod callback - so none of them get "fixed" by copying a production secret onto a laptop.
- **One gap is documented rather than papered over.** A backup that fails shouts; a backup that never runs at all is silent. Closing that needs an external pinger, which is another third party, and it was not worth it on day one.

## August 5th, 2026 - docs(reference): Buy Me a Coffee had no tag reference at all

Every other integration has a page under `/help/reference/eventsub-tags` listing the tags you can use in an alert template. Buy Me a Coffee, which emits more tags than any of them, had none. Seventeen tags, nowhere to look them up, which makes writing a BMAC alert a guessing game against whatever a test webhook happens to show you.

- **The new page documents all seventeen, read off the driver rather than off a sample payload.** A test event only shows you the fields that happened to be populated - `commission_name` and `wishlist_title` are empty strings on a plain donation, so a payload-derived list would have quietly omitted the tags that are hardest to guess.
- **`event.message` is not what the supporter wrote, and that needed saying loudly.** Buy Me a Coffee sends its own generated description ("John bought you a coffee") as the message, while the supporter's actual note arrives separately as `event.support_note`. `support_note` is what fills `c:bmac:latest_donation_message`. Anyone writing an alert reaches for `event.message` first and gets boilerplate. The page now has a Message Tags section that exists purely to head that off, including the detail that a supporter marking their note private (`note_hidden`) makes it come through empty on purpose.
- **Ko-fi's page was wrong about `event.source`.** It documented the value as lowercase "kofi"; the driver emits "Ko-fi". So `[[[if:event.source = kofi]]]` never matched, and the failure mode is a conditional that silently renders nothing. `event.is_shop_order` was also missing entirely.
- **Three Ko-fi pages linked each other by title instead of slug.** `[[All Ko-fi Events]]` does not resolve - the renderer looks up `all-ko-fi-events` - and an unresolved wikilink degrades to inline code rather than erroring, so they had been rendering as plain grey text rather than links for as long as they have existed.
- **Throne needed nothing.** Its page already covered all eleven tags accurately, including the minor-units division on `event.amount`. Checked against a real test gift rather than assumed.
- **Two tests now enforce this.** One asserts every `event.*` tag in each donation driver appears somewhere in the reference; verified to fail when the BMAC page is moved away. The other asserts every wikilink in the vault resolves to a real slug, since that class of rot is invisible by design. Obsidian attachment embeds are skipped, being file references rather than page links.
- **Overlabels GPS got its page too**, so the coverage test now has an empty exclusion list. It documents position, speed and device state rather than the donation six, carries a warning that the Android app is still in development and not in the Play Store, and states plainly that `event.latitude`/`event.longitude` put your physical location on stream - with an example that gates the whole block behind a boolean control you can flip mid-stream.
- **Two GPS quirks are documented rather than quietly left to be discovered.** It is the only integration with no `[[[event.type]]]` tag, so `[[[if:event.type = location_update]]]` never matches. And its event tags disagree with its controls on the position keys: `event.latitude`/`event.longitude` versus `c:gps:lat`/`c:gps:lng`.

## August 5th, 2026 - fix(integrations): connecting Ko-fi, BMAC or Throne gave you no controls at all

Three of the five donation integrations never provisioned anything. You connected Throne, the webhook worked, signatures verified, events landed in `external_events` - and there was nothing to read them from. The overlay render payload is built from control rows, so `[[[c:throne:latest_donor_name]]]` resolved to nothing, because the row had never been created. Same for Ko-fi and Buy Me a Coffee. Only Streamlabs, Fourthwall and GPS called `provision()`.

This was found while trying to fix a documentation bug. The generated reference said "Throne provisions 9 controls when you connect it", which was false, and the reason it was false is that the generator reads the driver while the decision lived three layers away in each settings controller. Nothing forced the two to agree.

- **The fix is deletion, not a flag.** The first instinct was a `provisionsOnConnect(): bool` on the driver contract so the generator could tell the truth per service. That is making the lie configurable. The principle is that connecting a service gives you its controls, uniformly, so the honest fix is one call site that every connect flow routes through.
- **`DonationIntegrationController` now owns `show()`, `setTestMode()`, `seedDonationCount()` and `disconnect()`.** Subclasses supply a service key and their connect flow, nothing else. 1,108 lines became 709 including the new base class. The five copies of `seedDonationCount()` were byte-identical between Ko-fi and Streamlabs and spelled the service key three different ways across the rest (`self::SERVICE_KEY`, `self::SERVICE`, a bare literal) - which is the same drift that produced the provisioning gap, just cosmetic instead of load-bearing.
- **`provision()` is called on every connect, not just the first.** It is documented idempotent and does not overwrite existing controls, so the `if ($isNew)` guard bought nothing and cost something: a driver that gains a control later now picks it up on the next reconnect instead of silently never appearing for existing users.
- **Fourthwall rolls controls back with the row.** Its callback deletes a fresh integration if webhook registration fails. Provisioning now happens on the way in, so that rollback had to deprovision too, or a failed first connect would strand six service-managed controls that nothing can write and the user cannot delete.
- **A migration backfills anyone already connected.** The fix only helps people who connect again; existing integrations keep an empty control list otherwise. Locally that was BMAC at 0 controls and Throne at 0. Idempotent, so a user who had added some by hand from the presets modal keeps their values and gains only what was missing.
- **`IntegrationProvisioningTest` is the test that did not exist.** The whole suite passed before and after the fix, which is precisely how three broken integrations stayed broken. Six of its assertions were verified to fail with the `provision()` call commented out. It also asserts structurally that every donation service's show route resolves to a `DonationIntegrationController` subclass, so integration number six cannot reintroduce this with a hand-rolled controller.
- **Provisioning and authoring are different things, and CLAUDE.md had them merged.** It recorded "Ko-fi: NO auto-provision on connect - user explicitly adds from ControlFormModal" as a decision. The modal part is true and is about writing a template; the provisioning part was a bug wearing a decision's clothes. Controls are user-scoped, so once the row exists the tag works in every overlay you own with no per-template setup - the modal only helps you discover which keys exist.

## August 4th, 2026 - docs(readme): the README documented 2 of 5 donation integrations

Opened the README expecting to strip StreamElements out of it and found there was nothing to strip - the External Integrations section never covered it. What it did cover was Ko-fi and StreamLabs, and nothing else. Fourthwall, Buy Me a Coffee and Throne have all shipped since that section was written, so the front door of the repo was describing 2 of the 5 donation services that actually exist.

- **The shared six-key schema is stated once, up front.** Writing out the same six-row table five times would have tripled the section for no information gain. Stating it once and letting each service list only what it adds beyond the six is also the honest shape of the thing - the portability of a template between services *is* the feature, and a repeated table buries it.
- **The auto-provision claim was wrong in the first draft and got caught before commit.** "All five provision six controls on connect" reads plausibly and is false: only StreamLabs and Fourthwall call `provision()` on connect. Ko-fi, BMAC and Throne rely on the user adding controls from presets, and `applyUpdates()` only ever updates controls that already exist - it never creates one. A reader following the old wording would have connected Throne, seen no controls appear, and concluded the integration was broken.
- **BMAC and Throne get their extras documented with the conditional that makes them useful**, rather than as bare table rows: `latest_support_type` driving a per-type alert, and `latest_is_surprise_gift` working with the truthiness form of `[[[if:]]]` because `"0"` is falsy under the rules the README already states two sections earlier.
- **The self-hosting env block now distinguishes OAuth from per-user auth.** It listed only the StreamLabs variables, which implied the others needed something. Fourthwall's five `FW_*` variables were missing, and Ko-fi, BMAC and Throne need no environment configuration at all because they authenticate per-user with a token or a signature. Worth saying explicitly - "nothing to configure" is information.
- **The tip line dropped StreamElements**, which was the one and only mention of it in the file, and the one place the removal actually did reach the README. Ko-fi only now.

## August 4th, 2026 - chore(integrations): StreamElements is gone

Razer, which acquired StreamElements, put up an accept-or-delete-your-account dialog for a new privacy policy. Buried in it is a clause claiming ownership of user-submitted and user-generated content, including the right to hand it to third parties. That is not a thing Overlabels wants to route a streamer's donation data through, so the integration is removed rather than deprecated.

Being honest about what this is: Razer will not notice, and no data is protected by the removal - Overlabels pulled tips *out* of StreamElements, it never sent anything in. It is a statement about what this project is willing to maintain. The reason to make it now rather than later is that it costs nothing now: zero users were connected, so nobody's overlay breaks. In a year it would have broken real people's setups.

- **The whole surface went, not just the connect button.** The driver, the settings controller and page, the five settings routes, the internal integrations endpoint, `streamelements-listener.mjs` with its Dockerfile and GHCR build step, the Kamal accessory, and `STREAMELEMENTS_LISTENER_SECRET` in all four places it was declared. The settings integrations list is built from `ExternalServiceRegistry`, so dropping the registry entry removed the card with no separate edit.
- **A migration purges the data**, and it has to. Service-managed controls answer 403 to `setValue()` and `update()`, so six undeletable, permanently stale controls per connected user would have sat in the dashboard forever with no driver left to write them. The encrypted JWTs go too - keeping the credential while dropping the integration would be the wrong half of the decision. It is deliberately irreversible; `down()` cannot invent a JWT it never had.
- **The reference pages regenerated themselves.** `help:build-integration-controls` reads the registry, so `streamelements.md` was deleted and `all-integration-controls.md` rewritten by running the command, which is exactly the property that was built in last week. The hand-written `streamelements-tip-event-tags.md` and its 301 redirect were removed by hand, redirect included - pointing a preserved URL at a page that no longer exists is worse than letting it 404.
- **Copy that counted the services was recounted.** Six donation services became five (Ko-fi, Streamlabs, Fourthwall, Buy Me a Coffee, Throne), including "six pipes" in the `latest()` section and the three meta descriptions on the homepage. The code sample under it loops `$latestServices`, so it dropped a line on its own.
- **Comparative mentions stay.** "StreamElements-style widget" in `EventFeedAppender`, "other bots (StreamElements, Nightbot, Fossabot)" in the lists help, and the line in the integrations section about every overlay tool being owned by a donation platform are all describing the market, not advertising an integration. The last one arguably reads better now.
- **Twitch is not the same case, before anyone asks.** Twitch is the substrate; there is no product without it. StreamElements was one of six interchangeable donation sources, and declining an optional integration is a choice that exists.

## August 4th, 2026 - fix(templates): Add to OBS is a main tab now, not a code editor tab

The Builder replaces the Code tab wholesale for overlays composed from blocks, which is the point of it. What went unnoticed is that Add to OBS lived physically inside the code editor, as the fifth entry in its vertical HEAD/BODY/CSS/TW3 strip. Overriding the Code tab took the browser-source URL with it, so a Builder overlay could be composed, saved and previewed, and then had no way at all of reaching OBS.

Add to OBS is now the last main tab on both the overlay page and the edit page, alongside Details and Controls, where it no longer depends on which editor is rendering underneath it.

- **The panel is one component now**, `components/templates/AddToObsPanel.vue`. The heading, the prose, the "you are adding an alert directly to OBS" warning and the button existed as two near-identical copies, one in show.vue and one in TemplateCodeEditor.vue. Moving it was the moment to stop having two.

- **Digits still map to tabs, and now they map to all of them.** The edit page runs to ten tabs on an alert overlay, so 1-9 select the first nine and 0 takes the tenth. The old loop stopped at 8 while alerts already had nine tabs, which meant Effects had no key; it does now. The overlay page tops out at seven.

- **Blocks still do not get the tab.** A block is a Builder ingredient, not a standalone overlay, so there is no browser source to add. Same gate as before, moved with the panel. On the overlay page it stays owner-only for the same reason as always: the URL carries a user-scoped token.

- **The button is `type="button"` now.** It sits inside the edit page's form, where a bare button is a submit button, so clicking Add to OBS opened the dialog and saved the overlay at the same time. That was true in its old home too and was easy to miss, because the save happened behind a modal that had just told you to look away from your stream.

- **TemplateCodeEditor lost its `template` and `templateType` props.** They existed only to feed the OBS tab, and create.vue never passed them, which is why the tab was already absent when creating an overlay.

## August 4th, 2026 - feat(moderation): public overlays can be reported

Every public overlay had a share URL, an OpenGraph card and a copy button, and no way at all to tell anyone something was wrong with it. The gallery is user-generated content served from our domain, so the absence of a report path was the gap.

There is now a Report button on every public overlay preview, and a User Reports page in the admin panel where the reports land.

- **The report row renders even when the overlay has no description.** The old block was `v-if="template.description"`, so on a description-less overlay the entire container was absent. Putting the button inside it would have made reporting available only on overlays whose owner happened to write a description. The description is now the optional half of that row, not the thing that gates it, and `ml-auto` rather than `justify-between` keeps the button hard right either way.

- **Logged-out visitors can report.** This is the point of the feature: someone arriving from a shared link is exactly who spots a problem, and they are the least likely to have an account. They give an email address instead of an identity. That email is never verified and is stated as unverified in the admin table, because a typed-in address proves nothing.

- **No captcha.** Three cheap layers instead: a honeypot field, a timing trap, and a tight per-IP throttle (3/hour, 10/day). The timing trap's render timestamp is signed with `Crypt::encryptString`, so a bot cannot back-date the field to look like a slow human. It deliberately never expires - its only job is to prove the timestamp came from us, and expiring it would silently reject a real person who left the tab open. Turnstile stays an option if this ever gets abused; it was not worth an external processor in the privacy policy on day one.

- **Every rejection returns the ordinary success response.** Honeypot, timing trap, forged ticket, duplicate submission: all of them redirect back exactly like a real report, and write nothing. Telling a bot which check it tripped is how it learns to pass the next one. Four tests assert this, which is the only way to keep it from being "fixed" into a helpful error message later.

- **Reports outlive the overlay they are about.** `overlay_template_id` is nullable with `nullOnDelete`, and the slug and name are snapshotted onto the row. Deleting the overlay is often the outcome of acting on the report, so cascading the delete would erase the record of why it happened. The admin table strikes through the name and says "overlay deleted".

- **Deleting a report copies its reason into the audit log first.** The audit log is the append-only record of what admins acted on; a report vanishing from it without a trace would defeat that.

- **One open report per reporter per overlay**, which stops a double-clicked submit button and stops one person padding the queue.

- **Retention is 180 days after an admin marks a report handled**, swept daily. Only reports actually marked as read are swept, so nothing disappears out of the queue unreviewed. That sweep is what caps how long the reporter's email and IP are kept, and section 6 of the privacy policy now commits to it in those words.

The privacy policy gained a "Reporting a public overlay" subsection covering what is stored, that the IP is for spotting mass filers and nothing else, that reports are admin-only and the reported user is never told who filed, and how to have one deleted.

The dialog copy states where the report goes and stops there. It does not promise review, a timeframe, or an outcome.

**The route is named `reports.store`, not `overlay.report`.** The first name shipped broken: `config/ziggy.php` hides `!overlay.*` from every frontend payload, and Ziggy's `filter()` returns `false` the moment a `!` pattern matches, so a negation beats an explicit include. The name could not be added back to any group, and hitting Submit threw `route 'overlay.report' is not in the route list` in the browser. Narrowing `!overlay.*` into per-route negations would have fixed it too, at the cost of turning a blanket deny into an allowlist of denies where the next `overlay.*` route silently ships to every client. Moving the route out of that namespace keeps the deny intact; the URL is still `/overlay/{slug}/report`, and the name now pairs with `admin.reports.*`. A parametrised test asserts all three Ziggy groups can resolve it, and was checked to fail without the guest entry.

## August 4th, 2026 - feat(reference): integration controls are generated from the drivers now, not remembered

The reference is a vault of hand-written markdown, which is the right call for Twitch tags: they change when Twitch changes, which is rarely and loudly. It is the wrong call for integration controls, which are defined in PHP and change whenever a driver does. Left to hand-maintenance, it had converged exactly where you would expect: 4 of 7 services documented, none of them completely, and every one of the four asserting that the shared donation schema covered "all four integrations" when seven drivers provision those same six keys.

Finding them was itself the argument. Ko-fi's page was filed as `ko-fi-auto-provisioned-controls` while its service key is `kofi`, so it did not turn up in a search for the thing it documents. Buy Me a Coffee, Throne and GPS had no page at all.

There is now an `integration-controls` category, emitted from `getAutoProvisionedControls()` on each registered driver. Eight pages: one per service, plus an index.

- **`php artisan help:build-integration-controls`** reads `ExternalServiceRegistry` and writes the markdown. Adding a service to the registry adds its reference pages, its sitemap URLs and its rows in `help-reference-index.json`, with no separate documentation step to forget.
- **The output is committed, not gitignored.** That way the diff appears in review when a driver changes, and - the part that made this cheap - every existing consumer keeps working untouched. The Blade pages, the sitemap, the JSON index and the Alt+R palette's Vite glob all already handle `.md` files. Zero consumers changed.
- **`--check` fails when the committed files no longer match the drivers**, and a test runs it. Verified the way these things have to be verified: renamed one Ko-fi control label, watched it fail and name `kofi.md`, put it back.
- **The shared six are separated from what each service adds**, because flattening them into one table is what makes someone think `latest_item_name` is portable. Ko-fi, StreamLabs, StreamElements and Fourthwall have exactly the six; Buy Me a Coffee adds one; Throne adds three. GPS shares none of them and is labelled as not using the schema at all rather than pretending to extend it.
- **`ExternalServiceRegistry::displayName()`** now owns the service-name map. It was a private method on `IntegrationController` and a second inline copy in `AdminUserController`; the generator needed a third, which is how you end up with "Streamlabs" and "StreamLabs" both being correct somewhere. IntegrationController delegates now. AdminUserController still has its copy and could follow.
- **`llms.txt` carries the shared-schema rule inline** and points at the generated index for exact keys, so a model reading the file alone learns that swapping `c:kofi:` for `c:streamlabs:` is a valid port.

- **The four hand-written pages they replace are gone**, with 301s from their old URLs to the generated ones. They were filed under `eventsub-tags` despite documenting controls rather than EventSub tags, nothing anywhere linked to them, and their central claim had gone stale. The reference is the best-indexed part of the site, so retiring a page there means redirecting it, not deleting the URL.

The Ko-fi, StreamLabs, StreamElements and Fourthwall `*-events` and `*-event-tags` entries are untouched: those document `event.*` payload fields, which is a different thing that is still hand-written for good reason.

## August 4th, 2026 - docs(llms): the homepage now says out loud that machines are welcome

Yesterday's work gave `/llms.txt` a page pointing at it inside the reference. The reference is well indexed, so that was the right first move, but it left the strongest page on the site out of the chain: the homepage is plain Blade, canonical, priority 1.0, and the first thing anything crawls. Its only mention of the file was `<link rel="llms-txt">` in the head, which is exactly the non-link that started this whole problem.

The footer now carries the invitation as visible body copy.

- **"Reading this as a machine? You are welcome here, and this is not a grudging robots.txt allowance."** Four links follow: the file itself, its explainer page, `/help.md` as the crawl entry point, and the JSON tag catalogue.
- **Visible, not hidden.** A hidden keyword block would be a spam signal, and this is a real invitation to a real audience. It sits in the footer where it does not interrupt the pitch above it.
- **The chain is now closed from the top**: homepage (priority 1.0, crawled) to `for-machines/llms-txt` to `/llms.txt`, with the reference's own 140-page footer as a second path in.
- A test pins the homepage anchor, since losing it would silently undo the part of this that matters most.
- **"No rate limit" came back out of all four places it had been written.** The math is not the problem: `llms.txt` is 24KB raw and 9.7KB gzipped, served by Caddy's file server with no PHP process and no database query behind it, so ten thousand requests is about 97MB and a rounding error against the transfer allowance. The problem is the sentence. It advertises the absence of protection, and it is a promise that becomes a lie the day we want to add one. "No login, no API key, nothing to sign up for" says the useful part and commits to nothing. Both machine-facing entries now ask politely for caching instead.

## August 3rd, 2026 - docs(llms): llms.txt now has a page pointing at it, because a meta tag is not a link

Overlabels has published a complete overlay-authoring guide at `/llms.txt` for a while, and every attempt to get an assistant to actually read it ran into the same wall: it would not fetch a URL nothing had indexed, and nothing would index a URL nothing linked to. The `<link rel="llms-txt">` in the document head reads like a link but is not one - crawlers follow anchors, and `llms.txt` is a convention rather than a ratified standard, so no crawler goes looking for it on its own. The file was published and invisible.

It now has a page whose whole subject is the file, on the one part of this site a crawler can actually read.

- **`/help/reference/for-machines/llms-txt`.** The reference vault is plain server-rendered Blade - every `/help/*` prose page is an Inertia shell that hands a fetcher ~27KB of `<head>` and no words. So the reference is the only crawlable HTML documentation on the site, and that is where this belongs. The entry says what the file contains, states in plain language that any model may read it, and includes a copy-pasteable prompt for handing the URL to an assistant.
- **Two neighbours, so the category is not a lonely SEO page.** `markdown-endpoints` documents the "append `.md` to any help URL" convention, and `help-reference-index-json` documents the full tag catalogue as JSON. All three were already described inside `llms.txt` itself, with nowhere on the web to point at.
- **Body copy on `/help/reference` proper**, in the article column above the fold - the highest-priority page in the section, and the first thing in `<main>`. Not a badge, not an icon link. There is a comment in the Blade saying so.
- **A footer on every reference page**, so all 140 of them link to the file rather than just the index.
- **JSON-LD** naming `llms.txt` as a free `DataDownload` with `encodingFormat: text/plain`, which is the machine-readable way to say the thing the prose says.
- **The link is reciprocal now.** `llms.txt` §11 points back at the page that explains it, and `sitemap.xml` moved the file from priority 0.5 to 0.9 - nothing on this site matters more to a machine reader.
- **`@context` is a Blade directive.** Writing the JSON-LD out as literal JSON compiled `"@context"` into a call to the Context facade and swallowed the rest of the template, which took the entire reference down with it. The structured data is built as a PHP array and `json_encode`d, so Blade never sees the `@`-prefixed keys. `@type` and `@id` are not directives today; nothing says they will not be.
- **Eight tests pin the chain** from sitemap to index page to explainer to the file and back, including one that fails if `public/help-reference-index.json` is left unregenerated after a new entry is added.

## August 3rd, 2026 - ui(events): the events feed's empty state now offers the way out instead of describing it

"No events match your filters. Try widening the time range or clearing search." Two problems: the advice was static, so it suggested clearing a search you had not typed and widening a range already set to All Time; and it described an action rather than offering one, while the filter panel it refers to is collapsible, so the search box was often not even on screen.

The empty state now says which of your filters is actually responsible, quotes the search term back at you, and makes clearing it a button.

- **Three branches, each one true.** A search is named and clearable; a narrowed time range is called out on its own; anything else falls back to the plain message. No sentence mentions a filter that is not set.
- **`EmptyState` grew a default slot**, so callers that need markup in the copy can pass it while `message` keeps working. The slot falls back to `message`, and every existing caller passes a plain string or the named `action` slot, so nothing else changed.
- **Clearing cancels the pending debounce** before it applies, so a keystroke still in flight cannot land after the clear and re-filter the list behind you.
- **All three feeds got it**, since the same sentence was copy-pasted across the token-authed feed, `/dashboard/recents` and `/dashboard/events`.
- **`/dashboard/events` also got the echo guard** that Recent Activity received earlier the same day. It had been carrying the identical character-eating search box the whole time - same wholesale replacement of the local filter object on every response, same one-round-trip-stale value written back into the field being typed in. Found while adding the empty state to the same file.
- **Then all of it got deduplicated**, because writing the same guard twice is how the second page came to be missing it in the first place. `useEventFilters` now owns the filter ref, the echo guard, the debounce and the clear for both Inertia feeds; `EventsEmptyState` owns the empty copy for all three. 200 lines out of the three consumers, ~90 back as shared code, and one place left to get it wrong.
- **The token-authed feed deliberately does not use the composable.** Its filters are local state that never round-trips through props, so it has no echo to guard against - wiring it through a watcher that watches nothing would be indirection bought with no bug fixed. It shares the empty state and nothing else.

## August 3rd, 2026 - ui(events): the list-feed card on Recent Activity is collapsed by default

"Send these events to a list" occupied most of the first screen of Recent Activity: a title, an explanatory paragraph, a status block, a three-column configuration grid, an event-type fieldset and a save button, all above the events you came to look at. Since the hash-authed feed route landed, mirroring events into a list is a cool thing to have rather than something you need on the way in, and it was pushing the actual point of the page below the fold.

It is now a disclosure. Collapsed you get the title and a chevron; expanded, everything it had before, unchanged.

- **The heading is the toggle** - `<h3>` wrapping a `<button aria-expanded>`, which is the standard disclosure shape, so it keeps its heading semantics and announces its own state instead of being a div that happens to respond to clicks.
- **One thing survives the collapse: whether a feed is actually running.** The old card deliberately kept "which lists are receiving events" always visible, and hiding that outright would mean a live feed with no sign of itself anywhere. Collapsed, a green dot and "2 lists receiving" sit next to the title - but only when something is on. If you have no feeds, which is the case for everyone this change is for, it really is just the title and the chevron.

## August 3rd, 2026 - fix(events): searching "poll" found nothing, searching "po" found polls

Typing `Po` in the Recent Activity search returned Poll started, Poll updated and Poll ended. Typing `Poll` returned nothing. Being more specific made the results worse, which is the sort of thing that makes you distrust a search box entirely.

The search only ever looked at the stored payload - `event_data::text` for Twitch, `normalized_payload::text` for external services. A poll payload does not contain the word "poll" anywhere; the word lives in the event type (`channel.poll.end`) and in the label the feed renders in the browser, neither of which was being searched. The `Po` results were a coincidence: poll payloads carry a `channel_points_voting` key, and "po" is a substring of "points". Nothing about polls was ever actually matching.

- **The event type is searched alongside the payload now.** That is where the words people actually type live - "poll", "raid", "cheer", "follow", "donation". Both event tables get it, so it works the same either side of the union.
- **The two conditions are grouped, and that matters more than it looks.** An ungrouped `orWhere` would have escaped the surrounding `user_id` scope and the GPS exclusion, turning a search into a way to read every user's events. The same `applyFilters()` also backs the new bulk delete, so the identical mistake would have deleted other people's rows. There is a test on each path that fails if the grouping is ever removed.
- **Still not searchable: the multi-word labels.** The feed renders "Poll updated" client-side while the server's catalogue calls the same event "Poll Progress", so there is no single string to match against. Searching "poll" finds it; searching "poll updated" does not. Worth fixing by making one of those the source of truth rather than by teaching the query about both.

## August 3rd, 2026 - fix(events): the recents search box was eating characters

Typing in the search filter on Recent Activity felt like it was fighting you. Pause for half a second reaching for Shift and the search would fire early; keep typing while it loaded and the letters you typed would vanish a beat later. It read as "the input is disabled while loading and skips keys", but the field was never disabled and never missed a keystroke. It was throwing them away afterwards.

`recents.vue` kept a local copy of the filters and watched the server's copy to stay in sync, replacing the whole object on every response. The search input is bound to that object. So every response wrote the server's `search` value back into the box you were still typing in - and a response can only ever carry what you asked for one round trip ago. Type `test`, pause, request goes out; type `F`; the response for `test` lands and the box snaps back to `test`. The `F` is gone. The slower the response, the more characters it swallows, which is why it looked like loading was the thing blocking input.

- **The watcher now ignores its own echo.** We remember the term we last dispatched, and when a response comes back carrying exactly that, we leave the box alone - local state is by definition at least as current. A value we did not ask for is real news (back/forward, someone else's link) and is still adopted. The dropdowns sync unconditionally, since you cannot be mid-edit in a `<select>`.
- **The debounce went from 300ms to 500ms.** 300 is tuned for a search box that is the whole page. This one filters a table you are reading, and reaching for a modifier key routinely takes longer than that.
- **Search stopped littering browser history.** The filter visit was pushing an entry per keystroke batch, so leaving the page meant pressing back through `t`, `te`, `tes`. It replaces now.
- **A filter change only fetches the feed.** It was re-sending the template list, the facet counts and the user's lists on every keystroke. Those cannot change from a filter, so the visit asks for `recentEvents` and `filters`, and the controller defers the other three behind closures so their queries do not run either. Making the response smaller also shrinks the window the bug above lived in, which is why it was worth doing in the same pass rather than later.

## August 3rd, 2026 - feat(events): select and delete rows from the recent-events feed

Every integration has a test button, and every test event lands in your feed and stays there. Fire a few Twitch CLI events while wiring up an alert and you are left with `testFromUser raid 56171 viewers` sitting in your history forever, next to the real raids.

The obvious fix was to detect test events and hide them, and that turns out to be a trap. The donor name is the only thing the payloads have in common, and it is different for every service: Twitch CLI sends `testFromUser`, Ko-fi sends `Jo Example`, Throne sends `marie_123`, StreamLabs sends `Kevin`, Fourthwall sends `supporter username`, Buy Me a Coffee sends `John`, and StreamElements picks a fresh random name on every payload. Filtering on any of those means the day a real viewer called Kevin tips you, their donation quietly vanishes. There is no name-based rule that is safe.

Test mode looked like the way out, since it already exists per integration and the webhook controller already branches on it - but it does not mean what the name suggests. It only tells the system to stop rejecting repeated UUIDs, so the *first* test event you fire lands identically whether test mode is on or off. Flagging on it would have missed the single test that most people fire, which is exactly the one that causes the clutter.

So no detection. The feed just lets you select rows and delete them.

- **Selection keys are `source:id`, not `id`.** The feed is a `UNION ALL` over `twitch_events` and `external_events`, whose ids both start at 1 and collide freely. A flat id list would have deleted a stranger's Ko-fi tip because it happened to share a number with the follow you picked. A test pins this down specifically.
- **The filter bar is the bulk selector.** Alongside per-row checkboxes and select-all-on-page there is "select all N matching these filters", which re-derives the set server-side from the same `normalizeFilters()` the feed renders with. Filter to StreamLabs, search "Kevin", delete the 47 that match. It sidesteps the identification problem entirely by letting you define what junk means per cleanup, rather than shipping a rule that has to hold for seven services forever.
- **Deleting a row takes its hidden rows with it.** Gift-sub bombs fold N recipient events under the gifter, and a resub hides the bare `channel.subscribe` that Twitch fires alongside it. Those rows exist but are not rendered, so deleting only what you can see would have left them behind to reappear ungrouped on the next load, looking like the delete had failed.
- **`user_id` scoping is the authorization boundary, and it is load-bearing twice over.** `twitch_events.user_id` is nullable for events from broadcasters we do not know, so a delete scoped by id alone would reach rows belonging to nobody. GPS rows are excluded on the way out for the same reason they are excluded on the way in: they never appear in this feed, so they can never be picked from it, spoofed source or not.
- **No delete on the token-authed `/events/feed`.** It shares the same table component, behind a prop the recents page passes and the feed does not. Overlay tokens live in an OBS browser source URL and are write-capable for exactly two actions today; a destructive third is a worse trade than the convenience is worth.
- **Nothing is rolled back.** Deleting a donation event does not decrement `donations_received`, because those controls are running counters rather than a projection of the event tables. The confirm copy says so, and points at nothing, because the per-integration seed actions already exist for that.
- **Deleting while live is allowed.** A lock would not have protected the numbers anyway: session stats are a time-window query computed at read time, so an event deleted thirty seconds after the stream ends moves them exactly as much as one deleted mid-stream. It would only have postponed the same mutation while blocking the case that actually needs it, which is clearing follow-bot spam in the middle of the raid. The confirm says the stats will move and gets out of the way.

## August 1st, 2026 - fix(auth): logging out landed on a 404 instead of the homepage

Account > Log out did log you out, then dropped you on a 404 at `/logout`. Same day, same root cause as the dialog fix below it: `/` is a plain Blade view, and Inertia has no idea what to do with one.

Yesterday's backstop navigates to the URL of the visit that is in flight, because the `httpException` payload is typed as status, data and headers with no URL on it. For a link click those two URLs are the same, which is every case that was tested. Logout is the one flow where they diverge: the visit starts at `POST /logout`, the server redirects to `/`, the XHR follows that redirect itself, and Inertia gets a full HTML document back. The backstop then fired and sent the browser to the URL it had recorded - `/logout` - as a GET. There is no `GET /logout`, so `Route::fallback` served the 404. Logged out, stranded, one route short of home.

- **`Inertia::location()` instead of `redirect('/')`.** It answers the XHR with 409 + `X-Inertia-Location`, which Inertia turns into a real navigation natively, never reaching the backstop. This is already how `RedirectIfUnauthenticated` and the 419 handler send an Inertia request to a non-Inertia page - it is the existing pattern, not a new one. A session teardown wants a fresh document anyway.
- **The backstop now only resumes GET visits.** A non-GET visit that ends on plain HTML got there by a redirect, so the recorded URL is guaranteed to be the wrong target - and re-issuing a write as a GET is a bad outcome even when a route does exist to receive it. One-line guard, closes the class rather than the instance.
- **A duplicate `POST /logout` in `web.php` was deleted.** It had been dead the whole time: `RouteCollection` keys on method+URI, `auth.php` is required further down the same file, and the later registration wins. `route:list` only ever showed one. Worth knowing before debugging a route that looks right and never runs.
- **Logout had no tests at all**, which is why this shipped twice in two forms. It has three now, and the one that matters was confirmed to fail against the old controller.
- **`BotFollowageTest` was failing on the calendar, not on the code.** It asserted "2 years, 2 months, 30 days" off a `now()->subMonths(3)` fixture. `HumanDuration` walks calendar units, so subtracting 3 months from a high day-of-month clamps into a short month and the answer shifts by a whole unit - meaning the test passed or failed depending on the day the suite ran. It now freezes the clock in `beforeEach` exactly like `BotAccountageTest` already did, anchored to the 15th because no month is short enough to overflow it. With `now()` frozen the span is exact, so both duration assertions moved from `toContain` fragments to the full sentence chat actually sees.

## August 1st, 2026 - feat(updates): the updates feed is public

`https://overlabels.com/updates/overlabels-development-highlights-july-2026` bounced anyone who was not logged in. That is the wrong shape for what these posts are: announcements, written to be linked from Twitch, from Discord, from anywhere someone might first hear about the project. A link that only works if you already have an account is not a link.

Both routes moved out of the `auth.redirect` group and now sit with the other public routes, next to `/help`. Nothing else about them changed.

- **No new visibility logic was needed.** `UpdateController` already queried through the `published()` scope on both actions, which is `published_at <= now()`, so a post dated into the future stays a 404 for guests exactly as it did for logged-in visitors. Moving the route did not open a hole, because the gate was never the middleware.
- **`updates.*` joined the `guest` Ziggy group.** The index page calls `route('updates.index')` when you type in the search box or pick a tag, and the guest group is a whitelist, so without this the filters would have thrown for the exact visitors the change is for. Worth remembering for the next public page: making a route public is two edits, not one.
- **Guests get an Updates item in the sidebar.** Logged-out visitors see their own nav list, so the page was reachable but not findable. It now sits under Learn, right below Help.
- **A test pins all of it down**, including the future-dated 404, because "this page is public" is the kind of property that quietly stops being true.
