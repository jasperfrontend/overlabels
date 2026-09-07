# Changelog - September 2026

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
