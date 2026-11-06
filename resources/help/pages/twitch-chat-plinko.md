---
title: Twitch Chat Plinko - your chat types !drop and their name bounces down a Plinko board - Overlabels
section: Bot & chat
description: A Plinko board your Twitch chat plays on stream. Every !drop drops a ball with that chatter's name through real pegs, in chat order. One click to install, no bot. How the line works, the scores, and how to restyle it.
heading: Twitch Chat Plinko
lead: Your chat types !drop and a ball with their name on it bounces down a Plinko board on your stream. The middle pays 5, the rare edges pay 1000. Nothing to connect, no bot to add.
keywords: plinko, drop game, chat game, pachinko, galton board, brb game, downtime game, drop command, chat plinko
---

Install [Twitch Chat Plinko from Products](/products/twitch-chat-plinko) and you get the overlay in one
click. Put it in OBS as a browser source the size of your canvas, 1920 by 1080. It is a full scene, so it
works best on a BRB or downtime scene. From then on, every `!drop` in your chat drops a ball.

## What viewers type

A chatter types `!drop`, or the word you chose instead (see below). Anything after the word still counts, so `!drop come on edges` drops a ball too,
but `!dropped` does not. A ball with their name on it, in their Twitch name colour, falls from the top,
bounces off every peg it touches and lands in a slot. The slot's points go on the chatter's total.

The middle slots pay 5 and the edges pay 1000. Most balls land near the middle, the way a real board
behaves, so a 1000 is rare and worth celebrating.

## Use a different word than !drop

If your channel already uses `!drop`, for example for another chat game, pick a different word on
the [Plinko settings page](/settings/integrations/plinko), under Settings, Integrations. It is there
once Plinko is installed. Type the word with or without the `!`. Reload
the Plinko browser source in OBS afterwards and the board listens for the new word, and the line
above the board says it too.

## Played in order, one ball each

Every `!drop` takes its turn in the order your chat sent it. Each chatter has one ball at a time: a
second `!drop` while theirs is still queued or falling is ignored, so nobody can flood the board.

Up to 150 balls can be on the board at once, and ten new ones join every second. When more people drop
than that, everyone else waits in line. The board shows how many are in play and how many are waiting,
and the next five names in line. A raid of thousands all typing `!drop` at once becomes a line that
drains steadily, in order, instead of a pile.

## The scoreboard

Beside the board are the six latest drops with their points, and the five best totals. The totals
count from when the overlay loaded: reloading the browser source, or restarting OBS, starts a new game.

## Your mods stay in charge

When a mod deletes a `!drop` message, that ball leaves the board, or never falls if it was still in
line. When a mod times out or bans a chatter, their ball, their place in line and their score all go.
Chatters you have hidden on the [chat settings page](/settings/chat) never get a ball.

The setting there that hides messages starting with `!` is about a chat feed. It does not stop Plinko,
which listens for exactly those messages.

## Where the drops come from

The overlay reads your Twitch chat directly, over the same public connection any chat viewer uses, the
way [Twitch Chat Overlay](/help/chat) does. Nothing passes through Overlabels, so a busy chat costs
nothing extra, and the Overlabels bot never has to be in your channel.

## Restyle it

The board is drawn by one tag, `[[[plinko_board]]]`, and how it looks is CSS custom properties on
`.ol-plinko` in the overlay's CSS. Open the overlay, press Edit and change them:

| Property | What it sets | Default |
|---|---|---|
| `--plinko-rows` | rows of pegs, 4 to 24 | `14` |
| `--plinko-max-balls` | balls on the board at once | `150` |
| `--plinko-rate` | new balls per second while chat waits | `10` |
| `--plinko-peg-color` | the pegs | `#a78bfa` |
| `--plinko-accent` | pegs as they are hit, and a landing | `#f472b6` |
| `--plinko-ball-color` | balls of chatters with no name colour | `#f472b6` |
| `--plinko-label-bg` / `--plinko-label-color` | the name tags | `#120720` / `#fafafa` |
| `--plinko-slot-color` | slot numbers and dividers | `#a78bfa` |
| `--plinko-font` | name tags and slot numbers | the page's font |

The scoreboard is ordinary HTML with these classes, placed by the overlay's own CSS:
`.ol-plinko-count` (in play and waiting), `.ol-plinko-next` (next in line), `.ol-plinko-recent` (latest
drops) and `.ol-plinko-top` (best totals). Each row holds a `.ol-plinko-name` and, in the last two, a
`.ol-plinko-points`.

You can put `[[[plinko_board]]]` in any overlay of your own, too. Give the element a size in your CSS
and the board fills it. `[[[plinko_command]]]` prints your command word, without the `!`.
