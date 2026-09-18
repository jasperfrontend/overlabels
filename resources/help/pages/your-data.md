---
title: Your data on Overlabels - what we hold, and what we deliberately do not
section: Getting started
description: "A complete, honest inventory of the data Overlabels stores: your Twitch account, your events, your integrations, your viewers, how long each thing is kept, where it physically lives, and exactly what deleting your account does and does not remove."
heading: Your data on Overlabels
lead: A full inventory of what Overlabels holds about you, what it deliberately throws away, how long the rest is kept, and what actually happens when you delete your account. No summary, no hand-waving.
canonical: https://overlabels.com/help/your-data
keywords: privacy, GDPR, delete my account, erasure, data retention, what do you store, donor email, personal data, security, backups, ip address, tracking, analytics, cookies
context: settings.account
---

Most services answer "what do you keep about me?" with a privacy policy written by a lawyer for another
lawyer. This page is the other thing: a walk through the actual database, table by table, written for
the person whose data is in it.

Where something is uncomfortable, it is on this page anyway. A list of what we hold is only worth
reading if it includes the parts we would rather leave out.

## The short version

- **We never store your email address.** It is stripped out of the Twitch login response before
  anything is written down, and there is no email column to put it in.
- **There is no password**, because there is no password login. Twitch is the only door.
- **We hold no payment information of any kind.** Not yours, not your supporters'. Overlabels never
  touches money.
- **The database is not reachable from the internet.** Not firewalled off, not obscured: simply not
  published to a public address at all.
