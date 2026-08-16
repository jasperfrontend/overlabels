---
title: Twitch Chat in an Overlay
description: Render live Twitch chat in an Overlabels overlay - the foreach loop, every per-message tag, badges and emotes, Shared Chat, display filters, and the four chat controls.
heading: Twitch Chat in an Overlay
lead: Render live Twitch chat with one foreach loop. Every per-message tag, badges and emotes, what happens during a collab, and the settings that decide what gets drawn.
canonical: https://overlabels.com/help/chat
context: templates.show?type=static, settings.chat
---

Chat works in a **static overlay**. Drop this in and you have a chat feed:

```html
[[[foreach:chat as msg]]]
  <div class="msg [[[msg.badges]]]">
    <span style="color: [[[msg.color]]]">[[[msg.author]]]</span>
    <span>[[[msg.html]]]</span>
  </div>
[[[endforeach]]]
```

That is the whole feature. No setup, no connecting anything, no extra permissions.

## Where the messages come from

Your overlay reads chat **straight from Twitch**, over the same anonymous connection any chat viewer
uses. Nothing is relayed through Overlabels.

Three things follow from that, and they are all in your favour:

- Chat keeps working even if Overlabels is having a bad day.
- There is no added delay between someone typing and the message appearing.
- No credentials are involved, so nothing sensitive ever reaches your OBS browser source.

The socket only opens if your template actually renders chat. An overlay that never shows a message
does not hold a connection open for your whole stream.

## Every tag on a message

Inside `[[[foreach:chat as msg]]]`, each message gives you:

