---
title: Chat Tower - one tower your whole chat stacks with !stack - Overlabels
section: Bot & chat
description: Chat builds one shared tower with !stack. Every block leans, the tower sways more the taller it gets, and when it falls the viewer who placed the last block gets named. Commands, the fall rule, the tags, the controls and the settings.
heading: Chat Tower
lead: Viewers type !stack and a block with their name lands on top of one shared tower. It leans, it sways, and when it crosses a fall line it comes down and whoever placed the last block gets named.
keywords: tower, stack, jenga, blocks, chat game, minigame, topple, collapse, record, tallest tower, stack left, stack right
---

Install [Chat Tower from Products](/products/chat_tower) and you get the overlay, the integration
and the record list in one click. Or connect it by hand at
[Settings - Integrations - Chat Tower](/settings/integrations/tower) (the Overlabels bot must be
enabled in your channel) and build your own overlay from the tags below.

## What chat can type

```
!stack
!stack left
!stack right
!tower
```

- `!stack` puts a block with the viewer's name and chat colour on top. It lands up to a quarter
  of a block off the one below, left or right. Pure luck.
- `!stack left` and `!stack right` (also `l` and `r`) aim the block. It lands on that side, and
  further out than an unaimed block ever goes. That is how chat fights a lean. It is placement,
  not a shove: nobody can push the tower, you can only choose where your own block goes.
- `!tower` makes the bot say how tall it is, which way it leans, how much room is left and the
  record. For the viewer who just arrived. It works while the stream is offline too.

One block per viewer every 30 seconds by default, and nothing while the stream is offline, the
same confidently-live gate every per-stream counter follows. An offline attempt stores nothing and
the bot says so.

The bot is quiet on purpose. A plain `!stack` gets no reply; it speaks on a topple, on the first
block past a real record, and at every tenth block.

## How it falls

Every block sits a little off the one below it, so the tower drifts. The lean is where the top
block is compared to the base, in block widths (a block is 4 wide). The tower also sways, and the
sway grows with height, so the safe zone shrinks the higher you go. When the top of the sway
crosses a fall line, 4 units from the base centre on either side, it falls.

Both fall lines are drawn on the product overlay, so chat can see it coming and argue about it.
The rule is deterministic from what is on screen; the only randomness is where an individual block
lands. Chat delay is the game: you correct a lean you saw four seconds ago, three other people do
too, and now it leans the other way.

> [!NOTE]
> Only a standing tower counts. The block that brings the tower down sets no record and raises no
> counter. The viewer who placed it gets named on the overlay and in chat, and nothing else
> happens to them.

## The overlay

The tower is an iterable, bottom to top:

```
[[[foreach:tower as block]]]
  <div class="block" style="--p: [[[block.position]]]; --x: [[[block.x]]]; background: [[[block.color ?? #9146ff]]]">
    [[[block.name]]]
  </div>
[[[endforeach]]]
```

Per block: `name`, `login`, `color` (the viewer's Twitch chat colour as `#RRGGBB`, empty if they
never picked one), `position` (1 is the base), `offset` (where it landed relative to the block
below), `x` (its centre relative to the base, the top block's `x` is the lean), `record` (`1` on a
block that took the tower past the all-time record) and `at` (Unix seconds). `tower.count` is the
true height. The iterable carries the top fifty blocks, because a camera follows the top.

For three seconds after a topple the fallen blocks stay in the iterable with `tower.falling` set
to `1`, so a CSS transition on `[data-falling="1"] .block` can tumble them before they vanish.
`tower.toppled_by` and `tower.toppled_height` name the moment until the next block lands.

Live updates arrive one block at a time over the overlay's websocket; a full reload is never
needed.

## The controls

Connecting the integration provisions these under the `tower` prefix:

| Tag | What it holds |
|---|---|
| `[[[c:tower:tower_height]]]` | Blocks standing right now |
| `[[[c:tower:tower_lean]]]` | The top block's position, negative is left |
| `[[[c:tower:tower_room]]]` | How far the top of the sway is from the nearer fall line |
| `[[[c:tower:last_stacker_name]]]` | Who placed the last block |
| `[[[c:tower:blocks_stacked_this_stream]]]` | Blocks placed this stream, the toppling one included |
| `[[[c:tower:tallest_tower_this_stream]]]` | Tallest standing tower this stream |
| `[[[c:tower:topples_this_stream]]]` | How many times it fell this stream |
| `[[[c:tower:last_topple_by]]]` | Who brought it down last |
| `[[[c:tower:last_topple_height]]]` | How tall it was when it fell |
| `[[[c:tower:tallest_tower_record]]]` | The all-time record |
| `[[[c:tower:blocks_stacked_total]]]` | Blocks placed, all time |

The three `_this_stream` counters reset when you go live. The record and the `last_*` values
persist. Every control has a `_at` twin holding when it last changed, which is what the product
overlay keys its topple banner and record flash on: `c.tower.last_topple_height_at` moves on
every topple, and `c.tower.tallest_tower_record_at` only when the record actually moves.

## The record list

Everyone who had a block in the tallest tower ever, bottom to top, one entry per viewer, lives in
a List with the slug `tower_record`. The product creates it; a hand-built setup can create it on
the [Lists page](/dashboard/lists) with that exact slug and the roster starts being written on the
next record. It is a normal List, so it shows on your Lists page and any overlay can loop it:

```
[[[foreach:c:list:tower_record as name]]][[[name]]] [[[endforeach]]]
```

## Alerts

Two alert triggers come with the integration: **Chat Tower Block Stacked** and **Chat Tower
Toppled**. Both carry `event.user_name`, `event.height`, `event.lean`, `event.room`, `event.aim`,
`event.color` and `event.record`; see [[all-chat-tower-events]] for every tag.

## Settings

[Settings - Integrations - Chat Tower](/settings/integrations/tower) has three things:

- **Tower lifetime.** Per stream knocks the tower down when you go live, so every stream starts
  from the ground. Persistent leaves it standing between streams. The record carries over either
  way.
- **Per-viewer cooldown.** How long a viewer waits before `!stack` works for them again. One
  person cannot build or topple it alone.
- **Clear the tower.** Knocks it down by hand without naming anyone. The record and the counters
  stay.

Disconnecting removes the controls and switches `!stack` off; the standing blocks are kept and
come back if you reconnect.
