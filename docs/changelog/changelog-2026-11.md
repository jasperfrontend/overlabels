# Changelog - November 2026

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
