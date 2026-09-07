---
title: The living Twitch title - a stream title that keeps itself up to date
section: Live data
description: Write your Twitch stream title with tags in it and Overlabels keeps it true. A follow arrives, the number in your title moves. How to set it up, what you can put in it, when it updates, what happens when you change the title yourself, and how to drive it from chat with !ol title.
heading: Living Twitch title
lead: Write your stream title with tags in it, and Overlabels keeps it true on Twitch. A follow arrives, the number in your title moves. Set it on the settings page or from chat with !ol title.
canonical: https://overlabels.com/help/living-title
context: settings.title.show
keywords: title, stream title, twitch title, living title, dynamic title, auto title, automatic title, change title, change my title, set title, update title, edit title, rename stream, stream name, title template, title not updating, title paused, paused, resume title, followers in title, follower count in title, sub count in title, category, game, stream category, set category, change category, ol title, title bot, title from chat, channel:manage:broadcast, reauthorize
---

Everything in Overlabels receives data and reacts to it. The living title is the one thing that
writes back to Twitch: a stream title written as a template, with tags in it, that Overlabels keeps
true for you.

```
Road to 2K | [[[followers_total]]] followers | playing [[[channel_game]]]
```

Save that, and your title on Twitch reads `Road to 2K | 1525 followers | playing Minecraft`. Someone
follows, and a few seconds later it reads `1526`. Switch categories in the Twitch dashboard, and the
game name follows. You never touch the title again.

## Setting it up

1. Open **Settings -> Stream title**. If a yellow bar asks you to reauthorize Twitch, do that first:
   changing your title needs one permission Overlabels did not ask for before this feature existed,
   and Twitch only grants it when you log in again. Once.
2. Write your title in the **Title template** box. Type `[[[` anywhere and use any tag from
   [your tags](/tags), any control as `[[[c:key]]]`, or a service control like
   `[[[c:kofi:donations_received]]]`.
3. Watch the **Preview**. It renders with your real values as you type, and counts characters
   against Twitch's limit of 140.
4. Tick **Keep my title updated** and click **Save title**. Twitch updates within a few seconds.

The page also shows what is on Twitch right now, so you can see the write land.

## What you can put in it

A title is one line, and it takes everything a one-line message takes:

- **Tags** from the catalogue: `[[[followers_total]]]`, `[[[subscribers_total]]]`,
  `[[[channel_game]]]`, `[[[followers_latest_user_name]]]` and the rest of [your tags](/tags).
- **Controls**, your own and service-managed: `[[[c:wins]]]`, `[[[c:kofi:total_received]]]`.
- **Conditions**: `[[[if:c:wins > 0]]] | [[[c:wins]]] wins today[[[endif]]]`. See
  [Conditionals](/help/conditionals).
- **Pipes**: `[[[c:kofi:total_received|currency:EUR]]]`. See [Formatting](/help/formatting).
- **Defaults**: `[[[c:goal ?? no goal yet]]]`.

Three things are refused when you save, each with a sentence saying why:

- `[[[channel_title]]]`. Once the living title is on, the title on Twitch *is* whatever your template
  renders, so reading it back would feed on itself.
- `[[[rand:...]]]` and a List's `:random`. A value that changes every time it renders would rewrite
  your title on every event, and a title that flickers is worse than one that lags.
- `[[[foreach:...]]]`. A title is one line.

Anything longer than 140 characters after rendering is cut at 140. The preview tells you when that
happens, so you can shorten the template rather than find out on your channel page.

## Examples

Every one of these works as written. Copy it into the template box, or type
`!ol title set ` in front of it in chat.

### The follower goal

```
Road to 2K | [[[followers_total]]] followers
```

The classic. Renders as `Road to 2K | 1525 followers` and ticks up on every follow.

### Follower goal with the gap counted for you

```
[[[followers_total]]]/2000 followers | [[[if:followers_total >= 2000]]]WE DID IT[[[else]]]almost there[[[endif]]]
```

The condition flips the moment the count crosses the line, and the title changes exactly once.

### Playing what, with the category kept honest

```
[[[channel_game]]] with chat | [[[followers_total]]] followers | !discord for the server
```

`[[[channel_game]]]` follows the category, so switching from Just Chatting to Minecraft in the
dashboard rewrites the front of the title on its own.

### Tonight's numbers, reset every stream

```
[[[c:twitch:follows_this_stream]]] new follows | [[[c:twitch:subs_this_stream]]] new subs tonight
```

The per-stream counters go back to zero when you go live, so the title starts the night at
`0 new follows | 0 new subs tonight` and grows from there.

### Sub goal with the points

```
[[[subscribers_total]]] subs ([[[subscribers_points]]] points) | goal 100
```

### Ko-fi total on the title

```
[[[c:kofi:total_received|currency:EUR]]] raised for the new PC | thank you [[[c:kofi:latest_donor_name ?? everyone]]]
```

Renders as `€42.00 raised for the new PC | thank you Sam`, and as `... thank you everyone` before the
first donation. Swap `kofi` for `streamlabs`, `fourthwall`, `bmac` or `throne`.

### Bits tonight

