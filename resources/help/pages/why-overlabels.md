---
title: Why Overlabels
description: Overlabels is a third-party data normalization engine for Twitch. Donations, subs, bits, followers - it all becomes math you can work with.
heading: Why Overlabels
lead: Overlabels is a third-party data normalization engine for Twitch and the services around it. Donations, subs, bits, followers - it all becomes math you can work with.
canonical: https://overlabels.com/help/why-overlabels
---

If that sentence excited you, you're in the right place. Strap in.

## Why being third-party matters a lot

Overlabels doesn't care where your donation came from. StreamLabs, Ko-fi, Fourthwall,
Twitch Bits - they're all just *money that showed up*. We normalize every payload into a common shape and
expose it as a Control you can reference anywhere in your template.

Overlabels *loves* numbers. Because numbers mean math, and math means you can actually *do* something
with your data instead of just rendering whatever opinionated widget your alert provider decided to ship
this quarter.

## Who was the last person to donate, across *every* service?

One function:

```
latest(
  c.streamlabs.donations_received_at,     c.streamlabs.latest_donor_name,
  c.kofi.donations_received_at,           c.kofi.latest_donor_name,
  c.fourthwall.donations_received_at,     c.fourthwall.latest_donor_name,
  c.bmac.donations_received_at,           c.bmac.latest_donor_name,
  c.throne.donations_received_at,         c.throne.latest_donor_name,
  c.twitch.cheers_received_at,            c.twitch.latest_cheerer_name
)
```

