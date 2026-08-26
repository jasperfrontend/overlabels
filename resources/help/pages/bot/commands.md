---
title: Bot Commands - Overlabels Help
description: Every chat command the @overlabels Twitch bot understands - controls, !ol chat-admin meta-command, list operations, and built-ins.
heading: Bot Commands
lead: Every chat command the @overlabels Twitch bot understands - controls, !ol chat-admin meta-command, list operations, and built-ins.
canonical: https://overlabels.com/help/bot/commands
context: settings.bot.commands.*
section: Bot
---

Warning: This page is... Slightly overwhelming. I'm fully aware of that and I hope to make this page less
of a knowledge bomb in the future. For now: if you learn how `!ol` works, you're well on your way to
become an Overlabels Bot Commands expert.

> [!WARNING]
> **Control commands are OFF by default.** Everything in the *Controls* section below is gated behind
> a per-channel switch. The default is **off**: until you flip it on, the bot will ignore `!control`,
> `!set`, `!increment`, `!decrement`, `!reset`, `!enable`, `!disable`, and `!toggle` entirely. **The !ol
> chat-admin commands and !list meta-command work regardless** - they don't touch your controls
> layer.
>
> To open the controls surface for your channel, type `!enablecontrols` in your own chat (broadcaster
> only). To close it again, `!disablecontrols`.

## Controls

Read and write your overlay controls from chat. The bot only ever touches controls you created yourself -
service-managed controls (Ko-fi, StreamLabs, Fourthwall counters) are intentionally invisible to
chat. Requires `!enablecontrols` on your channel.

| Command | Tier | What it does |
|---|---|---|
| `!control <key>` | Everyone | Read the current value of one of your controls. |
| `!set <key> <value>` | Mod+ | Set a control to an exact value. |
| `!increment <key> [amount]` | Mod+ | Add to a number/counter control. Amount defaults to 1. |
| `!decrement <key> [amount]` | Mod+ | Subtract from a number/counter control. Amount defaults to 1. |
| `!reset <key>` | Broadcaster | Reset a number or counter control back to 0. |
| `!enable <key>` | Mod+ | Enable a boolean control. |
| `!disable <key>` | Mod+ | Disable a boolean control. |
| `!toggle <key>` | Mod+ | Flip a boolean control to the opposite state. |

```
chat: !control level
@overlabels: @viewer level: 8

chat: !set level 8
@overlabels: @mod set level to 8

chat: !inc wins
@overlabels: @mod wins is now 4

chat: !dec lives 1
@overlabels: @mod lives is now 2

chat: !reset wins
@overlabels: @broadcaster reset wins to 0

chat: !toggle mute
@overlabels: @mod mute is now enabled
```

`!increment` has the shorthand `!inc`, and `!decrement` has `!dec`. Negative amounts work - `!inc wins -2`
subtracts 2.

## Controls-access switch

Broadcaster-only toggles that open or close the entire *Controls* section above. Useful when you want
chat to drive your overlay state for one stream and not the next.

| Command | Tier | What it does |
|---|---|---|
| `!enablecontrols` | Broadcaster | Open the controls surface on this channel. Default is closed. |
| `!disablecontrols` | Broadcaster | Close the controls surface again. Chat can no longer touch your controls. |

## `!ol` chat-admin

Manage your custom commands and aliases without leaving Twitch. `!ol` is namespaced this way so it
doesn't fight with other bots (StreamElements, Wizebot, Nightbot, Streamlabs Cloudbot all already own
`!command` / `!cmd` / `!commands`).

All `!ol` subverbs are moderator+. The replies are queued through the bot's outbox so they thread
normally in chat. Validation runs server-side - the same rules that gate the dashboard form catch
chat-side typos too (reserved names, self-looping aliases, bad placeholder syntax, etc).

| Command | Tier | What it does |
|---|---|---|
| `!ol cmd add <name> <payload>` | Mod+ | Create a Bot Command - a custom !command that speaks a templated reply. |
| `!ol cmd edit <name> <payload>` | Mod+ | Replace the reply template on an existing command. |
| `!ol cmd delete <name>` | Mod+ | Remove a Bot Command from your channel. |
| `!ol cmd options <name> <option> <value>` | Mod+ | Tune one option on a command. Options: cooldown, permission, enabled, destroy, hidden. |
| `!ol alias add <name> <target>` | Mod+ | Create a Bot Alias - a short command that rewrites to a longer one. |
| `!ol alias edit <name> <target>` | Mod+ | Change what an existing alias rewrites to. |
| `!ol alias delete <name>` | Mod+ | Remove an alias. |
| `!ol alias options <name> <option> <value>` | Mod+ | Same options as `!ol cmd options`, applied to an alias. |
| `!ol list [cmd\|alias]` | Mod+ | Print every command and alias you have. Optional filter. |
| `!ol help [cmd\|alias\|options]` | Mod+ | Print a usage line for `!ol` or one of its subverbs. |

```
chat: !ol cmd add lol HAHA [[[bot:from_user]]]
@overlabels: @mod added !lol

chat: !ol cmd options lol cooldown 30
@overlabels: @mod !lol cooldown is now 30s

chat: !ol alias add w !inc wins {1}
@overlabels: @mod added alias !w -> !inc wins {1}

chat: !ol list
@overlabels: @mod commands: !lol !discord | aliases: !w
```

Notes worth knowing:

