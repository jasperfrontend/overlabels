---
title: Testing your alerts with the Twitch CLI
description: "Fire any of 28 real Twitch EventSub events at your own Overlabels account from a terminal. Setting up the Twitch CLI, using the Testing page, what your webhook secret is for, and why you should never show these commands on stream."
heading: Testing your alerts
lead: Fire real Twitch events at your own account from a terminal, without waiting for an actual follower. What the Testing page gives you, how to set up the Twitch CLI, and the one rule about never showing these commands on stream.
canonical: https://overlabels.com/help/testing
context: testing.index
---

You have built a follower alert. To see it, you need a follower. That is a bad development loop.

The [Testing page](/settings/testing) fixes it: it hands you a ready-to-run command for every Twitch event
Overlabels supports, pre-filled with your Twitch ID, your webhook URL and your webhook secret. Paste one
into a terminal and a real EventSub webhook arrives at your account, taking exactly the same path a real
follow would.

## Setup, once

You need the [Twitch CLI](https://dev.twitch.tv/docs/cli/) installed, and you need to have run:

```bash
twitch configure
```

once, with a client ID and secret from a Twitch application. That is the entire setup. The CLI is Twitch's
own tool, not an Overlabels one.

## Using the Testing page

Every event is a row. **Click a row and its command is copied to your clipboard.** Paste it into a
terminal, press enter, and watch your overlay.

- **Filter box** - type `raid`, `poll`, `hype` or any part of an event name to narrow the list.
- **"Show command" checkbox** - reveals the full command inline for every row. It is off by default,
  which is a deliberate choice covered below.
- Events are grouped into 8 families: Basic, Channel Points, Stream, Hype Train, Charity, Goals, Polls
  and Predictions. 28 event types in total.

A copied command looks like this:

```bash
twitch event trigger channel.follow \
  --transport=webhook \
  -F https://overlabels.com/api/twitch/webhook \
  -s your_webhook_secret \
  --to-user your_twitch_id \
  --from-user 1234567
```

`--from-user` is the fake viewer the event comes from. Change that number and the alert shows a different
user ID - handy for checking how your layout copes with a long name versus a short one.

> [!WARNING]
> **Never show these commands on stream, and never paste one into chat.** The `-s` flag is your webhook
> secret in plain text. Anyone who has it can send your account convincing fake events - fake raids, fake
> donations, fake subs - and your overlay will believe every one of them. This is why the commands are
> hidden behind a checkbox rather than shown by default: the page is designed to be safe to have open
> while you are live.

## Your webhook secret

The secret is what proves an incoming webhook genuinely came from Twitch. Every request is signed with it
using HMAC-SHA256, and Overlabels rejects anything whose signature does not verify.

Each account gets its own 32-byte secret, generated when the account is set up. If yours has not been
generated yet the Testing page tells you so and falls back to the instance-wide secret - the commands
still work, they are just not unique to you.

## What to expect

A triggered event is a **real** event as far as Overlabels is concerned. It is verified, stored,
deduplicated, and it will:

- Fire the alert template mapped to that event type, if you have mapped one
- Update any controls the event feeds
- Appear in your events feed alongside genuine events

That last point is the one that surprises people. Test events are not marked as tests - the whole value is
that they are indistinguishable from the real thing. If you fire twenty test cheers, your events feed has
twenty cheers in it.

> [!NOTE]
> **Alerts need a static overlay to render into.** If a command runs cleanly and nothing appears, the
> usual cause is that no static overlay is open in the browser source, not that the event failed. See
> [Overlays vs Alerts](/help/overlays-vs-alerts).

## Testing things that are not Twitch

The Twitch CLI only speaks Twitch. For the donation integrations - Ko-fi, StreamLabs, Fourthwall, Buy Me a
Coffee, Throne - each service has its own test facility, and Overlabels can replay any external event it
has already received from your events feed. Replay is usually the faster loop: get one real donation, then
replay it as often as you like while you build the alert.

## Related

- [How an overlay renders](/help/rendering) - the pipeline your test event travels through
- [Overlays vs Alerts](/help/overlays-vs-alerts) - why an alert needs a static overlay
- [Twitch EventSub Reference](https://dev.twitch.tv/docs/eventsub/eventsub-reference/) - the payload of every event
