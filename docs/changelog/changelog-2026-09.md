# Changelog - September 2026

## OL-2609-062 - September 10th, 2026 - feat(bot): built-in commands get a switch and a tier, per channel

Seventeen commands arrived with the bot, on by default, and there was no way to switch any of them
off. `!followage` answered whoever asked, forever. That is not very Overlabels: the whole platform is
built on not deciding for the streamer, and here we were shipping verbs into their chat with no door
marked exit.

The odd part is that the door was already built. `bot_builtins` has stored `enabled` and
`permission_level` per channel per command since the registry existed, and the command map has always
filtered on the first and published the second - the bot drops any command missing from the map by
design. The columns were seeded at signup and then never written again by anything. So this is a page,
not a feature: `/settings/bot/builtins`, a switch and a "who can use it" tier on each row, and the
bot needed no change at all to honour either.

Not everything on that page is yours to set, and the split is the same one source-managed controls
have. A command a product installed - `!stack` and `!tower` from Chat Tower, `!checkin` from Chat
Checkin - belongs to that product: its settings, cooldown included, live on the product's own page,
and switching it off from an unrelated screen would quietly break the thing you just installed. And
`!enablecontrols` / `!disablecontrols` stay on permanently, because they are the master switch for
every chat control command: turn them off and there is no way back to controls from chat. Both kinds
are shown rather than hidden - you should be able to confirm your product's commands are present -
and both are refused by the controller rather than merely lacking a knob in the UI, the way
`OverlayControl::setValue()` refuses a `source_managed` write.

Ownership is declared once in `BotBuiltin::DEFAULTS` and stored nowhere. It is a property of the verb
and identical for every account, so there is no column, no migration and no backfill, and adding a
builtin stays the two edits it has always been.

No cooldown here yet. Builtins are the one command type that has never had one, and they ride the
bot's channel-wide window - which dropped to 1s today in OL-2609-061. If a per-command cooldown is
ever wanted, it is an additive field on the map rather than anything structural.

## OL-2609-060 - September 10th, 2026 - fix(controls): Expression Controls resolve in bot replies, alert messages and the living title

Drop `[[[c:subs_plus_1]]]` on an overlay and it renders 5. Drop the same tag in your Twitch
title and it rendered nothing at all. Same for a bot command, same for an alert's TTS line.

An Expression Control has no value of its own. The overlay evaluates the formula in the browser
on every tick, so an overlay is always live. PHP never evaluated anything: it read a scalar
cached on the row, and that cache was only ever refreshed when a control the expression
*depends on* changed. An expression over Twitch data, `t.subscribers_total + 1`, depends on no
control at all, so nothing ever refreshed it and the row kept the null it was created with.
Forever. Even a control-dependent expression was null until its first dependency update.

- **Evaluated on read now, through the same sidecar the overlay's evaluator shares.** Parity by
  construction rather than by more triggers, which would still have left a freshly created
  control empty until something happened to fire. No migration, and the save path, the cycle
  detector and the dependency validator are untouched.
- **It costs nothing when nothing needs it.** Only expression controls the template actually
  names are evaluated, so a bot command mentioning none pays not even a query. If the sidecar is
  unreachable the control keeps its stored value, which is exactly what these surfaces printed
  before.
- **The `_at` companions were missing server-side too**, and that one was quietly worse: the
  engine does not error on an unknown identifier, it coerces, so `max(c.a_at, c.b_at)` returned
  0 and got stored as the control's value. One local control read "seconds since the last spin"
  and held 1788971784, which is seconds since 1970. The context now carries every control's
  `_at`, same rule and same fallback as the overlay payload.
- **One definition of how a `c:` tag names a control.** `OverlayControl::tagIdentifier()`. The
  rule was written out longhand in four places and only the fourth had it wrong; a control with
  a source but not service-managed would have resolved to 0 in silence, the same failure `_at`
  had. No row in the wild had that shape, so nothing changes today.

## OL-2609-055 - September 10th, 2026 - feat(products): Chat Tower, the third product - the tower overlay, the manifest, the record list, the hero and the help page

The product half of Chat Tower, on top of the integration (OL-2609-051), the bot verbs
(OL-2609-052) and the overlay client (OL-2609-053, OL-2609-054). One click on `/products`
installs the overlay, connects the integration and creates the record list; the bot and OBS are
the two things left for the streamer, the same as Checkin.

- **The overlay is an engine build.** A 560px column on the right of a 1080p canvas: a HUD with
  height, room to fall, the record and its roster, a lean gauge with the sway drawn as a band,
  two dashed fall lines, and the blocks themselves, each in its viewer's chat colour, swaying in
  proportion to how high up it is. A camera slides the whole stack down once it is taller than
  the field. Ten expression controls and one toggle for the command hints.