Calmly outputs the name of the last person who gave you money, across five donation services and Twitch
bits. No other overlay or alert service on the market does this, and with Overlabels it's just another
function. (And we're adding more integrations all the time.)

Every control Overlabels tracks gets an automatic `_at` companion - a Unix timestamp of when that value
last changed. `latest()` walks through (timestamp, value) pairs and returns the value whose timestamp is
the largest. Same mechanism gives you `oldest()`, `max()`, and `min()`.

## Add up every donation this stream (and make sense of Bits)

Twitch Bits are 100 Bits = 1 USD, which is a math problem everyone else seems to just... give up on. Drop
an Expression Control into your overlay and unleash:

```
c.kofi.donations_received +
c.streamlabs.donations_received +
c.fourthwall.donations_received +
(c.twitch.cheers_this_stream / 100)
```

That's a workable USD number, composed from five providers and a currency conversion, updating live as
events arrive. You could keep going - weight each source, compute a 1-hour rolling average, trigger a
goal celebration when the combined total crosses a threshold. It's just math.

## Controls: the soul of the machine

There's a SICK implementation of Controls in Overlabels. A Control is a named, typed, live-updating value
you can reference anywhere in your template. Seven types:

- **Text** - strings you can update from the dashboard or chat
- **Number** - integers or floats with min/max constraints
- **Boolean** - toggles for show/hide logic
- **Counter** - increment/decrement with chat commands or API
- **Timer** - countup / countdown / countto, ticks locally on the overlay
- **Random** - picks a new value every N ms within a range
- **Expression** - the big one. Math over every other value in the system.

Service-managed Controls (Ko-fi, StreamLabs, Fourthwall, Overlabels Mobile...)
auto-update from their source and cannot be manually edited - the data is *real*. User Controls are yours
to fiddle with. Either way, every Control can be read by any Expression and targeted by any conditional.

Want to compare your follower count to your subscriber count? Want to fire an alert when the combined
Fourthwall + Ko-fi total crosses €100 in a single stream session? Want to flash a boolean ON when
your bits-per-minute spikes above your stream average? You have the building blocks. Read
[/help/controls](/help/controls) for the full list.

## But wait, what about `[[[if:`?

If? If not? If larger? If smaller? Oh yeah, we gotchu. Conditional engine: built in.

```
[[[if:followers_total >= 1000]]]
  <div class="milestone">1K+ followers!</div>
[[[elseif:followers_total >= 100]]]
  <div>Growing strong with [[[followers_total]]] followers</div>
[[[else]]]
  <div>Help us reach 100 followers!</div>
[[[endif]]]
```

Full comparison operators, boolean logic, nested branches, event-scoped conditions, even conditional
styling inside `<style>` blocks. Read all about it at [/help/conditionals](/help/conditionals).

## But data is ugly!

Yes. It really is. Ever typed `0.1 + 0.2` into any modern programming language and expected `0.3`? Yeah -
you got `0.30000000000000004`. Floats are cursed. When rendering, round at the edge: `round(expr, 2)` or
the `|round:2` pipe. Never compare floats with `==` - use `abs(a - b) < 0.001`.

Overlabels ships with `round`, `number`, `currency`, `duration`, `date`, `distance`, `speed`, and upper /
lowercase formatters. Pipe any value into a formatter and boom - readable.

```
[[[c:event_date|date]]]
```

Renders `Apr 5, 2026, 7:00 PM` when your locale is US. Renders `5 apr 2026, 19:00` when you're Dutch.
Every pipe formatter honors your locale setting. See [/help/formatting](/help/formatting).

## Settings?

Yeah, we got settings. Overlabels is basically a math engine wrapped as an overlay tool, so we need some
flexibility on how you display your data (more custom locale settings coming soon!). For now you can set:

- **Theme** - Light / Dark / System, affects the dashboard only (your overlays stay transparent)
- **Locale** - drives every formatter pipe system-wide
- **Foreach caps** - very important. Controls how many entries Overlabels streams down for `subscribers`,
  `channel_followers`, `followed_channels`, and goals. Higher caps = more rows in your loops = bigger
  payload. Tune to your overlay's needs.

## A foreach loop, you say?

Overlabels lets you iterate over array data: your last followers, subscribers, goals, channels you
follow, and live data from polls, predictions, and hype train contributions. Render a poll as animated
bars. Render the last 10 subs as avatars. Render hype-train contributors as a leaderboard. The loop is
just markup:

```
[[[foreach:event.choices as choice]]]
  <li data-key="[[[choice.id]]]">
    [[[choice.title]]] - [[[choice.votes]]]
  </li>
[[[endforeach]]]
```

Read the full loop docs at [/help/conditionals#foreach-loops](/help/conditionals#foreach-loops).

## You said you did maths?

We do maths. So much maths. All the maths. More maths than you'll ever need, probably. Jokes aside -
Overlabels ships with a substantial math evaluator built on
[EricSmekens/jsep](https://github.com/EricSmekens/jsep), and it can evaluate a frankly absurd array of
expressions. The math engine lives inside Expression Controls and is powerful enough that we can only
explain the basics - the rest is up to you.

Once you understand that every value flowing through Overlabels is just a string, number, or boolean, and
that you can compose them with any combination of arithmetic, comparisons, logical operators, ternaries,
and ~30 built-in functions (`sum`, `avg`, `clamp`, `sin`, `mod`, `lerp`, `floor`, `abs`, and many
more)... you really start to grasp what this thing can do.

Read [/help/math](/help/math) in full if you like maths. If you don't, skip to the next section.

## "I'm a coder but also lazy"

Respectable. Overlabels has Kits - collections of ready-made overlays and alerts you can copy into your
account with one click. Copying a Kit gives you a working starting point you can tear apart, rewire, and
make your own. It's how most people learn what the system can do.

Browse the current Kits (after logging in) at [/kits](/kits). And yes, you can build your own Kit -
create a bunch of overlays and alerts, wrap them together, share them with the community. We'd love that.

## So why are you still using Streamlabs overlays?

Bro, god knows. If you know a line or two of HTML and CSS, start using Overlabels. Copy a Kit, pick it
apart, see how it's wired. It'll click fast.

And if it doesn't? Send me an email on [jasper@emailjasper.com](mailto:jasper@emailjasper.com). I
actually reply!

## The vision

I want to make Overlabels the best third-party data normalization service there is for Twitch and its
ecosystem. No other system has come this far at turning any payload - from any source - into plain math
you can actually work with. I'm proud of that, and I hope by using Overlabels you start to see it too.

Peace. Thank you. Go nerd out.

[/JasperDiscovers](https://twitch.tv/JasperDiscovers)
