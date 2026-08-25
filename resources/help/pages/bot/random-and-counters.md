---
title: Random Rolls and Counters - Overlabels Help
description: "Add random numbers and running counters to your Twitch chat commands with Overlabels: [[[rand:0-69]]] rolls a number, [[[counter:wins]]] counts up, and both are set up from chat in one line."
heading: Random Rolls and Counters
lead: Roll a random number or keep a running count, set up from chat in one line. Your counters are real Controls, so the number in chat and the number on your overlay are the same number.
canonical: https://overlabels.com/help/bot/random-and-counters
context: settings.bot.commands.*
section: Bot
---

Two tags, both written straight into a chat command, both set up without leaving Twitch.

```
!ol cmd add steven your Steven Level is [[[rand:0-69]]]%! Kappa.
!ol cmd add wins So far, Jasper has won [[[counter:wins]]] times
```

That's the whole feature. The rest of this page is the detail.

## Rolling a random number

`[[[rand:0-69]]]` picks a random whole number between 0 and 69, fresh every time someone runs the
command.

```
!ol cmd add steven your Steven Level is [[[rand:0-69]]]%! Kappa.
```

> **stevenwastaken:** !steven
> **overlabels:** your Steven Level is 42%! Kappa.

Write both bounds, low number first. They're included, so `[[[rand:1-6]]]` can roll a 1 and can roll a
6.

**Each tag rolls on its own.** Two dice in one message really are two dice:

```
!ol cmd add roll [[[bot:from_user]]] rolls [[[rand:1-6]]] and [[[rand:1-6]]]
```

**Negative numbers aren't supported.** `[[[rand:-5-5]]]` gets refused when you save it, with a reason,
rather than silently going blank in the middle of your stream. If you want a spread around zero, roll a
positive range and say what it means in the text.

**You can format the number.** Anything on the [Formatting](/help/formatting) page works here, because
`rand:` is just a normal tag:

```
[[[rand:0-1000000|number]]]   ->   847,215
```

## Counting things

`[[[counter:wins]]]` adds one, then shows the new total.

```
!ol cmd add wins So far, Jasper has won [[[counter:wins]]] times
```

> **jasperfrontend:** !wins
> **overlabels:** added !wins - started counter wins at 0
>
> **someone_else:** !wins
> **overlabels:** So far, Jasper has won 1 times
>
> **someone_else:** !wins
> **overlabels:** So far, Jasper has won 2 times