- **The topple is the moment.** For three seconds after a fall the blocks stay and tumble toward
  their own side on a CSS transition, top first; then a banner names the height and the viewer,
  and the ground shows the `!stack` chip again. A record flash appears when the record moves.
- **The record list is a normal List.** `tower_record` is created by the install, written by the
  server every time a standing tower passes the record, shown on the Lists page, and loopable
  from any overlay.
- **Proven through the real path.** Eight stacks and a topple were sent through the bot endpoint
  on the local account and watched land on the overlay; the CSS stayed on the fast path with four
  bound properties. The hero's top block reads JasperDiscovers and its base reads overlabels.
- A guide at `/help/chat-tower` covers the commands, the fall rule, the iterable, the controls,
  the record list, the alert triggers and the settings.

## OL-2609-051 - September 10th, 2026 - feat(tower): the Chat Tower integration - !stack physics, blocks, controls, broadcast and settings page

Product #3. Bowling is instant and individual, Checkin is permanent and individual; the gap was
the collective game, one shared object the whole chat builds and is afraid of breaking. Chat
Tower is that: `!stack` puts a block with your name and chat colour on top of one tower, every
block lands a little off the one below, the tower sways more the taller it gets, and when the
lean crosses a fall line it comes down and the viewer who placed the last block gets named. Chat
delay is the game, not a bug: you correct a lean you saw four seconds ago, three other people do
too, and now it leans the other way.

This entry is the server half. The bot verbs, the overlay client and the product itself follow
as their own entries.

- **The physics is one file and one rule.** A block lands with an offset relative to the block
  below: unaimed, up to a quarter block either side; aimed with `!stack left` or `right`, on
  that side and further out than an unaimed block ever goes. That is placement, never a shove.
  The lean is the top block's position, the sway grows with height, and the tower falls when
  lean plus sway crosses the fall line. Deterministic from what the overlay draws; the only dice
  is where a block lands.
- **Only a standing tower counts.** The block that brings the tower down sets no record and
  raises no counter; the tower has to stand after a block for it to count. The bot says "fell at
  23" for the position of the block that did it.
- **Nothing beyond being named.** A topple costs the viewer nothing. The record roster is the
  all-time one, written into the `tower_record` List the product installs, one name per viewer
  bottom to top, rewritten every time a standing tower passes the record.
- **The bot is quiet by default.** A plain `!stack` gets no reply. It speaks on a topple, on the
  first block past a real record, and at every tenth block. `!tower` answers height, lean, room
  and record for the viewer who just arrived, offline too.
- **Same shape as Checkin, on purpose.** An integration with a driver that refuses webhooks, a
  settings page with the lifetime (per stream by default, the record carries over either way),
  the cooldown and a reset button, eleven source-managed controls with three on the go-live
  reset list, a one-block delta broadcast, and a `tower` iterable in the render payload capped
  at the top fifty blocks because the camera follows the top.

## OL-2609-050 - September 9th, 2026 - fix(products): the URL hint can no longer switch product mode on after a flow has ended

Found on a reinstall where everything was already set up. The finished product page's green
"Add to OBS" button carries the last-mile hint in its URL, and that hint was still allowed to
switch the mode on by itself, a leftover from the morning when the URL was the only switch. So a
flow that had just ended came back framed and bannered on the overlay page, and looked stuck.

- **The URL never starts a flow.** The mode has one source now, the flow on the account. A visit
  carrying the hint after the flow has ended shows no frame, no banner and no green.
- **The hint keeps its useful half.** It still opens the Add to OBS tab first and shows the
  last-step callout with the way back, which is the reading it was meant to have: the flow is
  done, here is the last mile.

## OL-2609-049 - September 9th, 2026 - feat(products): the flow frame names itself, the banner says what to do, and the next step's control lights up on its page

Ruthless state falls apart at the page's front door if the page does not say which button. So
the frame, the banner and the destination now agree, and each one points one level closer.

- **The frame names itself.** Bottom left, in its own colour: "Product installation mode
  enabled", and "Product installation complete" once the flow is fulfilled.
- **The banner gives an instruction, not a state.** "Next: the bot is switched on" read as a
  claim about a bot that was off. Each step now carries what to do: "make sure the bot is
  switched on", "type /mod overlabels in your own chat", "create an overlay link for OBS".
- **The destination lights the exact control.** Each step names the control it is about, and
  the page the step sends you to gives that one element a thin fuchsia edge with a soft glow
  while the flow is on: the Chat bot card on integrations, the Create token button on tokens.
  Not 10px, the frame already did that; this is the finger on the button.

