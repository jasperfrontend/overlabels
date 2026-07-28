---
title: Bot Aliases
description: Short chat commands that rewrite to longer ones. Positional placeholders, one-hop guard, shared validation.
heading: Bot Aliases
lead: Short chat commands that rewrite to longer ones. Positional placeholders, one-hop guard, shared validation.
canonical: https://overlabels.com/help/bot/aliases
context: settings.bot.aliases.*
section: Bot
---

## What is an alias?

An alias is a short chat command that **rewrites to a longer one** before the bot dispatches it. You type
`!w 2` in chat; the bot sees that `!w` is an alias whose target is `!inc wins {1}`; the `{1}` gets
replaced with `2`; the bot now routes the rewritten command `!inc wins 2` through its normal dispatch as
if you'd typed it directly. The original chatter context (badges, reply threading) carries through both
hops.

Aliases are **per-user**. They live on your account; another streamer creating `!w` on their channel
doesn't affect yours. Aliases can target Overlabels built-ins (`!inc`, `!set`, `!reset`...) or your own
[Bot Expressions](/help/expressions).

What aliases **can't** do: target another alias (one hop only), point to themselves, or collide with a
name already taken by a built-in or one of your expressions. The dashboard and the chat admin command
(`!ol alias add`) both validate against the same rules, so chat-side mistakes get caught with the same
error message.

## Creating aliases - dashboard or chat

Two surfaces, identical validation. Pick whichever fits the moment.

### From the dashboard

Settings > Integrations > [Manage aliases](/settings/bot/aliases). The editor has quick-insert chips for
the placeholders and a "Target a command" expander listing all built-ins and your expressions. It also
renders a live example showing how a sample call site resolves. Best path when you're building a
complicated target with multiple placeholders and want to see the rewrite preview before saving.

### From chat

Mod-or-broadcaster can run `!ol alias add <name> <target>` in chat. Replies thread normally through the
bot's outbox.

```
@mod: !ol alias add w !inc wins {1}
@overlabels: added alias !w -> !inc wins {1}
```

