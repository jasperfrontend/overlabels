---
title: Chat Emote Bubbles - every emote your chat types floats across your stream - Overlabels
section: Bot & chat
description: Every emote your Twitch chat types floats across your stream in a soap bubble, a snowflake or a heart. One click to install, nothing to connect, no bot. The ten controls, the emotes loop it is built on, and how to build your own.
heading: Chat Emote Bubbles
lead: Every emote your chat types floats across your stream in a bubble, one bubble per emote. Type PogChamp five times and five PogChamps drift up and away. Nothing to connect, no bot to add.
keywords: emote bubbles, bubble trouble, floating emotes, emote rain, emote widget, soap bubbles, snowflakes, hearts, pogchamp
---

Install [Chat Emote Bubbles from Products](/products/chat-emote-bubbles) and you get the overlay in one
click. Put it in OBS as a browser source the size of your canvas, above your camera, and every emote
your chat types is a bubble on your stream. Twitch emotes and the 7TV, BTTV and FFZ emotes enabled in
your channel all count.

Everything the product does is ten controls on the overlay's Controls tab, and every change lands in
OBS the moment it is made.

## The controls

- **Look** - `bubble` is a soap bubble, `snow` puts a snowflake behind the emote, `heart`
  is a heart-shaped bubble. Winter and Valentine's Day are the point of the second and third.
- **Bubble size** - width and height in pixels, 32 to 240. The emote inside scales with it.
- **Bubble color** - the ring and shine, the snowflake, or the heart. White reads as soap; try a pale
  pink on the heart and a pale blue on the snowflake.
- **Float speed** - 1 to 10. At 1 a bubble takes 24 seconds to cross the screen, at 10 under three.
- **Direction** - `up` floats bubbles toward the top of the screen, `down` sinks them toward the
  bottom. Where they appear follows.
- **Bubbles on screen at most** - the newest ones show, older ones are dropped. Twitch allows 500
  characters in a message, which is about 55 of the shortest emote, so a real emote bomb fits.
- **Pop after** - `0` lets bubbles float off the edge of the screen. Any other number makes them
  gather at that edge and pop after that many seconds.
- **Spawn** - where a bubble is born. `outside` floats in from just past the edge it starts from,
  `edge` appears on that edge, `random` appears anywhere on screen and drifts from there, `cannon`
  appears at one point you pick.
- **Spawn X** and **Spawn Y** - the cannon's point, in percent of the screen. 0, 0 is the top left,
  80, 80 is a spot near the bottom right where a camera usually sits.

Bubbles from one message are staggered a fraction of a second apart, so a bomb streams out instead of
appearing as a wall.

## What counts as an emote

Pictures. A Twitch emote, or a 7TV, BTTV or FFZ emote that is enabled in your channel. A Unicode emoji
like a party popper is text, not an emote, and stays in chat.

The [chat display filters](/help/chat#display-filters) apply here too: a chatter you have hidden
never puts a bubble on screen, and the `!` command filter keeps a `!so PogChamp` out of it. A message
a moderator deletes takes its bubbles with it.

## Where the emotes come from

The overlay reads your chat from Twitch directly, over the same public connection any chat viewer
uses. Nothing passes through Overlabels, so the bubbles keep coming even when Overlabels is having a
bad day. It is the same connection [Twitch Chat in an Overlay](/help/chat) explains, read through a
second loop.

## Build your own

The product is one static overlay you can open and edit, and the loop it uses is available to any
overlay: `[[[foreach:emotes as e]]]` lists every emote typed in chat lately, one item per
occurrence, oldest first. Each item carries the emote's picture, its name, who typed it, and three
stable random numbers to place and time it with. The full field list is in the
[emotes loop reference](/help/reference/foreach-loops/emotes).

The smallest possible version:

```html
[[[foreach:emotes as e]]]
  <span class="emote" data-key="[[[e.id]]]" style="left: [[[e.x]]]%">[[[e.html]]]</span>
[[[endforeach]]]
```

```css
.emote { position: absolute; bottom: -80px; animation: rise 8s linear forwards; }
.emote img { height: 56px; }
@keyframes rise { to { transform: translateY(-120vh); } }
```

Two things make it work. The `data-key` keeps each element across updates, so a bubble mid-flight is
not restarted when the next message arrives. And `e.x` is the same number every time the overlay
re-renders, so the bubble never jumps sideways. `e.n` is which of its message's emotes an item is,
for staggering, and `e.seed` is a third number for anything else that should vary per bubble.