"1 times" is sloppy. An `[[[if:...]]]` fixes it - see [Singular and plural](#singular-and-plural) below.

**You don't create the counter first.** Saving the command creates it for you, starting at 0, and the
bot tells you it did. This works from the dashboard too, but the point is that you can add a counter
mid-stream, in chat, in one line, without alt-tabbing anywhere.

### Your counter is a real Control

This is the part worth knowing. `wins` isn't a private thing the bot keeps to itself. It's an ordinary
[Counter Control](/help/controls), sitting on your Controls page next to everything else you've made.

Which means all of this already works on it, with nothing further to set up:

| What you want | How |
|---|---|
| Show it on your overlay | `[[[c:wins]]]` in your template |
| Fix a wrong number | `!set wins 40` |
| Add more than one | `!increment wins 5` |
| Start over | `!reset wins` |
| Edit it by hand | Your Controls page |

And because it's a Control, **it broadcasts.** The moment chat says "3 wins", the number on your overlay
says 3 too. You aren't keeping a chat counter and an on-screen counter in sync, because there is only
one counter.

### `counter:` counts, `c:` only looks

Same number, two tags, one difference: `counter:` adds one, `c:` just reads it.

So when you want a command that reports the total without moving it, use `c:`:

```
!ol cmd add wincount Jasper is on [[[c:wins]]] wins this season
```

Now `!wins` scores a win and `!wincount` reports the score. Chatters can ask as often as they like.

### Singular and plural

A counter is a number, and numbers have this habit of being 1 sometimes. `[[[if:...]]]` works in a
command exactly like it does in an overlay, so put the `s` behind a condition:

```
!ol cmd add wins So far, Jasper has won [[[counter:wins]]] time[[[if:c:wins != 1]]]s[[[endif]]]
```

> **someone_else:** !wins
> **overlabels:** So far, Jasper has won 1 time
>
> **someone_else:** !wins
> **overlabels:** So far, Jasper has won 2 times

Use `c:` inside the condition, not `counter:`. A condition only looks at the number; the
`[[[counter:wins]]]` in the text is what does the counting, and it has already counted by the time the
condition is checked.

`elseif` and `else` work too, and every comparison from the [Conditionals](/help/conditionals) page
(`=`, `!=`, `>`, `>=`, `<`, `<=`, or a bare tag for "is it set") means the same thing here. What
doesn't work in chat is `[[[foreach]]]` - a reply is one line, so there's nothing to repeat into.

```
!ol cmd add streak [[[if:c:wins >= 10]]]Jasper is ON FIRE[[[elseif:c:wins >= 3]]]Jasper is warming up[[[else]]]Jasper is just getting started[[[endif]]] - [[[c:wins]]] wins so far
```

### Counting once, twice, and several things

**Using the same counter tag twice in one message still counts once.** The tag means *this command
counts*, not *add one right here*, so you can mention the total more than once safely:

```
!ol cmd add wins Win number [[[counter:wins]]]! That's [[[counter:wins]]] this season.
```

That's one win, reported twice.

**Previewing never counts.** Typing a command into the dashboard's preview box, or editing one, doesn't
touch the number. Only actually running the command in chat does.

**One command can move several counters:**

```
!ol cmd add score [[[counter:wins]]] wins, [[[counter:losses]]] losses
```

Each of those goes up by one per run, and each is its own Control.

### Naming a counter

Same rules as any other Control: lowercase letters, numbers and underscores, starting with a letter.
`wins`, `deaths`, `bad_puns` are all fine. `Wins`, `1st_place` and `bad-puns` are not, and get refused
when you save with an explanation.

If you already have a Control by that name that can't hold a number (a text Control called `motto`, say),
`[[[counter:motto]]]` is refused rather than quietly overwriting it. Pick another name.

## Where these work

Both tags are for the bot. They work in Bot Commands - commands you write with `!ol cmd add` or on
the Bot Commands page - and nowhere else.

**Random doesn't belong in an overlay,** because an overlay redraws whenever anything changes, so a
random number in one would reroll at moments you didn't pick. If you want a number that shuffles on
screen, make a Number Control, turn on its random mode and set how often it rerolls. That way the timing
is your decision.

**Counting doesn't belong in an overlay either,** because a counter counts when something *happens*, and
an overlay redrawing isn't something happening. Use `[[[c:wins]]]` to display the total, and let the
chat command do the counting.

## If you typo it

Overlabels checks these tags when you save, not when they run, so a mistake costs you one retype in
chat instead of showing up live in front of everyone. If something's off, the bot says so and doesn't
save the command.

| You wrote | The bot says |
|---|---|
| `[[[rnd:0-69]]]` | There's no 'rnd' tag, so nothing would show up where you put `[[[rnd:0-69]]]`. Did you mean 'rand'? |
| `[[[countr:wins]]]` | There's no 'countr' tag... Did you mean 'counter'? |
| `[[[counter wins]]]` | I can't read `[[[counter wins]]]`, so chat would see it exactly as written. Tag names join up with a colon and no spaces, like `[[[counter:wins]]]`. |
| `[[rand:0-69]]` | `[[rand:0-69]]` needs three brackets on each side. Try `[[[rand:0-69]]]` instead. |
| `[[[rand:-5-5]]]` | That isn't a valid range. Write two whole numbers low to high, like `[[[rand:0-69]]]`. Negative numbers aren't supported. |
| `[[[rand:69]]]` | Same. Ranges need both a low and a high number. |
| `[[[counter:my-wins]]]` | 'my-wins' isn't a usable counter name. Use lowercase letters, numbers and underscores, starting with a letter. |

The same checks run on the dashboard, where the message appears under the field.

**One thing that isn't checked:** an ordinary tag name with a typo in it, like `[[[chanel_title]]]`.
Those are left alone on purpose, because a tag that's simply empty right now (nobody has followed yet,
say) looks identical, and refusing to save it would block plenty of commands that are perfectly fine. If
a command runs and part of the sentence is missing, a misspelled tag name is the first thing to check.
`Alt+R` opens the tag reference from any page in Overlabels, with the real names to copy.

## Quick reference

| Tag | What it does |
|---|---|
| `[[[rand:0-69]]]` | A random whole number, 0 to 69, rolled per use |
| `[[[rand:1-6]]] [[[rand:1-6]]]` | Two independent rolls |
| `[[[rand:0-1000000\|number]]]` | A roll, formatted with separators |
| `[[[counter:wins]]]` | Add one to `wins`, show the new total |
| `[[[c:wins]]]` | Show `wins` without changing it |
| `time[[[if:c:wins != 1]]]s[[[endif]]]` | "1 time", "2 times" |
| `!set wins 40` | Correct the total |
| `!increment wins 5` | Add five |
| `!reset wins` | Back to zero |
| `!ol help tags` | A short reminder of both tags, in chat |

## See also

- [Bot Commands](/help/bot/commands) - everything else the bot understands
- [Controls](/help/controls) - what a Control is and what else it can do
- [Formatting](/help/formatting) - the `|` formatters you can put on any tag
