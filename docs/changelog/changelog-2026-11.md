# Changelog - November 2026

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
