# Changelog - November 2026

## OL-2611-013 - November 14th, 2026 - perf(twitch): answer the webhook first, process on a dedicated twitch-events worker

A stress test of the new alert queue turned up a slow webhook: 30 follows posted at once took prod 17
seconds to get through, and 14 of the calls never got their 200. Each notification did all its work
before answering Twitch: avatar lookups, counters, the alert with its own Twitch API calls. About
1.4 seconds per follow, on a web server that runs only a few PHP threads. Twitch revokes a
subscription that keeps failing to answer, so a follow-bot wave could have cost a streamer their
alerts.

- **The webhook answers straight away.** It checks the signature, removes what we must not keep,
  stores the event (which is also the guard against Twitch's redeliveries) and answers. Everything
  else happens a moment later.
- **A worker of its own.** The rest runs on a new `events` worker that only handles Twitch events,
  one at a time and in the order Twitch sent them, so a gift bomb's recipients still meet their
  gift first. Alerts never wait behind a text-to-speech job on the main worker.

## OL-2611-011 - November 14th, 2026 - feat(alerts): alerts take turns, and a gift bomb is one alert

Three raids fired a second apart showed the first and the last: every alert replaced whatever was on
screen, and an alert whose source had to be fetched was dropped if another arrived before the fetch
finished. A busy moment on stream lost alerts. They now take turns, the way StreamElements and
others play them.

- **Alerts queue.** An alert that arrives while another is showing waits for it, in timestamp order,
  with a short gap in between. One that is still loading keeps its place in line. Nothing jumps the
  line. At most 25 wait, and one that waited 3 minutes is dropped rather than shown late.
- **Live alerts still update in place.** Poll, prediction, hype train, goal and charity progress
  arrive over and over for one ongoing thing; those update the alert on screen instead of queueing
  copies. The alert broadcast now names its event type, because the alert's data only holds the
  tags its template uses.
- **Sound and TTS play when the alert shows**, not when it arrives, so a queued alert never talks
  over another. A dropped alert stays silent.
- **A gift bomb is one alert.** Twitch sends the gifter's message with the final number first
  ("gifted 25 subs"), then one gifted-sub message per recipient over the next seconds, 13 of them for
  25 gifts on prod. Each recipient used to fire its own New Subscriber alert, which would now queue
  for minutes. When a Gift Subscription alert fires, the next that-many gifted subs are absorbed: no
  alert, no TTS, no chat line, but they still count in counters, sub lists and tags. The countdown
  expires 2 minutes after the last gift or recipient, subs people pay for themselves are never
  absorbed, and without a Gift Subscription alert nothing changes.
- **The delivery log says so.** Absorbed subs are recorded as "part of a gift alert" on the stream
  sessions page.

## OL-2611-010 - November 10th, 2026 - feat(alerts): alert products ask for the Alerts overlay, and /kits leads with the ready-made kits

The loose ends of the Alerts overlay (OL-2611-007) and the kit picker (OL-2611-009).

- **Alert products point at the Alerts overlay.** The OBS step of the five donation alert products
  used to offer the person's five newest overlays, or send them off to make one. It now asks for the
  Alerts overlay with the same create and Add to OBS panel as everywhere else. It still counts as
  done when another overlay of theirs has been in OBS lately, because until the Alerts overlay is
  live the alert really does show there.
- **The products say so.** The five alert products and their alert documents now say the alert
  shows inside the Alerts overlay, not in every overlay in OBS.
- **/kits opens with the ready-made kits.** The same cards as the kit picker, above "Your kits",
  so an account past its first login finds them too. They no longer repeat in the public list.

## OL-2611-009 - November 10th, 2026 - feat(kits): new accounts pick a kit, and ready-made kits lead the Alerts shelf

Every new account used to get a silent copy of the starter kit, Midnight Purple, with its Twitch
alerts wired by matching words in the alert names. Nobody chose it, and a streamer who wanted
donation alerts was never shown that Stamped or Subtle existed. Kits are the quickest way to get
something good-looking on stream, so they are now the front door.

- **A kit picker for new accounts.** After their first Twitch login, a new account lands on
  "Pick your alerts" (`/start`): the featured kits as cards with their thumbnail and what they
  cover, each one opening the kit page where its alerts play before anything is copied. "Skip for
  now" goes to the dashboard. Someone who signed up on their way to a product still goes there.
- **No more silent copy.** Onboarding no longer copies the starter kit or guesses which alert goes
  with which Twitch event. A new account owns nothing until it picks.
- **Ready-made kits lead the Alerts shelf.** On /products, the Alerts shelf now opens with the
  featured kits. The five single-service alerts stay underneath as "Basic alerts", with their
  heads-up and description unchanged.
- **Featured is a flag.** A kit shows up in both places when it is public and has `featured_at`
  set. It is set by hand, and a copy of a featured kit never inherits it.
- Not in this change: a featured section on the Kits page itself, and a way for existing accounts
  to find the picker.

## OL-2611-008 - November 10th, 2026 - feat(alerts): alerts carry no controls and no Add to OBS; their pages show the Alerts overlay instead

The alert editor was a copy of the static overlay editor: it offered Controls, Values and Add to
OBS, none of which make sense for an alert. A control on an alert never reached the screen, since
an alert renders inside a static overlay and only that overlay's controls are sent. And adding an
alert to OBS on its own sidestepped the Alerts overlay that OL-2611-007 introduced.

- **No more Controls, Values or Add to OBS on alerts.** An alert's edit and view pages end with an
  "On stream" tab instead. It says that an alert does not go into OBS on its own, and shows the
  account's Alerts overlay: create it, add it to OBS, and whether OBS has loaded it.
- **Creating an alert shows the same panel.** Picking "Event Alert" on the create page puts the
  Alerts overlay right under the tips, and creating it there keeps the half-written alert.
- **The server agrees.** Adding or importing a control on an alert is refused. TTS and chat lines
  keep reading any of the account's controls, so nothing they did needed one on the alert.
- **One panel everywhere.** The kit page's Alerts overlay block became `AlertsOverlayPanel`, used on
  the kit page, both alert pages and the create page.
- Left alone: an alert already added to OBS on its own still renders, and controls already on an
  alert stay where they are.

## OL-2611-007 - November 10th, 2026 - feat(alerts): one Alerts overlay per account, and untargeted alerts render only there once OBS has it

An alert never renders on its own: it renders inside a static overlay that is in OBS. Until now an
alert with no targeting rendered in every static overlay of the account, so a streamer with two
overlays in OBS saw every alert twice, and a new streamer who installed an alert product with no
overlay in OBS saw nothing at all. Kits and alert products had no answer to "where do these show
up".

- **Every account can have one Alerts overlay.** A transparent, private static overlay that exists to
  hold alerts. A kit's owner creates it from the kit page and adds it to OBS with the usual Add to
  OBS button.
- **Once OBS has loaded it, untargeted alerts render there only.** The first load is stamped on the
  account. Before that, nothing changes: alerts keep rendering in every overlay, so creating the
  overlay can never take alerts off a stream before it is in OBS. Alerts with explicit targets keep
  them.
- **Kits can ship settings for their alerts.** A kit that contains its author's Alerts overlay
  merges that overlay's controls into the copier's own Alerts overlay when copied, instead of adding
  a second overlay. The Subtle kit's corner picker works this way.
- **The kit page shows where alerts go.** Above "On your stream", the owner of a kit with alerts sees
  their Alerts overlay: a create button, Add to OBS, and whether OBS has loaded it yet.
- Not in this change: the alert editor still has Controls, Values and Add to OBS tabs, and the alert
  products still say alerts show in every overlay. Both are next.

## OL-2611-006 - November 9th, 2026 - feat(kits): play a kit's alerts before installing it, and copying a kit wires them up

A kit page used to be a thumbnail and a list of names. Now a kit with alerts has a stage beside its
header: the alerts play over a stand-in stream, one button per event they fire on, with sound on
click. Before anyone clicks, it cycles through them silently so the page is never a still picture.

- **Sample events for everything.** `AlertEventSamples` holds a sample for every event the Triggers
  page offers: 27 Twitch events and every external service event, with variants where alerts
  usually branch (a Ko-fi first month and a renewal, a Throne surprise gift and a crowdfunded one).
  Each is a canned raw payload run through the real code a live event goes through: the service
  driver's `normalizeEvent()` and locale formatting, or the Twitch event mapper. A test reads the
  reference page of every Twitch event and fails if a sample does not produce a tag it documents.
- **Kits declare their triggers.** The kit form has an "Alert triggers" section: for each alert,
  which events fire it and for how long. It starts from the author's own Triggers page. One event
  belongs to one alert.
- **Copying a kit wires it.** "Copy this kit" now points the copier's events at the copied alerts.
  An event they already have an alert for, on or off, is left exactly as it is, and the toast says
  how many were wired and how many were left alone. The copy keeps its triggers, so a copy of a
  copy works the same. Onboarding's starter-kit copy wires nothing, as before.
- **The owner sees the state.** Under the stage, a kit's owner sees each event: wired, switched
  off, handled by another of their alerts, or not wired, plus a Connect button for a service that
  is not connected yet.
- **Private means not shared, not unusable.** A private alert in a public kit plays in the
  preview. The kit page no longer sends every template's full source to every visitor, though:
  the listing carries names only, and source goes out solely to render the preview.
- Older kits that declare nothing preview from their author's own Triggers, and an alert nothing
  points at plays with a sample Ko-fi tip.
- Not covered: motion driven by Expression Controls (no engine runs in the preview) and live
  values, which never appear by design.

## OL-2611-005 - November 9th, 2026 - feat(alerts): alerts of any size fire, and an alert's head finally applies

An alert used to travel to the overlay whole, html and css included, on every single fire. Reverb
refuses any broadcast over 10 KB, so an alert bigger than that could be saved, mapped and tested in
the editor, and then never appear on stream. The delivery ledger marked it `failed` and nothing said
why. Found while building the "Stamped" donation alerts kit, whose first draft was 10.2-11.6 KB per
alert and would never have shown.

- The overlay now loads the source of every alert that can fire on it when it opens (`alert_sources`
  in the render payload: head, html, css and a content-hash version), the same way it already
  preloaded compiled CSS and alert sounds.
- A broadcast carries the alert's slug, its version and the event data. The source rides along only
  while the whole message stays under 9000 bytes; past that it is sent as empty strings with
  `source_omitted: true`. A small alert therefore still arrives complete, which keeps overlays that
  were open in OBS during the deploy working without a reload.
- An alert the overlay has no current copy of (created or edited after OBS loaded it, and too big to
  inline) is fetched once from `POST /api/overlay/alert-source`, a token-authenticated read in the
  same shape as the render call. It writes nothing and records no access. Only the token owner's
  own alerts resolve.
- An alert's `head` field now works. It was never in the broadcast, so a font `<link>` in an alert's
  head did nothing and authors had to `@import` instead. Head stylesheets are also warmed when the
  overlay opens, so the first alert of a stream does not paint in a fallback font.
- Verified end to end on local Reverb in Firefox with a 21.9 KB alert: it fired from the mount-time
  copy, then again after an edit through the fetch, and a small alert still arrived inline.
- Chunking the broadcast across several messages was considered and rejected: it would keep
  resending a template that never changes between fires, add ordering and loss handling, and
  multiply the metered message count.

## OL-2611-004 - November 8th, 2026 - feat(store): Paddle fulfillment backend for one-off product purchases

The groundwork for selling paid products. Paddle is the merchant of record: it takes the payment,
charges the VAT, handles refunds and chargebacks, and issues the invoice as "Overlabels via
Paddle.com". Overlabels only has to know who bought what, and this is that half. There is no Buy
button yet, on purpose.

- Paddle reports every sale to `POST /api/paddle/webhook`. The signature is checked on the raw body
  before anything is read. A delivery that fails the check, or fails to save, is answered with an
  error so Paddle tries again (up to 60 times over about two days on live).
- A sale is tied to an Overlabels account through the checkout's `custom_data`
  (`overlabels_user_id`). Verified in the sandbox: the value survives to `transaction.completed`
  unchanged. A sale that names no real account is still recorded, with no user, so it can be
  matched by hand.
- Purchases are one-off. There are no plans and no subscription state; subscription events are
  acknowledged and ignored. An approved full refund or chargeback takes the product back, a
  reversed chargeback gives it back. `PaddlePurchase::userOwns($user, $productId)` is the check.
- Nothing about the buyer is stored beyond the Paddle customer id and which account it belongs to.
  No email, no address: Paddle keeps the billing details.
- `/settings/billing/portal` sends a buyer to their Paddle customer portal for their purchases and
  invoices. Nothing links to it yet.
- Prod needs `PADDLE_ENVIRONMENT`, `PADDLE_API_KEY`, `PADDLE_CLIENT_TOKEN` and
  `PADDLE_WEBHOOK_SECRET` before it can take a delivery.

## OL-2611-003 - November 7th, 2026 - feat(chat): let chatters redact their message on the chat overlay with !hide

A chatter types `!hide my message here` and the chat overlay draws "my message here" in the Redacted
font: solid bars where the words were, in the message's own colour. It is only the overlay. The
message is in Twitch chat as normal, for every viewer and moderator, and in the VOD.

It is off until the streamer turns it on, on the chat settings page (`/settings/chat`), where the
command word can be changed too. Telling chat about it is up to the streamer. The Twitch Chat Overlay
product page has a card saying whether it is on, which opens that setting.

The overlay does the work itself, from the chat connection it already has, so nothing new passes
through Overlabels and there is no bot cooldown in the way. It works in every chat overlay, including
ones installed before today and hand-written ones, without a reinstall.

- The whole message is hidden: emotes are drawn as bars rather than images, and a hidden message
  sends nothing to Chat Emote Bubbles.
- Characters the Redacted font cannot draw (emoji, Cyrillic, Japanese and so on) are swapped for an
  `x` first, so no message falls back to a readable font.
- The setting that hides `!` commands from the overlay does not hide a `!hide` message, and Plinko
  still hears every message as typed. The word cannot be the same as your Plinko word.
- Moderator deletions and timeouts still remove a hidden message.
- Templates get `[[[msg.redacted]]]` to style the whole row. `[[[msg.text]]]` is not redacted; the
  hidden version is in `[[[msg.html]]]`.
- After changing the setting, reload the chat overlay's browser source in OBS.

## OL-2611-002 - November 7th, 2026 - feat(products): Twitch Chat Plinko, the sixth product

Your chat types `!drop` and a ball with their name on it bounces down a Plinko board on stream. The
middle slots pay 5, the rare edges pay 1000, and the six latest drops and five best totals fill in
beside the board. It installs in one click like every product, needs nothing connected and no bot.

It is the first product that ships its own script. The overlay is one tag, `[[[plinko_board]]]`, built
the way `[[[checkin_globe]]]` is: the renderer swaps it for a canvas and loads the board's code only
when an overlay uses it. The board reads `!drop` straight from Twitch chat over the overlay's own
connection, the same one Twitch Chat Overlay and Chat Emote Bubbles use, so no message passes through
Overlabels and there is no command cooldown in the way.

Chat order is game order. Each chatter has one ball at a time, balls join the board at ten a second up
to 150 at once, and everyone else waits in a line that shows on screen with the next five names. That
is what keeps a raid of thousands a game rather than a pile: measured at 20, 200, 2,000 and 20,000
players typing `!drop` at once, it held 61 fps throughout. Balls bounce off pegs and off each other,
pegs light as they are hit, and the slot a ball lands in flashes.

Mods stay in charge: a deleted `!drop` loses its ball or its place in line, and a timeout or ban also
takes the chatter's score. Chatters hidden on the chat settings page never get a ball, while the
setting that hides `!` commands from a chat feed does not stop Plinko, which listens for exactly those.

- New product page, hero, product icon and help guide at `/help/twitch-chat-plinko`.
- `!drop` is the default command; each streamer can choose their own word on the Plinko settings page
  (Settings, Integrations, Plinko), which appears once Plinko is installed,
  since `!drop` is already a well-known command from another chat game.
- Rows, pace, cap and colours are CSS custom properties on `.ol-plinko`.
- Scores count from when the overlay loaded; reloading the browser source starts a new game.

## OL-2611-001 - November 6th, 2026 - feat(users): delete accounts that did nothing in their first 48 hours

> **Not for the blog.** Leave this entry out of `/changeblog` posts and the November highlights
> post entirely, not even a softened mention. It is housekeeping, and "accounts that do not use
> the product get deleted" reads as cold to the very people the posts are written for. The claim
> file stays as the record.

Every signup gets a copy of the starter Kit, EventSub subscriptions and a place on the broadcast
service, whether or not the person ever comes back. Most of those accounts were never used: they
held data nobody needed, and anyone returning to one landed on a dashboard with a stale Kit in it
instead of a fresh start.

`users:prune-idle` now runs every hour and hard-deletes an account once it is older than 48 hours
and all three of these hold:

- it never installed a product (the welcome funnel's install step),
- it has no active overlay token (revoked and expired tokens do not count),
- it owns nothing beyond what signing up created: the starter Kit copy, its overlays, their
  controls and the alert mappings onboarding wired to them. Editing that Kit does not count as
  using the account.

Anything else of its own keeps an account: another overlay, Kit or control, a connected
integration, the bot switched on, a bot command or alias, a list command, a saved look, an uploaded
image. Admins, the system user and banned accounts are never touched; a ban has to outlive the
account, or deleting it would hand a banned streamer a clean signup.

Deletion goes through `UserDeletionService`, the same path as self-serve account deletion, so the
Twitch subscriptions, the Twitch login grant and stored images are released too. Each run deletes at
most 100 accounts and re-checks each one just before deleting it. `--dry-run` lists who would go.
This applies to existing accounts as well as new ones.
