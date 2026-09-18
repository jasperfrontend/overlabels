---
title: For viewers - what Overlabels keeps about you, and how to remove it
section: Getting started
description: You typed something in a Twitch chat and your name appeared on the stream. Overlabels is the software that drew it. Here is exactly what is kept about you, for how long, and how to have it removed.
heading: Why your name is on a stream
lead: If you typed something in a Twitch chat and your name or your city appeared on the stream, Overlabels is the software that drew it. This page is for you, not for the streamer. It says what we keep, for how long, and how to have it removed.
canonical: https://overlabels.com/help/viewers
keywords: why is my name on screen, remove my data, delete my checkin, who is overlabels, overlabels bot, what is this bot, forget me, viewer privacy, stop showing my name
---

Short version: Overlabels is overlay software. A streamer uses it to put live things on their stream,
like a follower name, a chat message, or a map of where their viewers are. When you typed in their
chat, that went to Twitch, and their overlay drew it.

We are not Twitch, and we are not the streamer. We are the tool in between.

## The first thing worth knowing

**Your chat messages do not reach us.** When a streamer shows chat on screen, the overlay reads Twitch
directly, the same way any chat app does. Your messages go from you to Twitch to their screen. They do
not pass through our servers and we do not store them.

There is one small exception, and it is one message: so a streamer can put "most recent chatter" on
screen, we hold the single latest message and name, and the next one replaces it. There is no history
and no second message.

## What we keep, and when

Only if you did the thing. If you have never used a chat command, most of this list is empty for you.

| If you... | We keep | For how long |
|-----------|---------|--------------|
| Typed a check-in command | Your Twitch name, and the city you named plus that city's coordinates | 90 days after your most recent check-in |
| Played a chat game like the tower | Your Twitch name and your chat colour | Until the tower falls, which is usually minutes |
| Were added to a streamer's list | Whatever the command added, usually your name | It is the streamer's list, so until they clear it |
| Followed, subscribed, cheered or raided | What Twitch told us: your name, your avatar picture, and what the event was | 90 days |
| Donated through Ko-fi, Streamlabs, Fourthwall, Buy Me a Coffee or Throne | Your display name, your message and the amount | 90 days |

**The coordinates are the city's, not yours.** If you typed Amsterdam, we store where Amsterdam is. We
have no idea where in Amsterdam you are, and we never ask your device.

## What we never have

- **Your email address.** Not yours and not the streamer's. We strip it out of the Twitch login before
  anything is saved, and there is no column in our database to put one in.
- **Your payment details.** If you donated, the money went to the donation service, not to us. We get
  told your name, your message and the amount. If that service also sends us your email address or a
  postal address, **we throw it away before it is written down**.
- **Anything about you anywhere else.** No tracking, no advertising, no cookies that follow you, no
  profile built across sites. We do not sell anything to anyone.
- **Your identity if you cheered anonymously.** If you chose anonymous, you stay anonymous. We clear
  the name ourselves rather than trusting it to already be blank.
- **What you bet on a prediction.** Twitch tells us who wagered what. We delete that before storing,
  because an overlay does not need it.

## Your name might be read out loud

Some streamers have alerts that speak. If they do, the sentence, which may include your name, is sent
to a text-to-speech service to be turned into audio. That audio file is deleted after 7 days.

## How to have your data removed

**Type `!forgetme` in any chat where the Overlabels bot is present.** That is the whole thing. It
deletes what we hold about you, everywhere, not only in that channel, and it tells us not to store you
again. You do not need to give a reason, quote a law, or explain yourself.

It works for everyone, and a streamer cannot switch it off or restrict it to subscribers or mods. It
is the one command on the platform that belongs to you rather than to the channel.

If you would rather not type it in public, email **privacy@overlabels.com** with your Twitch
username instead.

Either way, your check-ins, your blocks in any chat game, the record of anything you added to a list,
and the events Twitch sent us about you all go.

You can also just ask the streamer. Things like their lists and their overlays are theirs to edit, and
`!forgetme` deliberately does not reach into them: a raffle entry sitting on somebody's dashboard is
their content, not ours to quietly delete.

> [!NOTE]
> **It also stops it happening again.** After `!forgetme`, using a chat command does not put you back:
> check-ins and chat games quietly do nothing for you from then on. To do that we keep one thing, and
> only this: your Twitch ID and the date, so we know to keep ignoring you. No name, no channel, no
> reason. Remembering that we were asked to forget you is the only way to actually do it.

## What we cannot remove

- **The stream itself.** If you were on screen, you may be in a VOD, a clip or someone's recording.
  That is Twitch and the streamer, not us, and we have no reach into it.
- **Our backups**, for up to 30 days. We keep a nightly copy of the database in case of disaster.
  Nothing reads it in the meantime, and your data goes when that copy expires.
- **Anything already copied** by a streamer into their own notes or overlay text.

## If you would rather not appear at all

You do not have to use the chat commands. Nothing about watching a stream, chatting normally,
following or subscribing puts you on an Overlabels overlay unless the streamer has set up an alert for
it, and those show what Twitch already shows publicly.

If a streamer's overlay is showing something about you that you think is unfair, tell them first. They
control what their overlay draws. If that goes nowhere, email us.

## The longer version

Everything above is a summary written for viewers. The complete inventory, including the parts that
concern streamers, is at [Your data on Overlabels](/help/your-data).
