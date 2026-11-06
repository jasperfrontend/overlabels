---
title: Under the hood of your products - Overlabels Help
description: Every Overlabels product is an ordinary overlay in your own account. Climb from Chat Emote Bubbles to the Math Engine Showcase one idea at a time, and see how Overlabels really works.
heading: Under the hood
lead: Every product you installed is an ordinary overlay in your own account, built from the same parts you can use yourself. This page climbs from the simplest product to the Math Engine, one new idea per step. You can stop on any step.
canonical: https://overlabels.com/help/under-the-hood
section: Getting started
keywords: under the hood, how products work, next steps, learn more, beat overlabels, advanced, css variables, animation, frame rate, fps
---

You installed a product, it worked, and you want to know how. Good news: there is no hidden
machinery. Each product is an overlay sitting in **My overlays** on your dashboard. It is some HTML,
some CSS, and `[[[tags]]]` that Overlabels fills with live data. That is the whole trick, and it is
the same trick at every step below.

Nothing here asks you to understand all of it at once. Each step adds exactly one idea to the one
before it, and every step is a fine place to stop.

> [!TIP]
> Open the product alongside this page. Every product overlay is in **My overlays**: open one and
> press **Edit** to see the same source this page quotes. Look, but do not save changes to it yet.
> The last section shows how to experiment safely.

## Step 1: Chat Emote Bubbles

The whole markup of Chat Emote Bubbles is eight lines:

```html
<div class="ol-bubbles look-[[[c:look]]] dir-[[[c:direction]]] spawn-[[[c:spawn]]][[[if:c:pop_after > 0]]] popping[[[endif]]]">
  [[[foreach:emotes as e]]]
  <div class="bubble" data-key="[[[e.id]]]" style="--rx: [[[e.x]]]; --ry: [[[e.y]]]; --seed: [[[e.seed]]]; --n: [[[e.n]]];">
    <div class="sway"><div class="skin">[[[e.html]]]</div></div>
  </div>
  [[[endforeach]]]
</div>
```

Three ideas live in there:

- **A tag puts data on the page.** Anything in triple brackets is swapped for a live value before
  the page is drawn. `[[[e.html]]]` becomes the emote picture.
- **A control can become a CSS class.** `[[[c:look]]]` is a control holding a word like `bubble`,
  `snow` or `heart`, so the outer element ends up with a class like `look-bubble`, and the
  stylesheet draws each look differently. When you pick a look in the product's designer, all it
  does is write that control.
- **`foreach` repeats.** `[[[foreach:emotes as e]]]` makes one bubble for every emote your chat
  types, read straight from Twitch chat.

The motion is plain CSS. Each emote arrives with a few numbers between 0 and 99 (`e.x`, `e.y`,
`e.seed`) that never change for that emote, and the stylesheet uses them as CSS variables:

```css
.sway {
  animation: ol-sway calc(2.2s + var(--seed) * 0.016s) ease-in-out calc(var(--seed) * -0.04s) infinite alternate;
}
```

A bubble with seed 0 sways every 2.2 seconds, one with seed 99 every 3.8 seconds. Nothing is
random, and still no two bubbles move alike.

Read more: [Controls](/help/controls), [Chat Emote Bubbles](/help/chat-emote-bubbles).

## Step 2: Chat Checkin

Checkin adds words that change with the numbers. This is the line under the globe:

```html
[[[c:checkin:checkins_total]]] checkin[[[if:c:checkin:checkins_total != 1]]]s[[[endif]]]
from [[[c:checkin:unique_countries_this_stream]]] countr[[[if:c:checkin:unique_countries_this_stream != 1]]]ies[[[else]]]y[[[endif]]]
```

- **`if` shows text only when something is true.** The same line reads "1 checkin from 1 country"
  and "12 checkins from 4 countries", with `else` picking between "ies" and "y".
- **A pipe formats a value.** `[[[c:checkin:farthest_checkin_this_stream|distance:km]]]` and
  `|distance:mi` print the same distance in kilometres and in miles.
- **`??` is a fallback.** `[[[c:checkin:farthest_checkin_name_this_stream ?? someone]]]` prints
  "someone" until there is a name to print.

The `c:checkin:` part of those names matters too. These controls belong to the Chat Checkin
integration: the bot fills them in as your chat types `!checkin`, and **any** overlay of yours can
read them, not just the globe. The globe itself is a single tag, `[[[checkin_globe]]]`, which
Overlabels draws for you.

