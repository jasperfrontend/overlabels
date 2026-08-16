---
title: Show your last 5 subscribers - Overlabels Tutorial
description: Render a list of your most recent subscribers with a foreach loop, including gifted subs, tiers and avatars.
heading: Show your last 5 subscribers
lead: A foreach loop turns your subscriber list into rows on screen. This one covers the parts that bite - how many you actually get, and what to do about gifted subs.
canonical: https://overlabels.com/help/tutorials/last-five-subs
---

## The loop

```
[[[foreach:subscribers as sub]]]
  <div class="sub-row">[[[sub.user_name]]]</div>
[[[endforeach]]]
```

`sub` is a name you picked. Use the same one inside the loop.

## Getting five, not all of them

The loop renders every subscriber it is given, so the number on screen is a setting, not something you
write in the template. Set **Foreach caps** on [your account settings](/settings/account) to `5` and the
list arrives with five in it.

This is the part people get wrong: there is no `foreach:subscribers limit 5`. The cap is applied
server-side before the overlay ever sees the array, which is also why raising it costs you nothing in
the template.

> [!TIP]
> Caps are per iterable, so subscribers, followers and chat each get their own. Set subscribers to 5 and
> chat to 50 and they do not interfere.

## A row worth looking at

Each subscriber carries more than a name:

| Field | Holds |
|---|---|
| `user_name` | Display name |
| `user_login` | Lowercase login - use this for comparisons |
| `user_profile_image_url` | Their avatar |
| `tier` | `1000`, `2000`, `3000` or `Prime` |
| `plan_name` | Readable label, like "Tier 1" |
| `is_gift` | True when the sub was gifted |
| `gifter_name` | Who gifted it - empty when `is_gift` is false |

```html
[[[foreach:subscribers as sub]]]
  <div class="sub-row">
    <img class="sub-avatar" src="[[[sub.user_profile_image_url]]]" alt="" />
    <span class="sub-name">[[[sub.user_name]]]</span>
    <span class="sub-tier">[[[sub.plan_name]]]</span>
  </div>
[[[endforeach]]]

<style>
  .sub-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    font-family: 'Albert Sans', sans-serif;
    color: #fff;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8);
  }
  .sub-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
  }
  .sub-name {
    font-weight: 700;
  }
  .sub-tier {
    margin-left: auto;
    font-size: 13px;
    opacity: 0.75;
  }
</style>
```

## Crediting the gifter

A gifted sub has two people in it, and showing only the recipient quietly drops the one who paid:

```
[[[foreach:subscribers as sub]]]
  <div class="sub-row">
    <span class="sub-name">[[[sub.user_name]]]</span>
    [[[if:sub.is_gift]]]
      <span class="sub-gift">gifted by [[[sub.gifter_name]]]</span>
    [[[endif]]]
  </div>
[[[endforeach]]]
```

## Styling by tier

`[[[sub.tier]]]` is a value you can compare, so a Tier 3 sub can look different from a Prime one:

```
[[[foreach:subscribers as sub]]]
  [[[if:sub.tier = 3000]]]
    <div class="sub-row tier-3">[[[sub.user_name]]]</div>
  [[[elseif:sub.tier = Prime]]]
    <div class="sub-row tier-prime">[[[sub.user_name]]]</div>
  [[[else]]]
    <div class="sub-row">[[[sub.user_name]]]</div>
  [[[endif]]]
[[[endforeach]]]
```

You can also put the tier straight into a class and do the whole thing in CSS, which stays readable as
the list of cases grows:

```
<div class="sub-row tier-[[[sub.tier]]]">[[[sub.user_name]]]</div>
```

## When there are none

An empty list renders as nothing at all, which is usually what you want. If the surrounding heading
should disappear too, test the count first:

```
[[[if:subscribers_total]]]
  <h2>Recent subscribers</h2>
  [[[foreach:subscribers as sub]]]
    <div class="sub-row">[[[sub.user_name]]]</div>
  [[[endforeach]]]
[[[endif]]]
```

## Where next

- [Show your latest follower](/help/tutorials/latest-follower) - single values and the empty case
- [Show chat on screen](/help/tutorials/show-chat-on-screen) - the other big foreach loop
- [Conditional and Event Tags](/help/conditionals) - the full foreach and conditional reference
