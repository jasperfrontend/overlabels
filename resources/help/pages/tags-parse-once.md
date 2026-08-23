---
title: Why tags are parsed exactly once - Overlabels
description: "Overlabels resolves [[[tag]]] markers in a single pass per render, and substituted values are never re-scanned for tags. This page explains the rule, the attack it prevents, and what it means while writing templates."
heading: Why tags are parsed exactly once
lead: Every render scans your template for tags one time. Whatever a tag resolves to is inserted as plain text and never scanned again - so a value that happens to look like a tag renders as the literal characters. This is a security rule, and this page explains it.
canonical: https://overlabels.com/help/tags-parse-once
---

## The rule

When an overlay renders, the template you wrote is scanned once for `[[[tag]]]` markers. Each one is
replaced with its current value, and that is the end of it: the values that were substituted in are
never scanned for tags themselves. There is no second pass, no "resolve until nothing changes" loop,
and no way to make one happen from inside a template.

The practical consequence: if a value *contains* something shaped like a tag, it renders as the
literal text. A donation message reading `[[[channel_title]]]` appears on your overlay as exactly
those characters, not as your channel title.

## Why: your overlay renders strangers' text

An overlay is full of text you did not write. Donor names, donation messages, chat messages, the
display name of whoever just followed - all of it is typed by other people, and all of it ends up
substituted into your template.

If substituted values were re-scanned for tags, anyone who can get text onto your overlay could
write template code into it. A chatter could type `[[[c:kofi:total_received]]]` into chat and your
chat overlay would resolve it - printing a number you never chose to put on screen. The data an
overlay renders from carries every control and tag value on your account, not just the ones your
template happens to name, so one reparsed message could read any of it out loud on stream.

Parsing exactly once closes that entire class of attack. Your template is the only place tags are
ever read from, and you are the only person who writes your template. Text that arrives as *data*
stays data.

> [!IMPORTANT]
> This is the whole reason the rule exists. It is sometimes described as a performance optimization,
> and a single pass is indeed cheap - but speed is a side effect. The rule is there so that nobody
> can smuggle template code into your overlay through a username, a message, or a control value.

## Even sneaky compositions stay inert

It is not enough for each value to be harmless on its own, because one person often controls several
values at once. So the protection does not depend on spotting suspicious values - it depends on the
pass boundary itself.

The test case the code is built against: two controls whose values are `scr` and `/scr`, composed in
a template as `<[[[c:scr]]]ipt>` and `<[[[c:scr_end]]]ipt>`. After substitution the output contains
the literal string `<script>` - but the pass is already over, nothing re-reads the result as a
template, and HTML encoding neutralises it for the page. Fragments cannot assemble into something
that runs, because there is no later step that would run it.

Inside `[[[foreach]]]` loops the same rule holds, with one extra guard: loop values are substituted
*before* the outer pass runs, so their square brackets are converted to harmless entities first.
That applies even to `[[[msg.html]]]`, the one chat field that is allowed to carry markup for emote
images - brackets are defused there too, precisely so a chat message cannot sneak a tag past the
boundary.

## What this means while writing templates

- **Tags cannot be built out of other tags.** A control value containing `[[[followers_total]]]`
  renders as that literal text. If you want a value derived from other values, that is what
  [Expression Controls](/help/expressions) are for.
- **Expressions never parse tags either.** Inside an expression you read values as `t.tag_name` and
  `c.control_name`, never as `[[[tag]]]`. See the [Math Engine](/help/math) guide.
- **`?? default` fallbacks are part of your authored template**, so they are emitted verbatim and
  never re-parsed. A tag written inside a default will not resolve.
- **Square brackets in messages are safe and stay visible.** A chatter with `[AFK]` in their name
  renders as `[AFK]` - defusing swaps brackets for entities the browser displays identically, so
  nothing is silently eaten.
- **A typo'd tag renders as nothing, not an error.** Unrelated to reparsing, but part of the same
  philosophy: the render never executes anything it did not recognise from your own template.

## Related

- [How an overlay renders](/help/rendering) - where the single tag pass sits in the full pipeline
- [Twitch Chat in an Overlay](/help/chat) - the loop that renders the most hostile text of all
- [Expression Controls](/help/expressions) - the supported way to compute one value from others