Read more: [Conditionals](/help/conditionals), [Formatting pipes](/help/formatting),
[Chat Checkin](/help/checkin).

## Step 3: Follower Bowling

Bowling is where an overlay starts keeping time.

- **A List is a queue.** `!bowl` adds the chatter's name to a List called `lane`. The overlay shows
  `[[[c:list:lane:count]]]` and loops over the names. Inside a loop, `loop.index` counts from 0, so
  `[[[if:loop.index <= 9]]]` keeps exactly ten followers as pins.
- **One timestamp is the trigger.** When a name leaves the List, the List remembers when, in
  `[[[c:list:lane:last_removed_at]]]`. That single moment is the only event the overlay ever
  receives.
- **Expression Controls turn it into motion.** An Expression Control is a control whose value is a
  formula. This one works out how many seconds ago the ball was thrown:

  ```
  (now_ms() - c.bowl_t * 1000) / 1000
  ```

  Because it reads the clock, it is recalculated on every frame, and tags work inside the CSS as
  well, so the stylesheet picks it up as a CSS variable:

  ```css
  :root {
    --age: [[[c:bowl_age|round:2]]];
    --bx: [[[c:ball_x|round:3]]];
  }
  ```

The ball's position, each pin falling over and the score that appears are all worked out from that
one number. The same timestamp even decides how many pins go down, so every throw is different
with no dice anywhere.

Read more: [Lists](/help/lists), [Expression Controls](/help/expressions), and the full teardown in
[Inside the Follower bowling lane](/help/deep-dives/follower-bowling-lane).

## Step 4: the Math Engine

Step 3 is the whole engine in miniature: formulas that read the clock, recalculated every frame,
handed to CSS as variables. The [Math Engine
Showcase](https://overlabels.com/overlay/elba-elbe-bergen-rockies/public) takes that idea as far as
it goes. It is a BRB scene with six moving panels, and 24 of its 28 controls do nothing but
arithmetic on the clock. Its planet and moon are five lines:

```
orbit_t  = 2 * PI * now_ms() / 12000
planet_x = 105 * cos(c.orbit_t)
planet_y = 105 * sin(c.orbit_t)
moon_x   = c.planet_x + 26 * cos(8 * c.orbit_t)
moon_y   = c.planet_y + 26 * sin(8 * c.orbit_t)
```

One angle goes once around every twelve seconds. The planet rides a circle, and the moon rides a
smaller circle on top of the planet, eight times faster. Feed `planet_x` and `planet_y` into CSS
and they orbit.

Almost every motion you will want comes from five moves, and the showcase uses all of them:
`mod` turns a number into a wheel, `fract` into a ramp, `floor` holds a value still, `sin` of a
`floor` rolls dice, and a CSS `transition` aimed at a moving target trails behind it for free.

Read more: [Math Engine](/help/math), and the panel-by-panel teardown in [Inside the Math Engine
Showcase](/help/deep-dives/math-engine-showcase).

### How fast is "every frame"?

As fast as the screen showing it. A browser draws a new frame each time the display refreshes: 60
times a second on a 60 Hz monitor, 144 times on a 144 Hz one. Inside OBS, a browser source draws at
OBS's own frame rate, unless you give the source a custom one. A machine that is struggling draws
fewer frames.

That costs smoothness, never correctness. Every formula is worked out from the clock, not from the
frame before it, so a skipped frame jumps straight to where things should be instead of falling
behind.

> [!NOTE]
> The preview inside the Expression Control editor does not tick. It recalculates when you change
> the formula, so anything built on the clock looks frozen there. Put it in an overlay to watch it
> move.

## Make your own

Do not experiment on the product's own overlay. The product's designer and its setup page look
after that one, and they expect it the way it was installed. Make a copy instead:

1. Open the product's overlay from **My overlays**.
2. Open the **⋮** menu next to **Edit** and choose **Copy**.
3. When it offers to **Import Controls from Source**, keep them selected and choose **Import
   selected**, so the copy gets its own set of the overlay's controls.
4. Change one thing, save, and watch it. In Chat Emote Bubbles, try making `2.2s` in the `.sway`
   rule `0.5s` and see the bubbles get nervous.

The copy reads the same live data as the original: the same chat, the same checkins, the same List.
Give it its own place in OBS with its own overlay link, and the product keeps working untouched next
to it.

From here, the [editor guide](/help/editor) covers writing overlays from scratch, and
[testing](/help/testing) shows how to fire fake events so you are not waiting for a real follow.