- A `!ol cmd add` payload can include any template tag: `[[[c:foo]]]` for controls,
  `[[[bot:from_user]]]` for the chatter, `[[[follower_count]]]` for Helix data.
- Alias targets use `{1}`, `{2}`, ... for positional args from the call site, and `{*}` to capture all
  remaining args. Aliases can target builtins or your commands, but not other aliases (one hop only).
- The target command's own permission still applies after an alias rewrite, so
  `!ol alias options` only restricts who can trigger the alias itself.
- `!ol list` output is clipped to ~480 characters so it stays inside Twitch chat's message limit.

### Options vocabulary

`!ol cmd options <name> <option> <value>` and the alias equivalent both accept these option keys:

- `cooldown` - integer seconds, 0 to 86400. Broadcaster bypasses cooldown.
- `permission` - `everyone` / `sub` / `vip` / `mod` / `broadcaster`. `all` is a synonym for everyone,
  `bc` for broadcaster.
- `enabled` - `true` or `false` (also accepts on/off, yes/no, 1/0).
- `destroy` - integer hours, 0 to 8760 (1 year).
- `hidden` - hides this command from the future `!commands` listing without disabling it.

## `!list` meta-command

One mod-only command exposes the full action vocabulary of your Lists to chat. The shape is
`!list <slug> <action> [args]` and the verbs cover read (`count`, `first`, `random`...), grow (`add`),
shrink (`draw`, `pop`, `clear`), snapshot/restore, and lifecycle (`disable`, `enable`).

| Command | Tier | What it does |
|---|---|---|
| `!list <slug> <action> [args]` | Mod+ | Operate on one of your Lists. ~20 actions cover read, append, draw, snapshot, clear, etc. |

```
chat: !list raffle count
@overlabels: @mod 'raffle' has 17 entries
```

The verb after the slug is the action. Full action reference:
[/help/lists - the action vocabulary in detail](/help/lists#actions).

## Your own commands

Everything above is built into the bot. On top of that you can author four kinds of custom command -
each is managed from the dashboard, and three of them are also reachable through `!ol` in chat.

- **[Bot Aliases](/help/bot/aliases)** - short commands that rewrite to longer ones before dispatch.
  `!w 2` becomes `!inc wins 2`. Positional placeholders: `{1}`, `{2}`, `{*}`.
- **[List Appenders](/help/lists#appenders)** - chat commands that append a chatter's input to one of
  your Lists. Raffle entries, quote walls, song requests - one verb per kind of growing list.

A custom command can't claim the name of a built-in - `!control`, `!ol`, `!list`, `!ping`, etc. are
reserved. Validation catches the collision at save time on both the dashboard and `!ol cmd add`.

### Random rolls and counters

Two tags you can write straight into a command: `[[[rand:0-69]]]` rolls a random number, and
`[[[counter:wins]]]` adds one to a running total and shows it.

```
!ol cmd add steven your Steven Level is [[[rand:0-69]]]%! Kappa.
!ol cmd add wins So far, Jasper has won [[[counter:wins]]] times
```

The counter is created for you on save, and it's an ordinary Control - so `!set`, `!reset` and
`[[[c:wins]]]` in an overlay all work on it, and the on-screen number moves the moment chat does.

**[Random Rolls and Counters](/help/bot/random-and-counters)** covers both tags properly. `!ol help tags`
prints a short reminder in chat.

### Conditions

`[[[if:...]]]`, `[[[elseif:...]]]`, `[[[else]]]` and `[[[endif]]]` work in a reply the same way they do
in an overlay, so a command can change what it says based on a Control, the chatter's arguments, or any
other tag:

```
!ol cmd add wins Jasper has won [[[counter:wins]]] time[[[if:c:wins != 1]]]s[[[endif]]]
```

The [Conditionals](/help/conditionals) page lists every comparison. `[[[foreach]]]` is the one block
that doesn't work in chat - a reply is one line, so there's nothing to repeat into - and saving a
command that uses it is refused with a reason.

## Miscellaneous

| Command | Tier | What it does |
|---|---|---|
| `!followage [@user]` | Everyone | How long the chatter (or a named user) has followed this channel. |
| `!accountage [@user]` | Everyone | How long ago a Twitch account was created. |
| `!ping` | Everyone | Liveness check. The bot says pong. |

```
chat: !followage
@overlabels: @viewer you have been following for 4 years, 4 months, 11 days, 11 hours, 55 minutes

chat: !accountage
@overlabels: @viewer your account was created 11 years, 7 months, 1 day, 12 minutes, 26 seconds ago

chat: !ping
@overlabels: @viewer pong
```

Target a different user with `!followage @someone`. It returns "you don't follow this channel yet" if
there's no follow relationship. The broadcaster querying their own follow date gets a friendly bounce -
Twitch auto-follows broadcasters to themselves on signup. Account creation dates are public Twitch data,
so `!accountage` needs no broadcaster permission.

Chat Castle (the chat-driven map game) ships several chat verbs of its own - `!join`, `!p`, `!h`, `!a`,
`!s`, `!castlehelp`. Those are documented separately at [/help/gamejam](/help/gamejam).

## Permission tiers

Permission tiers stack from least to most privileged: a moderator can invoke anything tagged Moderator+,
VIP+, Sub+, or Everyone. Broadcaster can invoke anything.

`Everyone` → `Sub+` → `VIP+` → `Mod+` → `Broadcaster`

Founder counts as Sub+ on the @overlabels tier ladder. The bot doesn't model founder as a separate tier.