- **Almost everything expires.** Events, logs, snapshots and audio all age out on a schedule.
- **Deleting your account is a real delete**, not a flag on a row. It also does not remove everything,
  and the honest list of leftovers is [further down](#deleting-your-account).

## Your account

When you log in with Twitch, Twitch hands us a profile. The first thing that happens to it is that
three fields are taken out and thrown away: `email`, `email_verified` and `verified`. That happens
before the profile is written anywhere, so there is no window during which it was stored.

This is not a setting, and it is not a policy we are promising to follow. The `email` column was
dropped from the users table outright, and the migration that dropped it also went back through every
profile already stored and stripped the key out of the saved copy, so the old JSON could not outlive
the column. There is nowhere left to put an email address even if we wanted one.

The same migration dropped `password` and the password reset table. Overlabels cannot have a leaked
password database, because it does not have a password database.

What is kept about you:

| What | Why |
|------|-----|
| Twitch display name, Twitch user ID | You are identified by your Twitch ID, never by email |
| Avatar **URL** | A link to Twitch's CDN. We never download or re-host your profile picture |
| Access token and refresh token | So overlays can read your channel data while you are away from the keyboard |
| Which scopes you granted | So a feature you did not authorize is skipped instead of failing |
| A per-account webhook secret | Used to verify that a Twitch webhook really came from Twitch |
| Your settings | Locale, loop limits, chat filters, living title, bot toggles |

Your Twitch tokens are **encrypted at rest**, like every other credential on the platform. They are
also hidden from every API response, never written to a log, and never sent to your browser. A copy
of the database without the application's encryption key contains no usable Twitch credentials.

Overlabels holds **one** write permission on your Twitch account: the one that sets your stream title
and category, used only by [Living Twitch title](/help/living-title). Every other scope is read-only.

### The channel snapshot

This is the largest single piece of Twitch data we hold and it is worth knowing about.

Each time you log in, Overlabels fetches your channel information, your followed channels, a page of
your followers, your subscribers and your goals, and saves that bundle against your account. It is
what makes an overlay render instantly instead of waiting on six Twitch API calls, and it is what the
tag browser shows you your real numbers from.

It is a snapshot, so it includes **other people's Twitch display names**: the followers and
subscribers in those lists. It is refreshed wholesale on each login, and it goes when your account
goes.

## Your events

Every Twitch event Overlabels is subscribed to on your behalf gets written down: follows, subs, gifts,
cheers, raids, channel point redemptions, hype trains, polls, predictions, charity campaigns, goals and
stream start/stop.

**The event is stored exactly as Twitch sent it**, with one addition: we look up an avatar URL for each
Twitch user the event mentions and attach it, so your alert can show a face without another API call.

That means an event row contains the other person's Twitch ID, login, display name and avatar URL,
plus whatever that event type carries: a bit count, a sub tier, the text someone typed into a channel
point redemption, the message attached to a resub.

Two things are taken out before an event is stored at all. Twitch's prediction events name every
viewer who bet, with how many channel points they staked and won; that list is dropped, because a
progress bar does not need to know who gambled what. And if a cheer is marked anonymous, the
identifying fields are cleared on our side rather than trusting them to already be empty, so an
anonymous cheerer cannot end up on screen through a replayed or hand-built event.

**Twitch events are deleted after 90 days.** There is no archive and no export of them.

## Chat

**Overlabels does not receive your chat.** When your overlay renders chat, the overlay connects
straight to Twitch itself, anonymously, the same way any chat reader does. The messages travel from
Twitch to the browser source in OBS and never pass through our server. Nothing is stored, and chat on
your overlay keeps working even if Overlabels is down.

There are exactly two exceptions, and both are narrow:

**One message at a time.** So that `[[[c:latest_chat_message]]]` can exist, the bot sends a summary
every 30 to 60 seconds containing a message count, the logins seen in that window, and the single most
recent message. That one message is stored in a control and overwritten by the next summary. There is
never a second message, and there is no history.

The list of logins seen this stream lives in memory (Redis, not the database), expires after 12 hours
and is cleared the moment you go live again. It exists to count unique chatters and holds nothing but
logins.

**Sub notifications.** Twitch's chat notification event, which is how resubs and announcements are
reported, is stored like any other event. Those payloads include the message the viewer wrote with
their resub. They follow the same 90-day deletion as everything else.

If you have hidden chatters on your [chat settings page](/settings/chat), that list is sent to your
overlay so it can filter them out, because the filtering happens in the browser. It is deliberately
excluded from every other API response, since a list of people you would rather not see on screen is
nobody else's business.

## Donations and integrations

This is the section to read carefully, because most of the data here is not yours. It belongs to the
people who support you.

When a donation service sends us a notification, Overlabels keeps a copy of it alongside the small
normalized version your overlay reads. The copy is what makes "replay this event" and "why did that
alert not fire" answerable.

**The copy is scrubbed before it is written.** Donation services send far more than an overlay needs,
and the surplus is other people's personal data:

| Removed on arrival | Who sends it |
|--------------------|--------------|
| Email address | Ko-fi, Fourthwall, Buy Me a Coffee |
| Postal address and phone number | Ko-fi on shop orders and commissions, Buy Me a Coffee on commissions |
| Discord username and user ID | Ko-fi |

These are dropped by key name wherever they appear in the message, at any depth, before anything
reaches the database. The match is on the name rather than a list of known fields, so a service that
adds a new one, or moves it somewhere else, is covered without us having to notice first.

> [!NOTE]
> This is why a Ko-fi commission is safe to accept through Overlabels. A commission is the one
> donation that carries a full delivery address, because somebody is having something posted to them.
> That address never lands in our database.

What survives is what an overlay actually uses: the supporter's display name, their message, the
amount, the currency, the item name on a gift. Those are the values your controls hold, and they stay
until the next donation replaces them.

| Service | How it connects | What we hold to connect |
|---------|-----------------|--------------------------|
| **Ko-fi** | Webhook | Your Ko-fi verification token, encrypted |
| **Streamlabs** | OAuth, then a socket listener | Access, refresh and socket tokens, encrypted |
| **Fourthwall** | OAuth, we register the webhook for you | Access and refresh tokens, encrypted |
| **Buy Me a Coffee** | Webhook | Your webhook secret, encrypted |
| **Throne** | Webhook | Nothing. Throne signs its messages with a key we already have |

Every integration credential is encrypted before it is written to the database.

Each connected service also gets a set of controls, and those hold the visible part of a donation
indefinitely, until the next one replaces it: the donor's name, their message, the amount and the
currency. That is the point of them, and it is why
`[[[c:kofi:latest_donor_name]]]` can put someone on your screen.

Overlabels never sends anything to Ko-fi, Buy Me a Coffee or Throne. With Streamlabs and Fourthwall we
exchange OAuth tokens, and with Fourthwall we register and later remove a webhook on your shop. No
donation data ever travels outward.

## Your viewers

Some features exist to put viewers on screen, so they necessarily remember viewers. The rule we hold
ourselves to here is narrow: a viewer never signed up for Overlabels, so we keep only what they chose
to put on your stream, and we keep it on a clock.

**Chat Checkin** stores, for each viewer who checks in, their Twitch ID, login, display name, the city
they named, its country and its coordinates. Cities only, never a precise location, and the lookup runs
against a gazetteer stored on our own server, so a viewer's city is never sent to a third-party
geocoder. Each viewer has one current pin, and the history of every checkin lives in the event log
under the same 90-day expiry. **A pin is deleted 90 days after the viewer last checked in**, so a
regular stays on your globe and someone who passed through once in March does not stay forever.
Pins survive you disconnecting the integration, on purpose, so disconnecting and reconnecting does
not wipe your globe.

**Chat Tower** stores, for each block standing in the tower, the stacker's Twitch ID, login, display
name and their Twitch chat colour. A topple deletes every block. As with Checkin, the event log keeps
the history for 90 days.

**Lists**: if you run a command that lets chat add to a list, the append history keeps who added what,
by login, so you can answer "what did that person actually submit?". That history is deleted after 90
days, and immediately when the list or the command is deleted. The list contents themselves are yours
and stay until you clear them.

**GPS**, if you use it, records your own precise location: latitude, longitude, altitude, speed,
bearing, accuracy and phone battery, one row per ping. This is the most sensitive data on the platform
and it is yours. If you turn on map sharing, only position, speed, bearing and tracking state are
published; battery, accuracy and distance are deliberately withheld, and you can delay the public feed
by up to five minutes.

## IP addresses

We do record IP addresses, in four specific places. None of them is analytics.

| Where | Why | Kept for |
|-------|-----|----------|
| Your login sessions | Standard session security | Until the session expires |
| Overlay access logs | So you can see what is using each overlay token, and so we can tell which overlays are live in OBS | 90 days |
| Admin action log | So every administrative action is attributable | 2 years |
| Overlay reports | To spot one person filing dozens of reports | 180 days after the report is handled |

Your IP address is never sent anywhere else. There was, until recently, a button on an internal page
that resolved an address to a city through an outside service; it has been removed, along with the
code behind it.

## What we do not collect

- **No analytics and no tracking.** There is no Google Analytics, no Plausible, no Fathom, no PostHog,
  no Mixpanel, no Hotjar, no Matomo, no Facebook pixel. The application page loads one external thing:
  a webfont.
- **No error reporting service.** No Sentry, no Bugsnag. Errors go to the server's own log.
- **No debug capture in production.** Telescope, which records request contents, is switched off in
  production and is not even loaded outside local development.
- **No email address**, and no way to receive one.
- **No payment or financial data.** Amounts arrive as numbers attached to donation notifications. No
  card, bank or billing identity is ever involved.
- **Your overlay token is not stored.** See [Overlay Access Tokens](/help/tokens): we keep a one-way
  hash of it, so a copy of the database contains no usable overlay credentials.

## Where it physically lives

Everything runs on a single server in **Frankfurt**, in containers.

The database is a container on that server. Its port is bound to the machine's own loopback address,
which means it is not published to the internet at all: there is no address on the public network that
reaches it, firewall or no firewall. The application reaches it over a private container network by
name. The same is true of Redis and of the expression engine. The only way in is an SSH session on the
server itself.

The site sits behind Cloudflare, so Cloudflare handles the encrypted connection and sees the traffic,
as it does for a large share of the web.

Images you upload, meaning overlay screenshots and kit thumbnails, live in a separate Cloudflare
storage bucket in the EU and are served from `images.overlabels.com`. **Those URLs are public.** There
is no signature and no expiry: anyone with the link can load the image. They are stripped of metadata
on upload, so a screenshot carries no camera or location data, but treat an uploaded image as public
from the moment it exists.

## Backups

The database is dumped **once a day at 16:00 UTC** and that single dump is uploaded to **two
independent providers**: Cloudflare R2 in the EU jurisdiction, and Scaleway in Paris. If either upload
fails, the whole run is treated as a failure and an alert goes out. A separate dead-man's switch
notices if the backup never runs at all.

Both buckets are private, with no public access and no website hosting enabled. Each has a lifecycle
rule that deletes anything older than **30 days**.

> [!NOTE]
> Backups are not encrypted by us before upload. This was a deliberate trade: the dumps sit in EU
> storage, encrypted at rest by the provider, under a data processing agreement, and the alternative
> was a passphrase whose loss would destroy every historical backup at once. The dumps do contain
> Twitch tokens, which is the main reason access to them is as narrow as it is.

The practical consequence for you: **for up to 30 days after you delete your account, your data still
exists in those backups.** It is not reachable from the application and nothing reads it, but it is
there until the dump it lives in ages out.

## Nothing is kept forever

Every high-volume table expires on a schedule:

| What | Deleted after |
|------|---------------|
| Twitch events | 90 days |
| Donation and integration events | 90 days |
| Overlay access logs | 90 days |
| Bot messages waiting to be sent | 7 days |
| Generated alert audio | 7 days |
| List snapshots | 30 days, unless you pin one |
| Uploaded images nobody claimed | 30 minutes |
| Handled overlay reports | 180 days |
| Check-in pins | 90 days after that viewer last checked in |
| List append history | 90 days |
| Admin action log | 2 years |
| Database backups | 30 days |

## Who at Overlabels can see your data

Honesty matters more than reassurance here.

Overlabels administrators can see the events on your account, your overlays and kits, your overlay
tokens and their access logs including IP addresses, and the login sessions on your account. They can
also **impersonate** your account, which is a complete identity swap: while impersonating, an
administrator sees and can do exactly what you can, including editing your overlays and acting on your
Twitch channel.

Starting and stopping an impersonation is written to an append-only audit log, with the administrator's
identity, your name and Twitch ID, and their IP address. Actions taken during an impersonation are not
separately marked as such.

The administrator role is held by the people who run Overlabels, and it is granted to as few accounts
as the job allows.

## Deleting your account

The delete button is on your [account settings page](/settings/account) and asks you to type
`DELETE ACCOUNT` to confirm.

It is a **hard delete**. Your user row is removed from the database, not flagged as deleted. It takes
with it, in one transaction:

Your overlays and blocks, your kits, your controls, your Expression Controls, your Lists and every
snapshot and append history attached to them, your bot commands and aliases, your saved sounds, your
overlay access tokens and their logs, your recipe and product installs, your check-in pins, your tower
blocks, your stream sessions and stream state, your integration connections and their encrypted
credentials, and every donation and integration event ever received for you.

Your overlay tokens stop working immediately, so anything in OBS goes blank.

It also reaches outside the database. Your uploaded screenshots and kit thumbnails are deleted from
the image bucket, your Twitch grant is handed back so Overlabels disappears from your connections
list, your EventSub subscriptions are cancelled at Twitch, and a Fourthwall webhook, if you had one,
is removed from your shop. Any of those can fail if the other service is unreachable; none of them
can stop the deletion.

Your login sessions go, and your event history is deleted rather than merely unlinked.

### What deleting does not remove

- **Backups**, for up to 30 days. The nightly dump your data was in has to age out on its own; nothing
  reads it in the meantime.
- **The fact that an administrator acted on your account**, if one ever did. The entry stays, because
  an append-only log that entries can vanish from is not a log. Your name and Twitch ID are taken out
  of it, and the entry itself is deleted after two years.
- **Anything you put somewhere public**, such as an overlay you shared or a kit someone copied. A copy
  belongs to whoever made it.

### Disconnecting Twitch is not deleting your account

This catches people out, so it is worth being blunt about.

If you go to Twitch and revoke Overlabels' access, Twitch tells us, and we mark the affected event
subscriptions as revoked. **That is all that happens.** Your overlays, controls, Lists, events,
integrations and settings all stay exactly where they are. Your account still exists. Nothing is
deleted.

Revoking on Twitch stops new data arriving. Deleting your Overlabels account removes the data already
here. They are two separate actions and doing one does not do the other.

If you want both, do both: delete your Overlabels account first, then revoke at Twitch.

## Asking us something

If you want to know what is held on your account specifically, or you want something removed that this
page says would survive, write to **privacy@overlabels.com**. You do not need to cite a regulation and
you do not need a reason.

## Related

- [Overlay Access Tokens](/help/tokens) - how the token in your overlay URL works and what to do if one leaks
- [Twitch Chat in an Overlay](/help/chat) - why chat never touches our server
- [Privacy Policy](/privacy) - the formal version
