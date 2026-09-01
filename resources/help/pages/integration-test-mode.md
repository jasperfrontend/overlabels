---
title: Test mode on donation integrations - what it does and when to use it
description: "What the test mode switch on Ko-fi, Buy Me a Coffee, Fourthwall, StreamLabs and Throne actually does: repeats are accepted, nothing counts toward usage, and turning it off resets the service's controls to their starting values."
heading: Test mode on donation integrations
lead: Every donation integration page has a test mode switch. It lets you send the same test donation over and over while you build your alerts, and it cleans up after itself when you turn it off. Here is exactly what happens on both sides of the switch.
canonical: https://overlabels.com/help/integration-test-mode
keywords: test mode, testing donations, test donation, test webhook, duplicate event, starting total, seed, reset controls, practice
---

## When you want it

You are setting up a Ko-fi alert, or a donation goal bar, and you want to see it fire before a real
supporter does. Every service has a way to send a fake donation to your webhook: a test button on the
page where you pasted your Overlabels webhook URL, or a test donation from its dashboard.

Press that button twice without test mode and only the first one shows up. Press it with test mode on
and every press lands, exactly like a real donation would.

## What turning it on does

**Repeats are accepted.** Normally, if a service sends the same donation twice (they retry when a
delivery looks slow), Overlabels keeps the first and quietly drops the rest, so a retry can never
double-count a supporter. A test button sends the same donation every time, and without test mode
that protection eats every press after the first. With test mode on, each press is stored as a new
event.

**Everything else behaves like a real donation.** Alerts fire. Controls like
`[[[c:kofi:latest_donor_name]]]`, `[[[c:kofi:donations_received]]]` and `[[[c:kofi:total_received]]]`
update. The event shows up in your recent events. This is the point: you are looking at the real
thing, not a preview.

**Nothing counts toward your usage.** Events received while test mode is on are not metered.

## What turning it off does

**Every control that service manages goes back to its starting value.** Counts go to zero, the latest
donor, amount and message are cleared, and the running total goes back to your starting total if you
set one, or to zero if you did not. Test donations never reach a real stream, because the moment you
switch test mode off they are gone from your overlays.

> [!WARNING]
> Turn test mode off before you go live. While it is on, a real donation is treated the same as a test
> one: it arrives and shows, but when you later switch test mode off it is reset along with everything
> else.

The stored events stay in your recent events feed. Only the control values are reset.

## The starting total

Each donation integration page has a **starting donation total**, for streamers who raised money on
the service before connecting it to Overlabels. Set it once and your `total_received` control starts
from that number instead of zero.

Test mode respects it. When you turn test mode off, the total goes back to your starting total, not to
zero. The starting total itself is never touched by test mode.

## Which services have it

Ko-fi, Buy Me a Coffee, Fourthwall, StreamLabs and Throne. The switch works the same way on all five
and only ever affects that one service's controls: turning test mode off on Ko-fi leaves your Throne
controls alone.

Overlabels GPS has no test mode. It carries location, not money, and there is nothing to reset.