| Tag | What it is |
|---|---|
| `[[[msg.author]]]` | Display name, as the chatter capitalised it |
| `[[[msg.login]]]` | Lowercase username |
| `[[[msg.text]]]` | The message as plain text |
| `[[[msg.html]]]` | The message with emotes rendered as images |
| `[[[msg.color]]]` | Their name colour, like `#1E90FF`. Empty if they never picked one |
| `[[[msg.badges]]]` | Badge names, space separated: `broadcaster subscriber` |
| `[[[msg.badge_images]]]` | The badge artwork, as images |
| `[[[msg.at]]]` | When it was sent, as a Unix timestamp |
| `[[[msg.id]]]` | Twitch's id for the message |
| `[[[msg.mod]]]` | Set if they are a moderator |
| `[[[msg.sub]]]` | Set if they are a subscriber |
| `[[[msg.vip]]]` | Set if they are a VIP |
| `[[[msg.broadcaster]]]` | Set if it is you |
| `[[[msg.first]]]` | Set if this is their first ever message in your channel |
| `[[[msg.source_channel]]]` | Empty normally. See [Shared Chat](#shared-chat) below |

Plus `[[[chat.count]]]` for how many messages are currently in the feed.

The last five are flags: they render as `1` when true and as nothing when false, so they work directly
in a condition.

```html
[[[if:msg.first]]]
  <span class="first-timer">First message!</span>
[[[endif]]]
```

### text or html?

Both are the same message. `[[[msg.text]]]` is plain, `[[[msg.html]]]` has the emotes turned into
images - Twitch emotes plus 7TV, BTTV and FFZ. Pick whichever suits the look you are after; there is no
downside to either.

### Newest last

Message `0` is the **oldest** one on screen. Looping in order renders top to bottom, the way chat reads.
The feed holds the most recent 50 messages by default, which is comfortably more than fits on screen
at 1440p. If you want a shorter feed - four lines, or even one - set it under **Chat messages** in
[Foreach loop limits](/settings/account), alongside the limits for every other loop. Reload the
overlay in OBS to pick up a change.

## Styling badges

`[[[msg.badges]]]` gives you names, which is what CSS wants:

```html
<div class="msg [[[msg.badges]]]">...</div>
```

```css
.msg.broadcaster { border-left: 3px solid gold; }
.msg.moderator   { border-left: 3px solid #00ad03; }
.msg.subscriber  { background: rgba(145, 70, 255, 0.08); }
```

`[[[msg.badge_images]]]` gives you the actual emblems Twitch draws, already as images:

```html
<span class="badges">[[[msg.badge_images]]]</span>
```

```css
.badges img { height: 18px; vertical-align: middle; }
```

Every badge image carries the class `ol-badge`, so you can target them without touching the wrapper.
Use one, the other, or both - a template that only wants coloured borders never loads any images.

### Sizing emotes

Emote images carry the class `overlay-emote`, and Twitch's own emotes additionally carry
`twitch-emote`. They default to `height: 1.5em`, which keeps them proportional to whatever text they
sit in. Override it like any other rule:

```css
.overlay-emote { height: 1em; }
```

No `!important` needed - the default is an ordinary stylesheet rule, and yours comes later.

## Messages that get deleted

If a moderator deletes a message, or times someone out, or clears the room, your overlay follows
immediately. That is not optional and there is no setting for it. A message a moderator removed should
not stay burned into your stream, your VOD, and every clip anyone makes of it.

## Shared Chat

During a collab, Twitch shows messages from the other channel in yours. Your overlay shows them too.

`[[[msg.source_channel]]]` is **empty** for a normal message and filled in for one that came from a
partner's channel, so you can mark them rather than passing someone else's audience off as your own:

```html
[[[if:msg.source_channel]]]
  <span class="from-collab">via collab</span>
[[[endif]]]
```

Their badges are the ones they have **in the channel they typed in**, which is what Twitch's own chat
does - a partner's moderator reads as a moderator. Their channel-specific badge art, like a subscriber
emblem, will not draw, because that artwork belongs to their channel and is not yours to borrow. The
badges that mean the same thing everywhere - moderator, VIP, staff - show up fine.

## Deciding what gets drawn

Two settings on the [Chat settings](/settings/chat) page:

- **Hide messages starting with `!`** - keeps bot commands off the overlay.
- **Hidden chatters** - a list of usernames whose messages your overlay skips.

Both are off by default. Whether bot commands are clutter is your call, not ours.

These change **your overlay only**. A hidden message is still in chat, still in the VOD, and every
viewer and moderator still sees it. If you want a message actually gone, that is what Twitch's
moderation tools are for - and as above, your overlay follows those automatically.

Changed a setting? Reload the overlay in OBS to pick it up.

## Counting chat

Four [controls](/help/controls) track chat while you are live, if you add them:

| Control | What it counts |
|---|---|
| `[[[c:chat_messages_this_stream]]]` | Messages this stream |
| `[[[c:unique_chatters_this_stream]]]` | How many different people talked |
| `[[[c:latest_chatter_name]]]` | Who spoke most recently |
| `[[[c:latest_chat_message]]]` | What they said |

The two `_this_stream` counters reset when you go live. The two `latest_` ones do not - they are
most-recent values, so they keep whatever was last there between streams.

These need the **Overlabels bot in your channel**, because the bot is what counts them. The feed above
does not need the bot at all. And during a collab the counters only count your own chatters, so the
feed will show more messages than the counter counts. That is deliberate: a collab should not inflate
your numbers.

## A worked example

```html
<div class="chat">
  [[[foreach:chat as msg]]]
    <div class="msg [[[msg.badges]]]">
      <span class="badges">[[[msg.badge_images]]]</span>
      <span class="name" style="color: [[[msg.color]]]">[[[msg.author]]]</span>
      [[[if:msg.source_channel]]]<span class="collab">via collab</span>[[[endif]]]
      <span class="body">[[[msg.html]]]</span>
    </div>
  [[[endforeach]]]
</div>
```

```css
.chat { display: flex; flex-direction: column; gap: 4px; }
.msg { padding: 4px 8px; border-radius: 4px; background: rgba(0, 0, 0, 0.55); }
.msg.broadcaster { border-left: 3px solid gold; }
.badges img { height: 18px; vertical-align: middle; }
.name { font-weight: 700; }
.collab { font-size: 0.75em; opacity: 0.7; }
```

New messages appear at the bottom. If you want them to animate in, give `.msg` a CSS animation - the
feed updates a few times a second at most, so animations have room to finish.

## See also

- [Conditional Tags](/help/conditionals) - `[[[if:...]]]` and `[[[foreach:...]]]` in full
- [Controls](/help/controls) - what the `[[[c:...]]]` tags above are
- [Overlays vs Alerts](/help/overlays-vs-alerts) - why chat lives in a static overlay