## OL-2609-048 - September 9th, 2026 - feat(products): product mode is on for the whole app while a setup flow is active, with a glowing frame

"You can't half flow." The URL-carried product mode from earlier today lit one page for one
visit and dropped on the next click, which is right for a single hop and wrong for a person three
steps into an install. The fix is not to forward the query through every link, and not a client
store that ticks a step when its button was clicked: a click is intent, not completion. The fix
is to admit what the mode really means.

- **Product mode is on while a setup flow is active. Full stop.** The one fact the install
  already writes on the account drives it, on every app page, across every navigation, until the
  flow is fulfilled or dismissed. The URL query remains as the one-visit hint and as the name of
  the product to go back to.
- **A 10px glowing frame around the whole app says so.** Fuchsia and slowly pulsing while
  anything is left, green and still once nothing is. It is decoration: nothing to click, no
  layout, off under reduced motion. The banner above the page keeps the words and the way back.
- **Completion stays a server fact.** The checklist is recomputed on every page load from the
  bot toggle, the token table and Twitch's own answer, so the frame turns green when the world
  says so, never because a button was pressed.
- **The green Add to OBS tab now lights on any visit** to the overlay page during a flow, from the
  sidebar or the band alike, and the callout's way back knows the product from the flow.

## OL-2609-047 - September 9th, 2026 - feat(products): the Follower Bowling lane is redesigned to the hero's look

The hero banner on the products page got a lane to match. Claude Design drew it from the banner
and the Overlabels design system; this brings it onto the engine. A product's overlay is a file in
the repo, so a redesign is that file changing, and every new install gets it.

- **Same game, new skin.** The twenty expression controls and their formulas are untouched. The
  ball still travels by the same number, the pins still fall by the same flags, the score still
  reads STRIKE, GUTTER or a count. What changed is what those numbers paint: a deep violet lane
  with pink gutters and a foul line, aiming arrows on the boards, an 84 px blue ball with finger
  holes and a trail, and the ten pins in a rack whose apex points at the ball, the head pin with a
  pink ring.
- **The queue is a panel.** "In line" with the count, the first three numbered, the first in pink,
  the `!bowl` chip and "Your ten newest followers are the pins" at the foot. The mods line is a
  pink chip naming `!fbfirst` and `!fbdraw`.
- **It is a strip now.** 1840 by 440 along the bottom of a 1080p canvas, the same footprint as
  the design's 1920 by 560 artboard. The old lane was 260 px tall.
- **Existing installs keep their lane** until they uninstall and install. There is no in-place
  update of an installed product, and that stays a decision for another day.

## OL-2609-046 - September 9th, 2026 - fix(products): Follower Bowling racks ten pins for a channel with fewer followers

A fresh account has one follower. Every channel the bowling lane had ever been tried on had
hundreds, so nobody noticed that a new channel bowls at one pin, and that a strike, which needs
ten knocked down, could never happen there. The one person a product exists for got the broken
version.

- **Stand-in pins fill the rack.** After the followers loop the lane keeps ten small gated blocks,
  one per slot, each rendering a dimmed Twitch avatar with a question mark when the loop did not
  fill that slot. They take the same fall flags, so the physics, the score and the strike are
  untouched. A real follower takes a stand-in's slot the moment they arrive.
- **The product page says what you are seeing.** A channel with fewer followers than pins gets one
  line under the product: how many pins are real, and that the rest fill in as followers arrive.
  Not a step, because a follower count is not a step anyone can take.
- **The one caveat, reported not fixed:** the pins the loop renders are capped by the account's
  followers foreach setting, default five. A channel above five followers now shows five real pins
  and five stand-ins until it raises that cap. Those slots were simply empty before.

## OL-2609-044 - September 9th, 2026 - feat(products): the verified badge on every product surface, and a green finished state on the product page

Everywhere else in Overlabels the person did the work, so the design stays out of the way. A
product is the opposite: the person pressed a button and followed a few lines, and the page that
says "done" has to make that feel like it counted anyway. This is the one page that is allowed to
celebrate, and it is a deliberate exception to the house restraint, for this surface only.

- **One mark for "an official Overlabels product".** The verified badge from TDesign Icons, on
  the listing heading, on every card, in the product page header and in the setup banner. Violet
  by default, green once installed.
- **The finished state says so loudly, once.** When every step is done the page shows a solid
  green band with the badge large, "Everything is in place", one line in the streamer's world
  ("Your chat can type !checkin right now, and the globe is waiting in OBS"), and one button per
  overlay straight to its Add to OBS page. The header above it has gone green as well.
