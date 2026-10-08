---
title: Overlabels and AI - your data never trains one
section: Getting started
description: Overlabels will never use your data, your viewers' data or your supporters' data to train an AI. Where your data lives, the one AI service Overlabels talks to, how Claude is used to build Overlabels, and what that does and does not mean for your data.
heading: Overlabels and AI
lead: Your data will never be used to train an AI. Here is that promise, the one AI service Overlabels does talk to, and an honest account of how AI is used to build Overlabels itself.
canonical: https://overlabels.com/help/ai
keywords: ai, artificial intelligence, ai training, machine learning, llm, claude, chatgpt, anthropic, openai, train on my data, ai policy, vibe coded
---

## The promise

**Overlabels will never use your data to train an AI.** Not yours, not your viewers', not the people
who support you. No deal with an AI company, no "anonymised dataset", no opt-out hidden in a setting
somewhere. I would rather stop building Overlabels than hand your data over to the AI gods. It is not
going to happen, ever.

Overlabels does not run an AI model on your data either. Your overlays, your events, your chat and
your settings are handled by plain code that does what it says.

## Where your data lives

Your data is stored in the EU only: the server is in Frankfurt, and the database is backed up once a
day to two separate providers, Cloudflare R2 in the EU and Scaleway in Paris.

Your Twitch tokens and every credential you give an integration are **encrypted** before they are
stored. The rest of the database is not encrypted field by field: the application has to be able to
read your overlays and events to show them, and I, as the person who runs Overlabels, can read them
too. The database itself is not reachable from the internet at all.

The full inventory, table by table, including how long each thing is kept and what deleting your
account removes, is on [Your data on Overlabels](/help/your-data).

## The one AI service Overlabels talks to

Spoken alerts. If you set an alert to be read out loud, the text of that alert is sent to
**ElevenLabs** to turn it into a voice, because that is what makes the voice sound like a person. That
text can include a donor's message or a viewer's resub message, so it is worth knowing.

Only the alert text goes, and only for alerts you set to be spoken. Nothing else about you or your
viewers is sent with it. What ElevenLabs does with that text is covered by their terms, not ours. The
audio comes back to Overlabels and is deleted after 7 days.

If you never turn on a spoken alert, Overlabels never talks to an AI service on your behalf.

## Yes, Overlabels is built with Claude

Massive parts of Overlabels have been written with Claude Code, Anthropic's AI coding assistant.
Claude is an absolute godsend for anyone who writes code, and not using AI to build an app in 2026 is
just not a smart decision. One person could not have built all of this alone in the time it took.

### Does that mean Claude can read my data?

Not on its own. Claude has no login to Overlabels, no access to the live database and no copy of it.
It works on the source code, on my computer, against a test database filled with made-up data.

There is one exception, and it is a narrow one. **Three Overlabels users have explicitly agreed** to
let me look at their real data while Overlabels is being built, so a bug on a real stream can be found
on a real stream. When I chase a problem for one of them, rows from those three accounts can end up in
my conversation with Claude. I will not say who they are.

Now and then I also ask the database a question like "how many streamers have connected Ko-fi?" with
Claude's help. What comes back is a number, not anyone's events, messages or names.

My conversations with Claude go to Anthropic, the company behind it, and my account is set so that
they are not used to train its models.

## Can I point an AI at the Overlabels code?

Oh please, yes, be my guest. Every line of Overlabels is open source on
[GitHub](https://github.com/jasperfrontend/overlabels). Hand it to any AI you like and ask it anything.

Want an AI to build an overlay for you? [llms.txt](/help/llms-txt) is a guide written for exactly
that. Whatever you share with your own AI is between you and that AI; Overlabels sends it nothing.

## Related

- [Your data on Overlabels](/help/your-data) - everything Overlabels stores, and for how long
- [For viewers](/help/viewers) - the same, written for the people in your chat
- [Privacy Policy](/privacy) - the formal version
