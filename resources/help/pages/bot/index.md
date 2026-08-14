---
title: Twitch Bot - Overlabels Help
description: How the @overlabels Twitch chat bot works - chat-driven controls, permission tiers, and the full command list.
heading: Twitch Chat Bot
lead: The @overlabels bot joins your channel as a moderator and lets you - and optionally your mods, VIPs, or viewers - change overlay controls directly from chat. No tab-switching, no dashboards during a stream.
canonical: https://overlabels.com/help/bot
context: settings.bot.*
section: Bot
---

- [**Expressions**](/help/bot/expressions) - the built-in chat expressions the bot ships with, plus one
  working example per expression.
- [**Random Rolls and Counters**](/help/bot/random-and-counters) - `[[[rand:0-69]]]` rolls a number and
  `[[[counter:wins]]]` keeps a running total, both set up from chat in one line. Counters are real
  Controls, so the number in chat and the number on your overlay are the same number.
- [**Bot Aliases**](/help/bot/aliases) - short chat commands that rewrite to longer ones. `!w 2` becomes
  `!inc wins 2` before dispatch. Positional placeholders, one-hop guard, shared validation.
