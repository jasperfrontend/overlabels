---
title: How Overlabels works - Overlabels Help
description: A tour of Overlabels for new streamers. What an overlay is, how alerts, live data, controls, the chat bot and your overlay link fit together, and where to read more on each part.
heading: How Overlabels works
lead: Overlabels gives you a web page that fills itself with your live stream data. This is the whole system in the order you will meet it, with a link to the full guide for each part.
canonical: https://overlabels.com/help/how-overlabels-works
section: Getting started
keywords: overview, beginner, introduction, what is overlabels
---

## What an overlay is

An overlay is a web page. You load it into OBS as a browser source, and OBS shows it on your stream. You
can write the page yourself in HTML and CSS, or build it without code. Either way, the page updates
itself while you stream.

You do not have to start from nothing. The [Products page](/products) has overlays and alerts you can
install with one click, and everything there is free.

If you want to build your own layout without writing code, the [Builder](/help/builder) lets you place
ready-made blocks on a grid. If you do write code, the [code editor](/help/editor) completes your tags as
you type.

## Static overlays and alerts

Overlabels has two kinds of overlay.

- A **static overlay** stays on screen for the whole stream. A follower counter or a goal bar is a static
  overlay.
- An **alert** fires once when something happens, such as a follow or a donation. It shows for a few
  seconds and then goes away.

Alerts look best when they appear inside a static overlay, because they then share its fonts, colours and
styling. Two tabs on each alert control this. **Triggers** decide which event fires the alert.
**Targeting** decides which static overlay it appears in. If you set no target, the alert shows in every
static overlay you have. Read [Overlays vs Alerts](/help/overlays-vs-alerts) before you set up your first
alert.

## Tags: putting live data on the page

A tag is a short piece of text in double brackets, such as `[[[c:wins]]]`. When the page loads,
Overlabels swaps each tag for its current value. When the value changes, the page changes with it,
without a reload.

Tags can also format values and show different content depending on a value. A formatting pipe turns a
number of seconds into a clock, and an `[[[if:...]]]` block shows one thing or another. See [Conditional
and Event Tags](/help/conditionals) and [Formatting Pipes](/help/formatting).

Overlays cannot run JavaScript. Scripts are removed when you save. Styling, fonts, animations and tags
are all you need for almost every overlay. The [For Designers](/help/for-designers) page covers the
design side.

## Live data from Twitch and donation services

Twitch sends Overlabels each event as it happens: follows, subscriptions, raids, cheers and more.
Overlabels passes each one on to your overlay, along with the counters built from them.

Donations come from services you connect yourself. Overlabels works with Ko-fi, Streamlabs, Fourthwall,
Buy Me a Coffee and Throne. Each connected service adds its own ready-made values to your overlay. The
[Integration Presets](/help/integration-presets) page lists all of them.

Some live data is a list rather than a single value, such as your latest subscribers or a raffle entry
queue. Lists are managed from your dashboard or from chat. See [Lists](/help/lists).

If you want to see how an event travels from Twitch to a finished alert on screen, read [How an overlay
renders](/help/rendering).

## Controls: values you change during the stream

A control is a named value you can change while you stream. Counters, text, numbers, timers and on/off
switches are all controls. You change them in the Control Panel on your dashboard, and the overlay
updates within a few seconds.

An expression control works out its value from other controls. For example, it can add up donations from
several services into one total. Expression controls run on the overlay itself, so they need no extra
server calls. Read [Controls](/help/controls) for every type, and [Expression
Controls](/help/expressions) for the formulas.

## The bot and chat

The @overlabels bot joins your channel as a moderator. You can then change your controls from chat, and
so can your mods, VIPs or viewers if you allow it. You do not need to switch tabs during a stream. The
[Twitch Chat Bot](/help/bot) page explains how it works, and [Bot Commands](/help/bot/commands) lists
every command.

Chat can also appear on your overlay. A single loop puts live chat, with badges and emotes, on screen.
See [Twitch Chat in an Overlay](/help/chat).

Some chat features are ready to install from the Products page. [Chat Checkin](/help/checkin) lets
viewers pin their city on a globe. [Chat Tower](/help/chat-tower) builds one shared tower that the chat
adds blocks to. [Chat Emote Bubbles](/help/chat-emote-bubbles) turns each emote in chat into a bubble
that floats across your stream.

## Your overlay link and its token

Your overlay link ends with a long code after a `#`. That code is the token, a 64-character key that
belongs to your overlay. The part after the `#` is never sent to Overlabels' servers as part of the page
request, so it stays out of logs.

Paste the whole link into OBS. If you think the link has been shared by mistake, for example by showing
it on screen, revoke the token and make a new one. Then update only the part after the `#` in OBS. The
[Overlay Access Tokens](/help/tokens) page explains each step.

## Test before you go live

You do not have to wait for a real follower to check an alert. The [Testing your alerts](/help/testing)
page explains how to fire real Twitch events at your own account from a terminal.

Donation integrations have a test mode for the same reason. It lets you send the same test donation as
often as you like. When you turn test mode off, the service's controls are reset to their starting
values. The [test mode](/help/integration-test-mode) page lists exactly what changes.

If something does not appear on screen, check the steps in [How an overlay renders](/help/rendering) in
order. That shows you which step to look at first.

## Where to start

The quickest way to learn is to build something small. [Show chat on
screen](/help/tutorials/show-chat-on-screen) and [Show my latest
follower](/help/tutorials/latest-follower) each take a few minutes and end with something you can see on
stream. The [help home page](/help) lists every guide, tutorial and reference entry.
