---
title: Show your latest follower - Overlabels Tutorial
description: Put your most recent follower on screen with one tag, and make the whole thing disappear when you have no followers yet.
heading: Show your latest follower
lead: One tag puts your most recent follower on screen. This tutorial is really about the two things around it - what to draw when there is no follower yet, and the difference between a value that sits there and an alert that fires.
context: templates.create?type=static
canonical: https://overlabels.com/help/tutorials/latest-follower
---

## The one tag

```
Latest follower: [[[followers_latest_user_name]]]
```

Drop that in a **static** overlay and you are done. It updates on its own.

There is a matching set for everything about that person:

| Tag | Holds |
|---|---|
| `[[[followers_latest_user_name]]]` | Display name, capitalised the way they write it |
| `[[[followers_latest_user_login]]]` | Lowercase login - use this for comparisons, never the display name |
| `[[[followers_latest_date]]]` | When they followed |
| `[[[followers_total]]]` | How many followers you have |

## Make it presentable

```html
<div class="follower-card">
  <div class="follower-label">Latest follower</div>
  <div class="follower-name">[[[followers_latest_user_name]]]</div>
</div>

<style>
  .follower-card {
    padding: 14px 22px;
    border-left: 3px solid #a684ff;
    background: rgba(0, 0, 0, 0.55);
    font-family: 'Albert Sans', sans-serif;
    color: #fff;
  }
  .follower-label {
    font-size: 13px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 0.7;
  }
  .follower-name {
    font-size: 30px;
    font-weight: 700;
  }
</style>
```

## The empty case

A brand new channel has no latest follower, and the tag renders as nothing. That leaves you with a card
saying "Latest follower" above a blank space, which looks broken rather than empty.

Wrap the whole card so it only exists when there is something to put in it:

```
[[[if:followers_latest_user_name]]]
  <div class="follower-card">
    <div class="follower-label">Latest follower</div>
    <div class="follower-name">[[[followers_latest_user_name]]]</div>
  </div>
[[[endif]]]
```

Now the card is absent until you have a follower, and appears the moment you do. Rendering nothing is
almost always better than rendering a dash or "N/A" - a placeholder is a thing your viewers have to
read and then discard.

If you would rather say something than show nothing, `??` supplies a fallback:

```
[[[followers_latest_user_name ?? nobody yet]]]
```

## Formatting the date

`[[[followers_latest_date]]]` is a timestamp. A pipe turns it into something a person reads:

```
[[[followers_latest_user_name]]] followed on [[[followers_latest_date|date:dd MMM yyyy]]]
```

Dates and numbers follow the locale on [your appearance settings](/settings/appearance), so this comes
out right for your audience without you doing anything.

## Static value or alert?

These are two different features and it is worth being deliberate about which one you want.

- **This tutorial builds a value.** It sits on screen permanently and quietly changes when someone new
  follows. Nobody watching necessarily notices the moment it happens.
- **An alert fires.** It appears, celebrates the specific person who just followed, and leaves.

Most channels want both, and they are best built together: an alert rendered inside your static
overlay's DOM can animate against the layout that is already there. [Overlays vs
Alerts](/help/overlays-vs-alerts) covers how they fit together, and [Testing your
alerts](/help/testing) shows how to fire a real follow event at your own account instead of waiting for
a stranger.

## Where next

- [Show your last 5 subscribers](/help/tutorials/last-five-subs) - the same idea, but a list
- [Conditional and Event Tags](/help/conditionals) - everything `[[[if:]]]` can test
- [Formatting Pipes](/help/formatting) - dates, numbers, currencies and durations