- **The checklist became a progress piece.** A bar that fills as steps complete, fuchsia while
  anything is left and green when nothing is, and a tick that lands on each finished line. Both
  motions are gone under reduced motion, and nothing else on the page moves.
- **Install and uninstall show the app's toast** on the product page, which used to swap a button
  label and nothing else. That shipped just before this, as its own change.

## OL-2609-042 - September 9th, 2026 - feat(products): a setup banner on every app page reels a mid-install streamer back to the product

Installing Follower Bowling on a fresh account showed the gap. The checklist sends you to a
settings page to switch the bot on, to the tokens page for an overlay link, to your own chat to
type the mod command. Each of those means leaving the product page, and nothing brought you back.

- **One fact: "setting up product X".** Install sets it, on the user, no migration. From then on
  a fuchsia banner sits at the top of every app page: what product, how many things are left,
  which one is next, and one button back to the product page. A green banner with "Go to
  installed product" when nothing is left. "Not now" ends it for good.
- **The banner and the page cannot disagree.** Both read the product's wiring circuit; the next
  step is simply the first missing line in checklist order.
- **It ends where it should.** The product page seeing nothing left ends it, an uninstall of that
  product ends it, and a mid-setup visit to the product page re-asks Twitch about mod status
  instead of serving the five-minute cache, since typing the mod command is exactly the step
  people leave for.
- **It never nags.** No flow, no queries, no banner. The product pages themselves live outside
  the app layout, so the banner never appears on the one page it points at.

## OL-2609-040 - September 9th, 2026 - feat(products): the product checklist says whether the bot is a moderator, from the bot's own side of Twitch

The products entry two below listed this as the one checklist line that would help most and the
one that needed every account to log in again. It needed neither. Bot replies work by mod status,
and Twitch will tell the bot itself which channels it moderates: one call with the bot account's
own token answers for every streamer at once.

- **One scope, on the bot token, granted once by the admin.** `user:read:moderated_channels`
  joins the three the @overlabels account already grants on the admin Twitch Bot page. No streamer
  sees a reconnect banner. Until that page is used once more on prod, the line reads as not
  checked, and the admin page says so.
- **The wire never accuses.** No token, no scope, Twitch not answering: all of those are "not
  checked", with their own sentence, never a red line telling a streamer to do something they may
  have done. A real "no" says to type `/mod overlabels` and that the page rechecks within five
  minutes, which is the cache.
- **Presence and mod status stay two questions.** The bot reporting it can hear your chat is one
  thing; Twitch delivering its replies is another, and now both are on the list.

## OL-2609-039 - September 9th, 2026 - feat(products): a product can be uninstalled from its page, from the install's own ledger

The products entry below said uninstalling was by hand. It is a button now, and the reason it
could be one quickly is that the install had been keeping the receipt all along: every row an
install creates goes into the instance's `primitive_map` by id, which is what made renaming the
aliases safe in the first place. Uninstall walks that ledger back.

- **The dialog names what goes.** Overlay, list, commands, aliases, and the integration, each as
  a line, read live from the ledger so a row the streamer already deleted by hand is not promised.
  Ids, not names, so a renamed `!fbfirst` is still found.
- **An integration is only disconnected if the install connected it.** The install now records
  whether it created the connection or found one the streamer already had. Found means kept,
  settings and all. Installs from before this change have no record, and those never disconnect,
  which is the safe way round.
- **An overlay in a Kit refuses the whole thing.** The kit pivot would block that delete anyway;
  checking first means nothing else is half-removed. The page says which kit.
- **Uninstall then install is the upgrade path.** An account that installed Follower Bowling
  before it had the two mod aliases gets them that way.

## OL-2609-038 - September 9th, 2026 - feat(products): Chat Checkin and Follower Bowling are installable products, on a public /products page

A kit is a bundle of overlays and alerts, and copying one is enough when that is all a thing
needs. Chat Checkin was never that: to run it you connected an integration on a settings page,
switched the bot on, modded it, added the globe tag to an overlay and put that overlay in OBS.
Five steps in four places, and nothing telling you which ones you had done. Products are the
answer to that: one page, one button, and the page keeps count of what is left.

- **`/products` and `/products/{slug}` are public.** A visitor can read what a product does
  without an account; the install button is where the login happens. Two are listed: Chat Checkin
  and Follower Bowling. Dice and Coin Flip stay unlisted; they are picker recipes, not products.
