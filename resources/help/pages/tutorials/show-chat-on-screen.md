---
title: Show chat on screen - Overlabels Tutorial
description: Put your live Twitch chat in an overlay with one foreach loop. Copy, paste, done - no setup and no extra permissions.
heading: Show chat on screen
lead: Put your live Twitch chat in an overlay. One loop, no setup, no extra permissions - copy the block below into a static overlay and you have a chat feed.
context: settings.chat
canonical: https://overlabels.com/help/tutorials/show-chat-on-screen
---

## What you get

A live chat feed in your overlay, updating as people talk. Names in their own colours, badges, emotes,
and messages disappearing when a mod deletes them.

## The whole thing

Create a **static** overlay and paste this in:

```
[[[foreach:chat as msg]]]
  <div class="chat-line">
    <span class="chat-author" style="color: [[[msg.color]]]">[[[msg.author]]]</span>
    <span class="chat-text">[[[msg.html]]]</span>
  </div>
[[[endforeach]]]
```

That is the feature. Add it to OBS and it works.

## What each part does

`[[[foreach:chat as msg]]]` loops over the messages currently on screen. `msg` is a name you picked -
call it anything, then use the same name inside the loop.

- `[[[msg.author]]]` - the display name, capitalised the way the chatter writes it
- `[[[msg.color]]]` - the colour they chose in Twitch, as a hex value you can drop into CSS
- `[[[msg.html]]]` - the message with emotes already turned into images

Use `[[[msg.text]]]` instead of `[[[msg.html]]]` if you want the plain text with no emote images.

> [!NOTE]
> Index 0 is the **oldest** message, so a plain top-to-bottom list reads in the order things were said.
> Use `flex-direction: column-reverse` in CSS if you want newest at the top.

## Make it look like something

The loop gives you plain elements. Everything else is CSS, and it is yours:

```html
<style>
  .chat-line {
    margin-bottom: 6px;
    font: 500 20px/1.4 'Albert Sans', sans-serif;
    color: #fff;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8);
  }
  .chat-author {
    font-weight: 700;
    margin-right: 6px;
  }
  /* Emotes come through as <img>; size them here, not in the loop. */
  .chat-text img {
    height: 1.4em;
    vertical-align: middle;
  }
</style>
```

The text shadow matters more than it looks: an overlay sits on top of gameplay, and you do not know
what colour is behind any given pixel. See [For Designers](/help/for-designers) for the rest of that
problem.

## Adding badges

`[[[msg.badge_images]]]` renders the mod sword, the subscriber emblem, and so on as images:

```
[[[foreach:chat as msg]]]
  <div class="chat-line">
    <span class="chat-badges">[[[msg.badge_images]]]</span>
    <span class="chat-author" style="color: [[[msg.color]]]">[[[msg.author]]]</span>
    <span class="chat-text">[[[msg.html]]]</span>
  </div>
[[[endforeach]]]
```

There is also `[[[msg.badges]]]`, which gives you the bare set names (`moderator subscriber`) rather
than pictures - useful as CSS classes when you want to style moderators differently instead of showing
an icon.

## Styling one kind of chatter

Every message carries flags you can test with a conditional:

```
[[[foreach:chat as msg]]]
  [[[if:msg.first]]]
    <div class="chat-line first-time">
      <span class="chat-author">[[[msg.author]]]</span> said something for the first time!
    </div>
  [[[else]]]
    <div class="chat-line">
      <span class="chat-author" style="color: [[[msg.color]]]">[[[msg.author]]]</span>
      <span class="chat-text">[[[msg.html]]]</span>
    </div>
  [[[endif]]]
[[[endforeach]]]
```

`msg.mod`, `msg.sub`, `msg.vip`, `msg.broadcaster` and `msg.first` all work the same way.

## How many messages show

The window is 50 messages by default. Change it under **Foreach caps** on
[your account settings](/settings/account) - the overlay picks up the new size the next time it loads.

Set it to what your layout can actually fit. A window taller than the space you gave it just means the
oldest messages are drawn off-screen.

## Hiding things you do not want on stream

Two settings live at [/settings/chat](/settings/chat):

- **Hide commands** - drops messages starting with `!`, so `!discord` and `!lurk` never reach the overlay
- **Hidden chatters** - a list of logins whose messages are not drawn

Both default to showing everything. Filtered messages are dropped before they take a slot in the
window, so someone spamming commands cannot push real conversation off your screen.

> [!IMPORTANT]
> These are display settings, not moderation. They change what your overlay draws and nothing else -
> the message is still in your chat, and everyone watching on Twitch still sees it. Deletions by you or
> your mods are a separate mechanism and are always honoured.

## Things worth knowing

- **The overlay talks to Twitch directly.** It connects anonymously, reads chat, and renders it.
  Nothing is relayed through Overlabels and nothing about your chat is sent to us.
- **Deletions are honoured.** When a mod deletes a message or times someone out, it disappears from the
  overlay too.
- **During a collab**, messages from the other channel arrive with `[[[msg.source_channel]]]` set. Use
  it to label them, or ignore it and let the feed read as one conversation.

## Where next

- [Twitch Chat in an Overlay](/help/chat) - the full guide: every per-message tag, Shared Chat, emotes,
  and the four chat controls
- [Show your latest follower](/help/tutorials/latest-follower) - the same idea with a single value
  instead of a list