Full `!ol alias` reference (add / edit / delete / options) lives on
[/help/bot/expressions](/help/bot/expressions#ol).

## Placeholder syntax

The target template can contain placeholders that get replaced with the chatter's args at fire time.
Three forms:

**`{1}`, `{2}`, `{3}`, ...** - positional placeholders, 1-indexed. `{1}` is the first
whitespace-separated arg the chatter typed after the alias name, `{2}` the second, and so on. Missing
args substitute to empty string (no error, no warning).

**`{*}`** - captures every arg past the highest-numbered positional placeholder, space-joined. With no
positional placeholders, `{*}` is "every arg." With `{1} {*}`, `{*}` is "everything from arg 2 onward."

Anything else inside braces (`{x}`, `{foo}`, `{}`) is rejected at save time with a clear error pointing
at the offending placeholder. The valid set is small on purpose - aliases are not a templating language.

## Permission and the one-hop rule

Aliases ship with **moderator** as the default permission, but the dropdown lets you set any tier from
everyone to broadcaster. The chosen permission gates who can *fire* the alias.

After the rewrite, the target command's own permission still applies. This is defence-in-depth: even if
you accidentally open an alias to everyone, the target command's gate still runs against the original
chatter's badges.

```
# An alias to !reset (broadcaster-only), opened to everyone
!ol alias add hardreset reset {1}
!ol alias options hardreset permission everyone

# A viewer fires it
@viewer: !hardreset wins

# Alias gate passes. Rewrite to !reset wins.
# !reset is broadcaster-only -> second-hop gate denies.
# Silent drop. Nothing happens.
```

### One hop only

The rewritten command runs through normal dispatch once - it cannot land on another alias. The backend
rejects alias->alias chains at save time with a clear error (*"!w is itself an alias. Point this alias at
the underlying command instead."*), and the bot defensively drops any chain that would result from stale
map data. This keeps the model simple to reason about and immune to loops.

## Options - cooldown, permission, enabled, hidden

Each alias has four toggles that match the Bot Expression vocabulary one-for-one. Editable from the
dashboard or via `!ol alias options <name> <option> <value>` in chat.

| Option | Value |
|---|---|
| `cooldown` | Integer seconds, 0 to 86400. Broadcaster bypasses the cooldown. |
| `permission` | `everyone` / `subscriber` / `vip` / `moderator` / `broadcaster`. Shortforms `sub`, `mod`, `bc`, `all` work too. |
| `enabled` | `true` / `false`. Also accepts on/off, yes/no, 1/0. Disabled aliases stay in your library but don't fire. |
| `hidden` | Hides the alias from the future `!commands` listing without disabling it. |

## Worked examples

### A counter shortcut

Bind `!w` to incrementing your `wins` counter.

```
# Create
!ol alias add w !inc wins {1}

# Use - positive
@mod: !w
@mod: !w 2

# Use - negative. !inc wins -2 subtracts because !inc
# accepts a signed amount. Aliases pass the arg through verbatim.
@mod: !w -2
```

### Capturing the whole rest of the message

`{*}` is for cases where you don't know how many args the chatter will type. Good for wrapping commands
that accept a free-form string.

```
# Create
!ol alias add shout !set announcement {*}

# Use
@mod: !shout big raid incoming, thanks SomeStreamer!
# Rewrites to !set announcement big raid incoming, thanks SomeStreamer!
```

### Two-positional with a fixed middle

Positionals can appear anywhere in the target template, with literal text in between.

```
# Create
!ol alias add gift !give {1} from {2}

# Use
@mod: !gift @alice @bob
# Rewrites to !give @alice from @bob
```

### Aliasing a Bot Expression

Aliases can target your own [Bot Expressions](/help/expressions), not just built-ins. Useful when you
want a short trigger for a long templated reply.

```
# Suppose !discord is one of your Bot Expressions.
# Make !d an alias for it.
!ol alias add d !discord

# Use
@viewer: !d
# Rewrites to !discord, which the bot resolves as an expression
# and speaks the template result.
```

## Things to know

- **One hop only.** Aliases can't target other aliases. Self-loops are also rejected. Validation catches
  both at save time with explicit errors.
- **Target permission still applies.** After the rewrite, the target command's own permission gate runs
  against the original chatter. An alias can't escalate privilege.
- **Cooldown is per-alias.** The alias's `cooldown_seconds` gates how often the alias itself fires. If
  the target also has a cooldown (e.g. a Bot Expression), that runs independently on the second hop.
- **Missing args are silent.** `{1}` with no arg substitutes empty string. The rewritten command keeps
  running - it just sees a shorter arg list. No error to chat.
- **Negative numbers work.** Args pass through verbatim. `!w -2` with target `!inc wins {1}` expands to
  `!inc wins -2`, which subtracts because `!inc` accepts signed amounts.
- **Hide from listings if it's internal.** The `hidden` option keeps an alias out of the future
  `!commands` listing without disabling it. Useful for mod-only helpers you don't want chat asking about.

## Quick reference

```
Chat commands
  !ol alias add <name> <target>
  !ol alias edit <name> <target>
  !ol alias delete <name>
  !ol alias options <name> <option> <value>
  !ol list alias

Placeholders
  {1}, {2}, {3}, ...   positional, 1-indexed
  {*}                  every arg past the highest positional

Options
  cooldown    0-86400 (seconds)
  permission  everyone | sub | vip | mod | broadcaster
  enabled     true | false
  hidden      true | false
```

Dashboard: [/settings/bot/aliases](/settings/bot/aliases)

## Related

- [Bot Expressions](/help/expressions) - custom `!command` chat replies templated against your controls,
  Twitch data, and the chatter who fired them.
- [Bot Expressions reference](/help/bot/expressions) - every built-in chat expression the @overlabels bot
  ships with, plus the full `!ol` chat-admin vocabulary.
- [Lists](/help/lists) - if you find yourself aliasing list operations, the underlying `!list`
  meta-command is documented end-to-end here.