- **The install is the Recipes installer, taught six new things.** A manifest can now carry an
  `installs` section: overlays, as markdown documents shipped next to the manifest in the exact
  format the import button reads and the public `.md` endpoint emits; integrations, connected the
  way their settings page connects them; Lists, created empty the way the dashboard creates one;
  list appenders, the chat commands that fill them; Bot Aliases; and custom Bot Commands. The
  aliases and commands go through the same validators the settings forms use, before anything is
  created, so a reply the form would refuse refuses the install. Chat Checkin uses the first two,
  Follower Bowling adds a list, `!bowl`, and two moderator aliases: `!fbfirst` for `!list lane
  pop first` and `!fbdraw` for `!list lane draw`, both renameable afterwards. Nothing ships a
  custom command yet. The picker sections became optional so a manifest can be a product without
  being a dice roll.
- **Follower Bowling refuses rather than merges.** Its list is called `lane` and its command is
  `!bowl`, because the overlay reads `c:list:lane` and mods type `!list lane`. An account that
  already has either, which is every account that built bowling by hand from the deep dive, gets
  the refusal on the product page and nothing created. The followers cap the deep dive asked for is
  gone: the overlay caps its own pins in the `foreach`.
- **A product can carry a hero.** Both listing cards wear their Claude Design artwork from
  `public/products/`, stripped of the content-credentials blob each arrived with. The checkin one
  is 200 KB of dots, because the globe is drawn, not pasted.
- **The catalogue is the repo.** `RecipeCatalog` reads `resources/recipes/*/manifest.json` and
  writes the `recipes` row on demand, so prod, which runs no seeder, installs from the file.
- **What is left for you is a wiring circuit.** Every installed product is a subject on
  `/settings/wiring` with seven wires: bot on, bot hearing you, integration connected, overlay still
  there, list still there, chat commands on, an active overlay link for OBS. The product page shows
  the same seven as a checklist with ticks and buttons, and hides the ones that do not apply to
  that product.
- **Not built:** a modded check. Twitch needs `moderation:read` for it, which means every account
  re-authorizing once, and that is a separate decision. Uninstall is manual for now.

## OL-2609-036 - September 8th, 2026 - fix(dashboard): opening a post marks it seen on the What's New card, and the visit middleware is gone

The card shipped on August 29th with two records per post: "visited", set by a middleware when the
reader landed on the page a post's call to action pointed at, which greyed the row but left it on
the card; and "seen", set only by the dismiss button or "Mark all as seen", which removed it. Eleven
days of use said it did not work. A row sometimes greyed and sometimes did not, and a card with every
row read was still the full-height card until a button was pressed.

The flakiness was never the middleware. Inertia restores the dashboard from its history snapshot on
Back, so returning to it that way showed the card as it was before the click, and returning by the
sidebar showed it fresh. And opening the post itself, the one thing a reader would call reading it,
was not a visit at all: only the call-to-action target counted.

- **Opening the post marks it seen.** `UpdateController::show()` writes `dismissed_at` for a
  logged-in reader when the post is on their card, and only then: a guest, a post older than the
  account, a post without the tag or one already cleared writes nothing. Undo after reading brings
  back exactly that post.
- **The visited layer is gone.** `MarkWhatsNewVisited`, the `dashboard.whats-new.visited` route,
  `Update::ctaTargets()` and its cache, the `stale` styling and the `visited_at` column, dropped by
  migration. One record per post, one meaning.
- **The card drops the row itself on click.** Because Back restores a snapshot, the card rewrites
  its own history entry through `router.replaceProp` as the title is clicked, so Back lands on a card
  that agrees with the database. Bound with `@click.capture`, because Inertia's `Link` overwrites a
  plain `@click`.
- **Landing on a call-to-action target records nothing now.** Going to `/wiring` is not reading the
  post about wiring. The per-row link stays; it is just a link.

## OL-2609-034 - September 8th, 2026 - feat(gamejam): remove Chat Castle entirely

Chat Castle was the chat-driven dungeon game built in a week in April 2026: viewers typed `!join`,
voted a direction with `!p`, `!h`, `!a` and `!s`, and a party crawled through five rooms of zombies
on a live page. It was shelved in May, after one stream test, on art direction - the game logic was
finished and the tiles were placeholders nobody wanted to look at. Four months later nothing had
moved, and it was still costing something every day: six bot verbs seeded into every opted-in
channel, a throttle bucket, a Vue help page holding the last Inertia help layout alive, seven tables,
and 1,300 tracked asset files. Today it was removed, not archived.

