# Changelog - November 2026

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
