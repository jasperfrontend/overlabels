# Changelog - September 2026

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