```
[[[c:twitch:bits_this_stream|number]]] bits cheered tonight | last cheer from [[[c:twitch:latest_cheerer_name ?? nobody yet]]]
```

### A wins counter you bump from chat

```
[[[channel_game]]] | [[[c:wins]]] wins today | [[[followers_total]]] followers
```

Make a counter control called `wins`, then `!increment wins` from chat (or a bot command with
`[[[counter:wins]]]` in it). Every bump re-renders the title.

### Only mention the thing when it is happening

```
Chill stream[[[if:c:wins > 0]]] | [[[c:wins]]] wins so far[[[endif]]][[[if:c:twitch:raids_this_stream > 0]]] | raided [[[c:twitch:raids_this_stream]]] times[[[endif]]]
```

Empty until something happens, then grows one clause at a time. Note the separator lives inside the
condition, so there is no stray `|` when the clause is off.

### IRL: where you are and how far you have walked

```
IRL in [[[c:checkin:latest_checkin_place ?? the city]]] | [[[c:gps:session_distance|distance:km]]] km today | [[[c:checkin:checkins_this_stream]]] chatters checked in
```

Needs the Overlabels mobile app for GPS and the [Checkin](/help/checkin) integration for the place
names. `|distance:mi` if you think in miles.

### Chat as the headline

```
[[[c:twitch:unique_chatters_this_stream]]] of you talking tonight | [[[c:twitch:chat_messages_this_stream|number]]] messages and counting
```

### A title that shouts

```
[[[channel_game|uppercase]]] | [[[followers_total]]] FOLLOWERS | DAY [[[c:day]]] OF THE CHALLENGE
```

`|uppercase` on the category, and a plain number control called `day` you bump once a stream.

### Latest follower, called out

```
Welcome [[[followers_latest_user_name ?? new friends]]]! | [[[followers_total]]] followers and climbing
```

The name changes on every follow. This one writes more often than the others, still never more than
once every 30 seconds.

### Goal from Twitch's own goal widget

```
[[[goals_latest_description]]]: [[[goals_latest_current]]]/[[[goals_latest_target]]]
```

Whatever goal you have running on Twitch, current over target, kept in step.

> [!TIP]
> Keep an eye on the character counter. A long template with three or four tags in it renders longer
> than it looks, and everything past 140 is cut.

## When it updates

Any event and any control change can move a tag, so any of them triggers a render: a follow, a sub, a
cheer, a raid, a Ko-fi donation, a counter you bump from chat, the go-live reset, a control you edit
by hand. Rather than keep a list of which events matter, Overlabels re-renders on all of them and
writes to Twitch **at most once every 30 seconds, and only when the text actually changed**.

A gift bomb of 100 subs is 100 events in a few seconds. Your title updates once, with the final
number.

## When you change the title yourself

Every time your title changes on Twitch, Overlabels hears about it, including its own writes. If the
new title is not the one it last wrote, you changed it, in the dashboard, in Twitch Studio or through
another tool. Overlabels **pauses** rather than overwrite you a minute later.

You will see it in two places: an amber **Title paused** link under your name in the header, on every
page, and a banner on the settings page saying what the title was changed to. Click **Resume**, or
save the template again, and the template takes over. Your dashboard edit is replaced, so if you meant
to keep it, put it in the template.

Switching the feature off leaves the title as it is on Twitch. Nothing is restored.

## From chat: !ol title

A bare `!title` is taken by StreamElements, WizeBot and Fossabot, so the living title lives behind
`!ol` like every other in-chat admin command. Moderators and the broadcaster can use it.

| Command | What it does |
|---|---|
| `!ol title set <text>` | Save the text as the template and switch the feature on. Tags work: `!ol title set Road to 2K \| [[[followers_total]]] followers`. The bot replies with what it renders to. |
| `!ol title show` | Print the template, whether it is on, paused or off, and what was last written to Twitch. |
| `!ol title resume` | Take over again after a pause. |
| `!ol title off` | Stop updating. The title stays as it is. |

The same rules apply as on the settings page: a refused template gets the same sentence spoken in
chat, and a `set` before you have reauthorized Twitch saves the template and tells you the write
cannot happen yet.

> [!TIP]
> `!ol title set` is the fastest way to change the whole title mid-stream without leaving OBS. Because
> saving from chat renders at once, the new title is on Twitch within seconds.

## The category picker

Under the title on the settings page is a category search. Type a couple of letters, pick from the
list, and the category is set on Twitch right then. It is a one-shot: the living title never touches
your category again, so switching games in the Twitch dashboard mid-stream is never fought.

If you want the category *in* your title, `[[[channel_game]]]` is the tag, and it follows whatever
the category is.

## Good to know

- **Moderators can set the title from chat.** They can in the Twitch dashboard too. If you would
  rather they did not, keep the bot's `!ol` for yourself by not modding it.
- **A template made only of tags that are all empty is never written.** Your title is not blanked
  because a value happened to be missing.
- **Whitespace is tidied.** Runs of spaces and line breaks become one space before the write, so the
  preview and Twitch agree.
- **The category is not in the template**, on purpose. See above.
- If the settings page shows a red message, Twitch refused the last write or your login has
  expired. The message says which, and what to do.