- **Everything with the word in it is gone.** The seven `Game*` models and their tables, the
  `Gamejam` services and the round-resolution job, the admin and live pages, the room builder and
  its five room maps, the `/help/gamejam` page and `HelpLayout.vue` (its only remaining user), the
  five artisan commands, the internal bot endpoint and its rate limiter, the five tests that only
  the game exercised, and the tile art under `public/`.
- **The six bot verbs leave `BotBuiltin::DEFAULTS` and the registry.** `join`, `p`, `h`, `a`, `s`
  and `castlehelp` no longer seed for anyone who opts in, and a teardown migration deletes them from
  every existing channel's registry, so the command map the bot reads stops listing them. The bot's
  own handlers for those verbs are now dead code in its repository; the dispatcher drops any verb
  the map does not carry, so nothing fires.
- **The schema migrations are deleted and one migration drops the tables.** A fresh database never
  creates them; production drops them on deploy, rows included. There is no rollback for that and
  the migration says so.
- **What stays is history.** April and May's changelog entries describe the game as it was built,
  and the private design document went with it. The game is not coming back in this shape; the
  `!checkin` integration is what a chat-driven feature looks like on this platform now.

## OL-2609-032 - September 8th, 2026 - feat(help): search ranks sections with a real term engine, and a result lands on the heading that answers

Live on stream, trying to explain how controls are changed from chat, the help search was tried
with "controls", "chat", "controls chat", "enablecontrols", "twitch controls" and "bot controls".
Four returned nothing. The other two returned lists without the Bot Commands page - the page that
says `!enablecontrols` three times, in a table, under a heading called Controls.

The search was not keyword search. It was Fuse, a fuzzy string matcher, run over each page as one
20 KB blob. Two words in a query were one string to approximately match, so "controls chat" could
never match anything. And a term in a long body scored like coincidence however many times it
appeared, because the length norm scales with the page. `keywords:` frontmatter existed as a patch
for that defect and was on 12 of 41 prose pages.

- **The engine is MiniSearch now - BM25 over tokenised terms.** Every word must match, then any
  word. Filler (`how`, `do`, `the`) is dropped from index and query alike, plurals fold into
  singulars, a typo of a letter or two in a long word still finds it, and a leading `!` is
  punctuation. The same six queries all find the page; "enablecontrols" and its typo land on the
  section.
- **The unit is a section, not a page.** `HelpMarkdown::sections()` splits every body at its h2 and
  h3 headings, using the same id the renderer stamps on the heading, and a result links to it:
  `/help/bot/commands#controls`. Both search surfaces show "Bot Commands › Controls" with a snippet
  cut around the query. A test walks the whole corpus and asserts every section id is an id the
  rendered page actually has.
- **Nothing in the corpus changed.** No page was edited, no keyword was added; `keywords:` stays for
  words a page is about but never says, and a declared one still leads the results.
- One piece of "tuned behaviour" from the old engine turned out to be an artefact: "raid" surfaced
  the Random Rolls and Counters guide, which never says raid. It was a one-edit fuzzy match on
  "rand". Not kept.

Two things this is not. It is not semantic search: a question whose words the answering section
does not use still misses, and that ceiling is for another day if it turns out to matter. And it is
not a change to the reference index contract - `help-reference-index.json` is byte-identical.

## OL-2609-026 - September 7th, 2026 - feat(twitch): the living title - a tag template kept true on Twitch, plus a category picker

Everything in Overlabels receives data and reacts to it. This is the first thing that writes back
to Twitch other than the bot's chat replies: a stream title written as a template, with tags in it,
that keeps itself true.

```
Road to 2K | [[[followers_total]]] followers | playing [[[channel_game]]]
```

A follow arrives, `followers_total` moves, the title on Twitch updates. It lives at
`/settings/title`, with a preview rendered against your real values and a counter against Twitch's
140-character limit.

- **Nothing new renders it.** A title is the same shape as a bot command reply - one line, tags,
  if/else, pipes, `??` defaults, no loops - and it is resolved by the same `BotCommandResolver`
  against the same data: Helix tags, every control, every list. The save gate speaks in the same
  `Conditionals::describeProblem()` voice for a stray `endif` or a `foreach`.
- **Three rules were decided before a line was written.** `[[[channel_title]]]` is refused inside
  the template: our own write fires `channel.update`, the app consumes it, and the tag would feed on
  itself. A `channel.update` carrying a title we did not write pauses the feature rather than
  overwrite a dashboard edit a minute later; Resume is a click. And a burst of events becomes one
  write: renders are debounced to one per 30 seconds and PATCHed only when the text changed.
  A title that flickers is worse than one that lags.
- **"Any event" re-renders it, not a curated list.** Every stored EventSub event and every control
  write on the platform - Ko-fi, chat stats, GPS, counters, the go-live reset, a manual edit - goes
  through one `saved` hook on the control model. A curated list would have rotted the first time a
  tag was added; the debounce is what makes "anything" affordable.
- **The category is a separate, one-shot pick.** A search box against Twitch's own category lookup;
  choosing one sets it on Twitch right then and is never remembered or re-asserted. Streamers change
  category from the dashboard mid-stream and the title must not fight them over it.
- **One re-authorization.** `channel:manage:broadcast` is the first write scope the platform has
  ever held. The reconnect banner asks for it; until it is granted the title records why it is not
  writing and sends nothing.

## OL-2609-023 - September 7th, 2026 - feat(twitch): subscribe to channel.chat.notification and store it, the ledger a Plus Points count is built from

Twitch's Plus Program counts Plus Points: +1, +2 or +6 every time a paid recurring sub is charged in
a calendar month, with gifts and Prime excluded, reset to zero on the 1st. Streamers chasing the
100- and 300-point levels put the running number in their stream title and keep it there by hand,
because no service computes it. Twitch keeps the number to itself: CasualElephant, a partner with
a Plus goal on his channel, let us read his events, and in 630 goal events since June Twitch has
only ever sent `follow` and `subscription` goals. His Plus tracker shows 28; no goal event has ever
carried a value under 142. The Plus goal is not a creator goal in the API sense.

The one route to a number is chat. `channel.chat.notification` is the USERNOTICE feed, and it is
the only EventSub payload that says whether a sub is Prime (`sub.is_prime`, `resub.is_prime`) and
the only one that reports a gift or Prime sub converting to paid (`gift_paid_upgrade`,
`prime_paid_upgrade`). It still cannot see a renewal the viewer chose not to share, so what it
yields is a floor on Plus Points, not the number. Whether the floor sits close enough to the
dashboard to be worth putting in a title is a measurement, and it needs a month of rows first.

- **Subscribed with the bot as the second party.** The condition names the broadcaster and the bot.
  Twitch accepts it on an app token when the bot has `user:bot` and the streamer has `channel:bot`,
  both of which are already granted, so no streamer is asked to log in again. The bot's Twitch user
  id is new configuration: `TWITCHBOT_USER_ID`, a public value, set in `config/deploy.yml`.
- **Stored and nothing else.** A `sub` notice arrives alongside the `channel.subscribe` for the same
  viewer, so letting it through the alert path would fire every sub alert and counter twice. And
  the overlay spreads every broadcast payload into its tag data, where the notice's `message`
  object would blank a `[[[message]]]` the resub had just filled. `STORE_ONLY_EVENTS` on the
  webhook controller returns after the row is written: no cache refresh, no per-stream counter,
  no alert, no broadcast, no delivery outcome. The events feed shows the rows as "chat notice"
  with the notice type, and offers no Replay.
- **No count, control or tag yet.** The rows are the ledger; nothing reads them. The plan is to let
  a month accumulate on CasualElephant's channel, then compare the floor against the 28 on his
  tracker before deciding what to expose and under what name.
- **Rollout is one manual step.** The health monitor only repairs accounts with no subscriptions at
  all. Existing accounts get the new subscription from a single `eventsub:backfill-goals` run on
  production after the deploy, which is generic and idempotent despite its name. The 6 of 19
  connected accounts still without `channel:bot` pick it up on their next login.

## OL-2609-021 - September 2nd, 2026 - feat(help): the help site gets its own design and a seven-section guide taxonomy

The help docs stopped borrowing the app's chrome. Both pages came from a Claude Design canvas: a
landing page with a search hero, cards for tutorials and deep dives, and the guides laid out in
columns; and a document page with a collapsible tree on the left, the prose in the middle and an
"On this page" rail on the right that follows you as you scroll. Same Blade, same markdown, same
search index underneath - this is the page around the content, not the content.

- The 32 guides were one alphabetical list. They are now filed into seven sections by a `section:`
  line in each page's frontmatter: Getting started, Tags & syntax, Building overlays, Live data,
  Bot & chat, Integrations & testing, For machines. The landing columns, the sidebar branches, the
  breadcrumb, previous/next and "Related docs" all derive from it. The list of sections is closed;
  a typo fails a test rather than opening an eighth column.
- On the landing page each section is its own card with an icon tile and a line saying what the
  group is for, Getting started twice as wide as the rest. The first cut was seven bare columns of
  titles and read as one wall of text. Links inside follow the order index.md lists them in, so
  "Why Overlabels" leads instead of landing last alphabetically; there is no `order:` key.
- The design's own grouping was kept where it was right and corrected where it was not: Random
  Rolls and Counters is a bot page, not syntax; Controls, Expression Controls and Lists are live
  data, not build tooling; the three machine-readable pages belong together.
- Every document page shows its kind, a reading time, a "Copy page as Markdown" button that copies
  the `.md` twin byte for byte, and links to the pages either side of it in its section.
- Search results open in a panel under the field, with arrow keys and Escape, instead of replacing
  the sidebar. Alt+R still focuses it from anywhere.
- `/help` is now built from the corpus; `/help.md` is still the hand-written index and a test keeps
  the two filed the same way. Kind identity is flat tints and words, no gradients.

## OL-2609-014 - September 1st, 2026 - style(events): redesign the event list into dense kind-tagged rows

The event list - the oldest surviving layout in the app, still named `EventsTable.vue` from when it
was literally a table - got a proper redesign, based on a Claude Design mock. Every surface that
shows events picks it up at once: the dashboard card, /dashboard/events, /dashboard/recents and the
shareable events feed.

- Rows are dense, hairline-separated single lines: the pixelgrid provider icon in a small badge, a
  monospace uppercase kind tag (FOLLOW, CHEER, GIFT SUB, KO-FI TIP), the name in bold, then the
  payload - "500 bits", the reward title, the tip amount.
- The whole row is still the replay trigger with the same confirm popover, and a hover-revealed
  Replay pill on the right now says so. On touch screens the pill is always visible.
- Timestamps compacted to "2m" / "4h" / "3d", with the full date on hover.
- Kept deliberately against the mock: icons stay tinted by event type at rest (the shape-plus-color
  pairing is a recorded accessibility decision), and rows stay on the shared collection-row skin
  with the per-event hover accent.
- Selection, gift-sub grouping, resub dedup and delivery outcomes all carried over unchanged. One
  casualty of the rewrite: the "Ko tip-fi" label typo, which had apparently shipped that way.

## OL-2609-007 - September 1st, 2026 - feat(checkin): Chat Checkin - !checkin pins your viewers on a 3D globe

Viewers type `!checkin Rotterdam, NL` in chat and land as a pin on the streamer's overlay - on a
slowly rotating 3D globe whose continents are drawn from the checkin database itself. One command,
one pin per viewer (checking in again moves it), city-level only by construction: places resolve
against a local GeoNames index of ~235,000 cities, so nothing finer than a city can ever reach the
screen. No geocoding API, no per-request cost, no counterparty.

Because it shipped as a full ExternalIntegration, everything Overlabels already does lights up at
once:

- `[[[checkin_globe]]]` drops the globe into any static overlay, styled entirely by CSS custom
  properties and plain CSS on the HTML name labels. The 3D library is a lazy chunk that only
  downloads when a template contains the tag.
- `[[[foreach:checkins as pin]]]` is the raw feed for custom visualizations, newest first, with
  live one-pin delta updates over the existing alerts channel and its own foreach cap.
- Ten provisioned `c:checkin:*` controls: per-stream counters that reset at go-live (checkins,
  unique countries, farthest km) plus persistent latest-pin values - so haversine math lands in
  Expression Controls for free. Set a home city and every pin gets its distance.
- "Chat Checkin" is an alert trigger with a full `event.*` tag set, so a checkin can fire an
  alert, TTS or a chat reply.
- Place resolution is population-ranked with typo tolerance, calibrated against the real index so
  junk misses ("gyat") while typos land ("amsterdamm").

Shipped across OL-2609-003 through OL-2609-007, plus the `!checkin` handler in the bot repo.

## OL-2609-001 - September 1st, 2026 - feat(templates): filter alerts by event assignment

The `/templates` filter bar can now answer "which of my alerts are actually wired up". With Type set
to Event alert, a new Assignment dropdown offers All alerts, Assigned and Unassigned - assignment
meaning an event mapping (Twitch or external) belonging to the viewer, the same per-user scoping the
list's event icons already use. Another user's mapping on a public alert does not count as yours.

- Backend is two `when()` clauses in `OverlayTemplateController::index()`; the param is ignored
  entirely unless the type filter is `alert`, so it can never silently narrow overlays or blocks.
- `?type=alert&assignment=assigned` works as a direct link, and switching Type away from alerts
  drops the param again to keep the URL canonical.
- `FilterBar` is now always a five-column grid on desktop, so browsing between Overlays and Alerts
  never resizes the fields as the fifth one appears - pages with fewer fields leave the trailing
  columns empty.
- Pinned by `TemplateIndexAssignmentFilterTest`; the behavior tests were verified to fail against
  the unfiltered query.
