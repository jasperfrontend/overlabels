---
title: Conditional Tags Reference
description: Complete reference for conditional template tags, event data, Ko-fi, StreamLabs, and Fourthwall integration tags in Overlabels overlays.
heading: Conditional Tags Reference
lead: Complete reference for conditional template tags, event data, Ko-fi, StreamLabs, and Fourthwall integration tags in Overlabels overlays.
canonical: https://overlabels.com/help/conditionals
context: templates.edit, templates.create, tags.generator
---

See your [static Template Tags](/settings/tags) for your account. Need to format numbers, durations, or
currencies? See [Formatting Pipes](/help/formatting).

## Conditional Template Syntax

Use conditional logic to dynamically show or hide content in your overlays based on real-time data. All
conditionals are processed client-side for security.

`if`, `elseif`, `else` and `endif` also work in a [bot command](/help/bot/commands#conditions) reply
and in an alert's text-to-speech and chat messages, with the same comparisons:
`[[[event.streak_months]]] month[[[if:event.streak_months != 1]]]s[[[endif]]]`. `foreach` is
overlay-only.

### Boolean Conditions

Test if a value exists and is truthy. Values considered false: `null`, `undefined`, `""`, `"false"`,
`"0"`.

```html
[[[if:channel_is_branded]]]
  <p>This stream is sponsored!</p>
[[[endif]]]
```

### Numerical Comparisons

Compare numbers using standard operators: `>`, `<`, `>=`, `<=`, `!=`, `=`.

```html
[[[if:followers_total >= 1000]]]
  <div>1K+ followers!</div>
[[[elseif:followers_total >= 100]]]
  <div>Growing strong with [[[followers_total]]] followers</div>
[[[else]]]
  <div>Help us reach 100 followers!</div>
[[[endif]]]
```

### String Comparisons

Compare text values using `=` and `!=` operators.

```html
[[[if:channel_language = en]]]
  <p>Welcome to our English stream!</p>
[[[elseif:channel_language = es]]]
  <p>¡Bienvenidos a nuestro stream en Español!</p>
[[[endif]]]
```

### Event-based Conditionals

Use event data in alert templates to create dynamic alerts based on donation/subscription amounts, viewer
counts, etc.

```html
[[[if:event.bits >= 1000]]]
  <div>HUGE CHEER! [[[event.user_name]]] donated [[[event.bits]]] bits!</div>
[[[elseif:event.bits >= 100]]]
  <div>Thanks [[[event.user_name]]] for [[[event.bits]]] bits!</div>
[[[else]]]
  <div>[[[event.user_name]]] cheered with [[[event.bits]]] bits!</div>
[[[endif]]]
```

### Nested Conditionals

You can nest conditionals up to 10 levels deep for complex logic.

```html
[[[if:event.tier = 3000]]]
  [[[if:event.total >= 10]]]
    <div>Tier 3 gift bomb! [[[event.total]]] subs!</div>
  [[[else]]]
    <div>Tier 3 gift: [[[event.total]]] subs</div>
  [[[endif]]]
[[[endif]]]
```

### Foreach Loops

Repeat a block of markup for every item in a list. Use this for poll choices, prediction outcomes,
hype-train contributors - anything where the server sends indexed entries (`event.choices.0.title`,
`event.choices.1.title`, ...) plus a `.count`.

Inside the loop body you can reference the current item through the alias you named after `as`, plus
these loop metadata tokens:

- `[[[loop.index]]]` - zero-based iteration index
- `[[[loop.first]]]` / `[[[loop.last]]]` - booleans, handy with `[[[if:...]]]`
- `[[[loop.count]]]` - total number of items

```html
<ul>
  [[[foreach:event.choices as choice]]]
    <li>
      [[[loop.index]]]. [[[choice.title]]] - [[[choice.votes]]] votes
    </li>
  [[[endforeach]]]
</ul>
```

Loops can be nested, and you can use `[[[if:...]]]` inside a loop body (as shown above). Non-scoped
tokens like `[[[event.title]]]` still work inside the body.

#### Iterable collections

These are the collections you can put on the right-hand side of `[[[foreach:X as Y]]]`. Event collections
follow Twitch's own limits. User-scope collections obey the caps on your
[Account settings page](/settings/account) (hard maximum 50 per loop).

| Iterable | Scope | Cap source | Use in alert or static? |
|---|---|---|---|
| `event.choices` | Poll alert | 5 (Twitch limit) | Alert |
| `event.outcomes` | Prediction alert | 10 (Twitch limit) | Alert |
| `event.top_contributions` | Hype train alert | 3 (fixed) | Alert |
| `subscribers` | User (channel) | Account settings (default 10) | Static |
| `goals` | User (channel) | Account settings (default 3) | Static |
| `channel_followers` | User (channel) | Account settings (default 5) | Static |
| `followed_channels` | User (channel) | Account settings (default 5) | Static |

Inside a loop, use `[[[alias.count]]]` on the iterable itself to get the total (untruncated) count. For
example, `[[[subscribers.count]]]` shows the real subscriber total even if your cap is 10.

#### Inspect a loop item with `[[[raw]]]`

Not sure what fields an iterable exposes? Drop `[[[raw]]]` inside the loop body and it will print the
current item as pretty-printed JSON. It's the fastest way to see the shape of anything you're iterating
over without guessing.

```html
[[[foreach:event.choices as choice]]]
  <pre>[[[raw]]]</pre>
[[[endforeach]]]
```

`[[[raw]]]` only works inside a `[[[foreach]]]` and always dumps the current iteration's item regardless
of the alias name. It's meant as a scaffolding tool - remove it from your finished template.

#### Fields available on each iteration item

Inside the loop body, reference any of these fields as `[[[alias.field]]]`, where `alias` is the name you
picked after `as`. Missing fields render as an empty string.

##### `event.choices` - poll choice

- `id` - stable choice id (good for `data-key`)
- `title` - choice label shown to voters
- `votes` - total votes on this choice
- `channel_points_votes` - votes cast with channel points
- `bits_votes` - votes cast with bits (deprecated by Twitch, still in payload)

Aggregates on the iterable itself: `event.choices.total_votes`,
`event.choices.total_channel_points_votes`, `event.choices.total_bits_votes`.

##### `event.outcomes` - prediction outcome

- `id` - stable outcome id
- `title` - outcome label
- `color` - `"blue"` or `"pink"` (Twitch's own colouring)
- `users` - number of predictors on this outcome
- `channel_points` - total channel points wagered on this outcome

Aggregates: `event.outcomes.total_users`, `event.outcomes.total_channel_points`. The winning outcome id
is `event.winning_outcome_id` on lock/end events.

##### `event.top_contributions` - hype train contributor

- `user_id`, `user_login`, `user_name` - the contributor
- `type` - `"bits"`, `"subscription"`, or `"other"`
- `total` - amount contributed in the unit implied by `type`

Capped at 3 items (fixed). For just the single latest contributor use
`event.last_contribution.user_name`, `event.last_contribution.type`, `event.last_contribution.total`.

##### `subscribers` - channel subscriber

- `user_id`, `user_login`, `user_name` - the subscriber
- `user_profile_image_url` - the subscriber's avatar (enriched from Helix)
- `broadcaster_id`, `broadcaster_login`, `broadcaster_name` - your channel
- `is_gift` - `true` if the sub was gifted
- `gifter_id`, `gifter_login`, `gifter_name` - empty string when `is_gift` is false
- `gifter_profile_image_url` - the gifter's avatar (enriched)
- `tier` - `"1000"`, `"2000"`, `"3000"`, or `"Prime"`
- `plan_name` - human-readable tier label (e.g. `"Tier 1"`)

##### `channel_followers` - someone who follows you

- `user_id`, `user_login`, `user_name` - the follower
- `followed_at` - ISO-8601 timestamp of when they followed
- `user_profile_image_url` - the follower's avatar (enriched from Helix)

##### `followed_channels` - a channel you follow

- `broadcaster_id`, `broadcaster_login`, `broadcaster_name` - the channel
- `followed_at` - ISO-8601 timestamp of when you followed
- `broadcaster_profile_image_url` - the channel's avatar (enriched from Helix)

##### `goals` - a channel goal

- `id` - stable goal id
- `broadcaster_id`, `broadcaster_login`, `broadcaster_name` - your channel
- `type` - one of `follower`, `subscription`, `subscription_count`, `new_subscription`,
  `new_subscription_count`
- `description` - the free-text label you set on Twitch
- `current_amount` - progress toward the goal
- `target_amount` - goal target
- `created_at` - ISO-8601 timestamp of when the goal was created

#### Animating loop items with `data-key`

Both static and alert overlays reconcile their rendered HTML via morphdom on every data update - when a
poll vote changes or a hype train contribution arrives, only the differences get patched instead of the
whole subtree being thrown away. That reconciliation is structural by default, which works fine for
content updates but means in-flight CSS transitions reset because the DOM nodes underneath can be
replaced between renders.

Add `data-key` to the repeated element and morphdom will reuse the same DOM node across renders whenever
the key matches. CSS transitions on that element then keep running smoothly:

```html
<ul>
  [[[foreach:event.choices as choice]]]
    <li data-key="[[[choice.id]]]" style="--bar-width: [[[choice.votes_pct]]]%">
      [[[choice.title]]] - [[[choice.votes]]] votes
    </li>
  [[[endforeach]]]
</ul>
```

With the `<li>` pinned by its `data-key`, a CSS rule like `transition: width 300ms ease-out` on a bar
inside the `<li>` will animate from the old width to the new one on every update. Without `data-key`,
morphdom may replace the node and the transition has no "from" state to interpolate from, so the bar
jumps.

`data-key` falls back to the element's `id` if no `data-key` is set, and finally to morphdom's positional
matching if neither is present - so older templates render identically to before. It's purely additive:
add it when you want smooth animations on repeated items.

> [!WARNING]
> **Gotcha:** pair `data-key` with CSS `transition`, not keyframe `animation`. Keyframe animations only
> fire once when a node mounts, so they won't re-trigger when the same DOM node's custom property
> changes. Transitions react to property changes and will.

## Event & Integration Tags

Tags available in alert templates, grouped by source.

BMAC, Ko-fi, StreamLabs, and Fourthwall all expose the same six core control keys. Pair
them with `latest()`/`oldest()` over the `_at` companion timestamps to pick the most recent supporter
across every connected service.

### Twitch - Basic

#### Channel Follow

Event type: `channel.follow`

When someone follows your channel

**User Information**

| Tag | Description |
|---|---|
| `[[[event.user_id]]]` | Follower's Twitch ID |
| `[[[event.user_login]]]` | Follower's username |
| `[[[event.user_name]]]` | Follower's display name |

**Event Data**

| Tag | Description |
|---|---|
| `[[[event.followed_at]]]` | Timestamp when followed |
| `[[[event.broadcaster_user_name]]]` | Your display name |

#### Channel Subscribe

Event type: `channel.subscribe`

When someone subscribes to your channel

**User Information**

| Tag | Description |
|---|---|
| `[[[event.user_id]]]` | Subscriber's Twitch ID |
| `[[[event.user_login]]]` | Subscriber's username |
| `[[[event.user_name]]]` | Subscriber's display name |

**Subscription Data**

| Tag | Description |
|---|---|
| `[[[event.tier]]]` | Sub tier (1000, 2000, 3000) *(prefer the display variant)* |
| `[[[event.tier_display]]]` | Sub display (1, 2, 3) **(preferred)** |
| `[[[event.is_gift]]]` | true/false if gifted |

#### Subscription Gifts

Event type: `channel.subscription.gift`

When someone gifts subscriptions

**User Information**

| Tag | Description |
|---|---|
| `[[[event.user_id]]]` | Gifter's Twitch ID |
| `[[[event.user_login]]]` | Gifter's username |
| `[[[event.user_name]]]` | Gifter's display name |

**Gift Data**

| Tag | Description |
|---|---|
| `[[[event.total]]]` | Number of subs gifted |
| `[[[event.tier]]]` | Sub tier (1000, 2000, 3000) *(prefer the display variant)* |
| `[[[event.tier_display]]]` | Sub display (1, 2, 3) **(preferred)** |
| `[[[event.cumulative_total]]]` | Total gifts ever |
| `[[[event.is_anonymous]]]` | true/false if anonymous |

#### Subscription Messages

Event type: `channel.subscription.message`

When someone resubscribes with a message

**User Information**

| Tag | Description |
|---|---|
| `[[[event.user_name]]]` | Subscriber's display name |
| `[[[event.tier]]]` | Sub tier (1000, 2000, 3000) *(prefer the display variant)* |
| `[[[event.tier_display]]]` | Sub display (1, 2, 3) **(preferred)** |

**Subscription Data**

| Tag | Description |
|---|---|
| `[[[event.cumulative_months]]]` | Total months subbed |
| `[[[event.streak_months]]]` | Current streak |
| `[[[event.duration_months]]]` | Months in this sub |
| `[[[event.message.text]]]` | The resub message |

#### Channel Cheer

Event type: `channel.cheer`

When someone cheers bits

**User Information**

| Tag | Description |
|---|---|
| `[[[event.user_id]]]` | Cheerer's Twitch ID |
| `[[[event.user_login]]]` | Cheerer's username |
| `[[[event.user_name]]]` | Cheerer's display name |

**Cheer Data**

| Tag | Description |
|---|---|
| `[[[event.bits]]]` | Number of bits cheered |
| `[[[event.message]]]` | Cheer message |
| `[[[event.is_anonymous]]]` | true/false if anonymous |

```html
[[[if:event.bits >= 1000]]]HUGE CHEER![[[endif]]] [[[event.user_name]]] cheered [[[event.bits]]] bits!
```

#### Channel Raid

Event type: `channel.raid`

When another streamer raids your channel

**Raider Information**

| Tag | Description |
|---|---|
| `[[[event.from_broadcaster_user_id]]]` | Raider's ID |
| `[[[event.from_broadcaster_user_login]]]` | Raider's username |
| `[[[event.from_broadcaster_user_name]]]` | Raider's name |

**Raid Data**

| Tag | Description |
|---|---|
| `[[[event.viewers]]]` | Number of viewers in raid |
| `[[[event.to_broadcaster_user_name]]]` | Your name |

#### Channel Points Redemption

Event type: `channel.channel_points_custom_reward_redemption.add`

When someone redeems a channel points reward

**User Information**

| Tag | Description |
|---|---|
| `[[[event.user_id]]]` | Redeemer's Twitch ID |
| `[[[event.user_login]]]` | Redeemer's username |
| `[[[event.user_name]]]` | Redeemer's display name |
| `[[[event.user_input]]]` | User's input text |

**Reward Data**

| Tag | Description |
|---|---|
| `[[[event.reward.title]]]` | Reward name |
| `[[[event.reward.cost]]]` | Point cost |
| `[[[event.reward.prompt]]]` | Reward description |
| `[[[event.status]]]` | Fulfillment status |
| `[[[event.redeemed_at]]]` | Timestamp |

### Twitch - Stream

#### Stream Online

Event type: `stream.online`

When your stream goes live

**Stream Information**

| Tag | Description |
|---|---|
| `[[[event.id]]]` | Stream ID |
| `[[[event.type]]]` | Stream type (usually "live") |
| `[[[event.started_at]]]` | Stream start timestamp |

> [!NOTE]
> Useful for logging but viewers probably will not see live alerts since the stream just started.

#### Stream Offline

Event type: `stream.offline`

When your stream goes offline

**Stream Information**

| Tag | Description |
|---|---|
| `[[[event.broadcaster_user_id]]]` | Your Twitch ID |
| `[[[event.broadcaster_user_login]]]` | Your username |
| `[[[event.broadcaster_user_name]]]` | Your display name |

> [!WARNING]
> Useful for logging but viewers will not see alerts since the stream went offline.

#### Stream Info Updated

Event type: `channel.update`

When the title, category, or content labels change mid-stream

**Channel Information**

| Tag | Description |
|---|---|
| `[[[event.broadcaster_user_id]]]` | Your Twitch ID |
| `[[[event.broadcaster_user_login]]]` | Your username |
| `[[[event.broadcaster_user_name]]]` | Your display name |

**Updated Fields**

| Tag | Description |
|---|---|
| `[[[event.title]]]` | New stream title |
| `[[[event.language]]]` | Language code (e.g. "en") |
| `[[[event.category_id]]]` | New category/game ID |
| `[[[event.category_name]]]` | New category/game name |

```html
[[[if:event.category_name]]]
  <div class="now-playing">Now playing: [[[event.category_name]]]</div>
[[[endif]]]
```

### Twitch - Hype Train

#### Hype Train Started

Event type: `channel.hype_train.begin`

A hype train kicks off on your channel

**Train State**

| Tag | Description |
|---|---|
| `[[[event.level]]]` | Starting level (usually 1) |
| `[[[event.total]]]` | Total points contributed so far |
| `[[[event.progress]]]` | Progress toward next level |
| `[[[event.goal]]]` | Points needed for next level |
| `[[[event.started_at]]]` | When the train started |
| `[[[event.expires_at]]]` | When the train expires unless contributed to |

**Top & Last Contributor**

| Tag | Description |
|---|---|
| `[[[event.last_contribution.user_name]]]` | Most recent contributor |
| `[[[event.last_contribution.type]]]` | "bits", "subscription", or "other" |
| `[[[event.last_contribution.total]]]` | Their contribution amount |
| `[[[event.top_contributions.count]]]` | How many top contributors are listed |
| `[[[event.top_contributions.0.user_name]]]` | #1 contributor name |
| `[[[event.top_contributions.0.type]]]` | #1 contribution type |
| `[[[event.top_contributions.0.total]]]` | #1 contribution total |

#### Hype Train Progress

Event type: `channel.hype_train.progress`

A new contribution lands during an active train - fires frequently, budget for spam

**Train State**

| Tag | Description |
|---|---|
| `[[[event.level]]]` | Current level |
| `[[[event.total]]]` | Total points contributed so far |
| `[[[event.progress]]]` | Progress toward next level |
| `[[[event.goal]]]` | Points needed for next level |
| `[[[event.expires_at]]]` | When the train expires |

**Top & Last Contributor**

| Tag | Description |
|---|---|
| `[[[event.last_contribution.user_name]]]` | Who just contributed |
| `[[[event.last_contribution.type]]]` | "bits", "subscription", or "other" |
| `[[[event.last_contribution.total]]]` | Their contribution amount |
| `[[[event.top_contributions.0.user_name]]]` | #1 contributor (also .1 and .2) |
| `[[[event.top_contributions.0.total]]]` | #1 contribution total |

```html
<div class="hype-progress">
  Level [[[event.level]]] - [[[event.progress]]] / [[[event.goal]]]
  [[[if:event.last_contribution.user_name]]]
    <small>Last: [[[event.last_contribution.user_name]]]</small>
  [[[endif]]]
</div>
```

#### Hype Train Ended

Event type: `channel.hype_train.end`

The train finished - use the final level + top contributors for the "thanks" beat

**Final State**

| Tag | Description |
|---|---|
| `[[[event.level]]]` | Final level reached |
| `[[[event.total]]]` | Final total contributed |
| `[[[event.started_at]]]` | When the train started |
| `[[[event.ended_at]]]` | When it ended |
| `[[[event.cooldown_ends_at]]]` | When the next train can start |

**Top Contributors**

| Tag | Description |
|---|---|
| `[[[event.top_contributions.count]]]` | How many contributors are listed |
| `[[[event.top_contributions.0.user_name]]]` | #1 contributor name |
| `[[[event.top_contributions.0.type]]]` | #1 contribution type |
| `[[[event.top_contributions.0.total]]]` | #1 contribution total |
| `[[[event.top_contributions.1.user_name]]]` | #2 contributor |
| `[[[event.top_contributions.2.user_name]]]` | #3 contributor |

### Twitch - Charity

#### Charity Donation

Event type: `channel.charity_campaign.donate`

A viewer donated to the active charity campaign

**Donor & Campaign**

| Tag | Description |
|---|---|
| `[[[event.user_name]]]` | Donor's display name |
| `[[[event.user_login]]]` | Donor's username |
| `[[[event.charity_name]]]` | Charity being donated to |
| `[[[event.charity_description]]]` | Charity description |
| `[[[event.charity_logo]]]` | Charity logo URL |
| `[[[event.charity_website]]]` | Charity website URL |

**Amount**

| Tag | Description |
|---|---|
| `[[[event.amount.formatted]]]` | Ready-to-display string (e.g. "$15.23") **(preferred)** |
| `[[[event.amount.value]]]` | Raw minor units (1523 = $15.23) |
| `[[[event.amount.decimal_places]]]` | Decimal places (usually 2) |
| `[[[event.amount.currency]]]` | Currency code ("USD", "EUR", etc.) |

```html
<div class="charity-donation">
  [[[event.user_name]]] donated [[[event.amount.formatted]]] to [[[event.charity_name]]]!
</div>
```

#### Charity Campaign Started

Event type: `channel.charity_campaign.start`

A charity campaign begins on your channel

**Campaign**

| Tag | Description |
|---|---|
| `[[[event.charity_name]]]` | Charity being raised for |
| `[[[event.charity_description]]]` | Charity description |
| `[[[event.charity_logo]]]` | Charity logo URL |
| `[[[event.charity_website]]]` | Charity website URL |
| `[[[event.started_at]]]` | When the campaign began |

**Goal**

| Tag | Description |
|---|---|
| `[[[event.target_amount.formatted]]]` | Fundraising target (formatted) **(preferred)** |
| `[[[event.target_amount.value]]]` | Target in minor units |
| `[[[event.target_amount.currency]]]` | Currency code |
| `[[[event.current_amount.formatted]]]` | Raised so far (formatted) |

#### Charity Campaign Progress

Event type: `channel.charity_campaign.progress`

Current vs. target update - fires on every donation, budget for spam

**Campaign**

| Tag | Description |
|---|---|
| `[[[event.charity_name]]]` | Charity name |
| `[[[event.charity_logo]]]` | Charity logo URL |

**Progress**

| Tag | Description |
|---|---|
| `[[[event.current_amount.formatted]]]` | Raised so far (formatted) **(preferred)** |
| `[[[event.current_amount.value]]]` | Raised in minor units |
| `[[[event.target_amount.formatted]]]` | Target (formatted) |
| `[[[event.target_amount.value]]]` | Target in minor units |
| `[[[event.target_amount.currency]]]` | Currency code |

```html
<div class="charity-progress">
  [[[event.current_amount.formatted]]] raised of [[[event.target_amount.formatted]]]
</div>
```

#### Charity Campaign Ended

Event type: `channel.charity_campaign.stop`

The campaign wrapped up - use the final totals for a thank-you alert

**Campaign**

| Tag | Description |
|---|---|
| `[[[event.charity_name]]]` | Charity name |
| `[[[event.charity_description]]]` | Charity description |
| `[[[event.charity_logo]]]` | Charity logo URL |
| `[[[event.stopped_at]]]` | When the campaign ended |

**Final Totals**

| Tag | Description |
|---|---|
| `[[[event.current_amount.formatted]]]` | Final amount raised (formatted) **(preferred)** |
| `[[[event.current_amount.value]]]` | Final amount in minor units |
| `[[[event.target_amount.formatted]]]` | Target (formatted) |

### Twitch - Goals

#### Channel Goal Started

Event type: `channel.goal.begin`

A follower, sub, or bits goal begins

**Goal**

| Tag | Description |
|---|---|
| `[[[event.type]]]` | "follower", "subscription", "subscription_count", "new_subscription", or "new_subscription_count" |
| `[[[event.description]]]` | Goal description (your custom text) |
| `[[[event.current_amount]]]` | Starting amount (where the goal begins from) |
| `[[[event.target_amount]]]` | Target to hit |
| `[[[event.started_at]]]` | When the goal started |

#### Channel Goal Progress

Event type: `channel.goal.progress`

Current amount updated - fires on every contribution, budget for spam

**Goal**

| Tag | Description |
|---|---|
| `[[[event.type]]]` | Goal type ("follower", "subscription", etc.) |
| `[[[event.description]]]` | Goal description |
| `[[[event.current_amount]]]` | Current value |
| `[[[event.target_amount]]]` | Target value |

```html
<div class="goal-bar">
  [[[event.description]]]: [[[event.current_amount]]] / [[[event.target_amount]]]
</div>
```

#### Channel Goal Ended

Event type: `channel.goal.end`

Goal completed or expired - is_achieved tells you which

**Goal**

| Tag | Description |
|---|---|
| `[[[event.type]]]` | Goal type |
| `[[[event.description]]]` | Goal description |
| `[[[event.is_achieved]]]` | true if goal was hit, false if it expired |
| `[[[event.current_amount]]]` | Final value |
| `[[[event.target_amount]]]` | Target value |
| `[[[event.started_at]]]` | When the goal started |
| `[[[event.ended_at]]]` | When the goal ended |

```html
[[[if:event.is_achieved]]]
  <div class="goal-hit">[[[event.description]]] - HIT!</div>
[[[else]]]
  <div class="goal-miss">[[[event.description]]] ended at [[[event.current_amount]]] / [[[event.target_amount]]]</div>
[[[endif]]]
```

### Twitch - Polls

#### Poll Started

Event type: `channel.poll.begin`

A poll opens with up to 5 choices

**Poll**

| Tag | Description |
|---|---|
| `[[[event.title]]]` | Poll question |
| `[[[event.started_at]]]` | When the poll opened |
| `[[[event.ends_at]]]` | When the poll closes |

**Choices & Voting**

| Tag | Description |
|---|---|
| `[[[event.choices.count]]]` | How many choices (max 5) |
| `[[[event.choices.0.title]]]` | First choice title (also .1 to .4) |
| `[[[event.channel_points_voting.is_enabled]]]` | true if channel points can vote |
| `[[[event.channel_points_voting.amount_per_vote]]]` | Points per channel-points vote |
| `[[[event.bits_voting.is_enabled]]]` | true if bits can vote (legacy) |

#### Poll Progress

Event type: `channel.poll.progress`

Mid-poll vote count update - fires frequently as votes come in

**Poll**

| Tag | Description |
|---|---|
| `[[[event.title]]]` | Poll question |
| `[[[event.ends_at]]]` | When the poll closes |

**Choices**

| Tag | Description |
|---|---|
| `[[[event.choices.count]]]` | How many choices |
| `[[[event.choices.total_votes]]]` | Total votes across all choices (use as denominator for progress bars) |
| `[[[event.choices.total_channel_points_votes]]]` | Total channel-points votes across all choices |
| `[[[event.choices.0.title]]]` | First choice title |
| `[[[event.choices.0.votes]]]` | First choice total votes |
| `[[[event.choices.0.channel_points_votes]]]` | Channel-points votes for #0 |
| `[[[event.choices.1.title]]]` | Second choice title (also .2 to .4) |
| `[[[event.choices.1.votes]]]` | Second choice votes |

#### Poll Ended

Event type: `channel.poll.end`

Final results - status tells you if it completed naturally or was cut short

**Poll**

| Tag | Description |
|---|---|
| `[[[event.title]]]` | Poll question |
| `[[[event.status]]]` | "completed", "terminated", or "archived" |
| `[[[event.started_at]]]` | When the poll opened |
| `[[[event.ended_at]]]` | When the poll ended |

**Final Choices**

| Tag | Description |
|---|---|
| `[[[event.choices.count]]]` | How many choices |
| `[[[event.choices.total_votes]]]` | Final total votes across all choices |
| `[[[event.choices.0.title]]]` | First choice title |
| `[[[event.choices.0.votes]]]` | First choice final vote count |
| `[[[event.choices.1.title]]]` | Second choice title (also .2 to .4) |
| `[[[event.choices.1.votes]]]` | Second choice final votes |

```html
[[[if:event.status = completed]]]
  <div class="poll-done">Poll ended: [[[event.title]]]</div>
[[[elseif:event.status = terminated]]]
  <div class="poll-cut">Poll cut short: [[[event.title]]]</div>
[[[endif]]]
```

### Twitch - Predictions

#### Prediction Started

Event type: `channel.prediction.begin`

A prediction opens with up to 10 outcomes

**Prediction**

| Tag | Description |
|---|---|
| `[[[event.title]]]` | Prediction question |
| `[[[event.started_at]]]` | When it opened |
| `[[[event.locks_at]]]` | When predictions close |

**Outcomes**

| Tag | Description |
|---|---|
| `[[[event.outcomes.count]]]` | How many outcomes (max 10) |
| `[[[event.outcomes.0.title]]]` | First outcome title (also .1 to .9) |
| `[[[event.outcomes.0.color]]]` | "blue" or "pink" |

#### Prediction Progress

Event type: `channel.prediction.progress`

Update with current predictor counts - fires frequently

**Prediction**

| Tag | Description |
|---|---|
| `[[[event.title]]]` | Prediction question |
| `[[[event.locks_at]]]` | When predictions close |

**Outcomes**

| Tag | Description |
|---|---|
| `[[[event.outcomes.count]]]` | How many outcomes |
| `[[[event.outcomes.total_users]]]` | Total predictors across all outcomes |
| `[[[event.outcomes.total_channel_points]]]` | Total channel points wagered across all outcomes |
| `[[[event.outcomes.0.title]]]` | First outcome title |
| `[[[event.outcomes.0.color]]]` | "blue" or "pink" |
| `[[[event.outcomes.0.users]]]` | Number of predictors on #0 |
| `[[[event.outcomes.0.channel_points]]]` | Total channel points on #0 |
| `[[[event.outcomes.1.title]]]` | Second outcome title (also .2 to .9) |
| `[[[event.outcomes.1.users]]]` | Predictors on #1 |

#### Prediction Locked

Event type: `channel.prediction.lock`

Predictions close - waiting for the streamer to resolve

**Prediction**

| Tag | Description |
|---|---|
| `[[[event.title]]]` | Prediction question |
| `[[[event.locked_at]]]` | When it locked |

**Final Outcomes**

| Tag | Description |
|---|---|
| `[[[event.outcomes.count]]]` | How many outcomes |
| `[[[event.outcomes.total_users]]]` | Total predictors across all outcomes |
| `[[[event.outcomes.total_channel_points]]]` | Total channel points wagered across all outcomes |
| `[[[event.outcomes.0.title]]]` | First outcome title |
| `[[[event.outcomes.0.users]]]` | Final predictor count on #0 |
| `[[[event.outcomes.0.channel_points]]]` | Final channel points on #0 |
| `[[[event.outcomes.1.title]]]` | Second outcome title (also .2 to .9) |

#### Prediction Ended

Event type: `channel.prediction.end`

Winning outcome + payouts - or canceled if refunded

**Prediction**

| Tag | Description |
|---|---|
| `[[[event.title]]]` | Prediction question |
| `[[[event.status]]]` | "resolved" or "canceled" |
| `[[[event.winning_outcome_id]]]` | ID of the winning outcome (resolved only) |
| `[[[event.started_at]]]` | When it opened |
| `[[[event.ended_at]]]` | When it ended |

**Final Outcomes**

| Tag | Description |
|---|---|
| `[[[event.outcomes.count]]]` | How many outcomes |
| `[[[event.outcomes.total_users]]]` | Final total predictors across all outcomes |
| `[[[event.outcomes.total_channel_points]]]` | Final total channel points wagered |
| `[[[event.outcomes.0.title]]]` | First outcome title |
| `[[[event.outcomes.0.users]]]` | Final predictor count on #0 |
| `[[[event.outcomes.0.channel_points]]]` | Final channel points on #0 |
| `[[[event.outcomes.1.title]]]` | Second outcome title (also .2 to .9) |

```html
[[[if:event.status = resolved]]]
  <div class="prediction-resolved">Winner: [[[event.outcomes.0.title]]]</div>
[[[elseif:event.status = canceled]]]
  <div class="prediction-canceled">Prediction canceled - refunded</div>
[[[endif]]]
```

### Ko-fi

#### Ko-fi Auto-provisioned Controls

Six controls are created on connect and kept up to date with every donation, subscription, shop order, or commission

**Use in any template with the [[[c:kofi:key]]] syntax**

| Tag | Description |
|---|---|
| `[[[c:kofi:donations_received]]]` | Total count of Ko-fi events received (counter) |
| `[[[c:kofi:latest_donor_name]]]` | Name of the most recent supporter |
| `[[[c:kofi:latest_donation_amount]]]` | Amount of the most recent payment |
| `[[[c:kofi:latest_donation_message]]]` | Message from the most recent supporter |
| `[[[c:kofi:latest_donation_currency]]]` | Currency of the most recent payment (e.g. USD) |
| `[[[c:kofi:total_received]]]` | Running total of all Ko-fi amounts (session) |

> [!NOTE]
> Ko-fi, StreamLabs, and Fourthwall share a unified control schema - the six keys are identical across all three integrations, so you can swap the prefix (c:kofi:, c:streamlabs:, c:fourthwall:) and the template keeps working.

#### All Ko-fi Events

Available on every Ko-fi event type (donation, subscription, shop_order, commission)

**Common Tags**

| Tag | Description |
|---|---|
| `[[[event.from_name]]]` | Name of the supporter |
| `[[[event.source]]]` | Display name of the platform (e.g. "Ko-fi") - useful for reusing templates across donation services |
| `[[[event.type]]]` | Normalized type: donation, subscription, shop_order, or commission |
| `[[[event.transaction_id]]]` | Unique Ko-fi transaction ID |
| `[[[event.url]]]` | Supporter's Ko-fi page URL |

#### Ko-fi Donation & Subscription Events

Additional tags available for donation and subscription events

**Payment Tags**

| Tag | Description |
|---|---|
| `[[[event.message]]]` | Supporter's message |
| `[[[event.amount]]]` | Amount as a string (e.g. "5.00") |
| `[[[event.currency]]]` | Currency code (e.g. "USD") |

```html
<div class="donor">[[[event.from_name]]] donated [[[event.amount]]] [[[event.currency]]]!</div>
<div class="message">[[[if:event.message]]][[[event.message]]][[[endif]]]</div>
```

#### Ko-fi Subscription-Only Tags

Extra tags exclusive to Ko-fi subscription events

**Subscription Tags**

| Tag | Description |
|---|---|
| `[[[event.tier_name]]]` | Subscription tier name |
| `[[[event.is_first_sub]]]` | "1" if first payment, "0" otherwise |
| `[[[event.is_subscription]]]` | Always "1" for subscription events |

### StreamLabs

#### StreamLabs Auto-provisioned Controls

Six controls are created on connect and kept up to date with every donation

**Use in any template with the [[[c:streamlabs:key]]] syntax**

| Tag | Description |
|---|---|
| `[[[c:streamlabs:donations_received]]]` | Total number of donations received (counter) |
| `[[[c:streamlabs:latest_donor_name]]]` | Name of the most recent donor |
| `[[[c:streamlabs:latest_donation_amount]]]` | Amount of the most recent donation |
| `[[[c:streamlabs:latest_donation_message]]]` | Message from the most recent donor |
| `[[[c:streamlabs:latest_donation_currency]]]` | Currency of the most recent donation (e.g. USD) |
| `[[[c:streamlabs:total_received]]]` | Running total of all donation amounts (session) |

> [!NOTE]
> StreamLabs, Ko-fi, and Fourthwall share a unified control schema - the six keys are identical across all three integrations, so you can swap the prefix (c:streamlabs:, c:kofi:, c:fourthwall:) and the template keeps working.

#### StreamLabs Donation Event Tags

Available in alert templates triggered by StreamLabs donations

**Event Tags**

| Tag | Description |
|---|---|
| `[[[event.from_name]]]` | Name of the donor |
| `[[[event.message]]]` | Donor's message |
| `[[[event.amount]]]` | Donation amount (e.g. "5.00") |
| `[[[event.currency]]]` | Currency code (e.g. "USD") |
| `[[[event.formatted_amount]]]` | Formatted amount (e.g. "$5.00") |
| `[[[event.type]]]` | Always "donation" |
| `[[[event.source]]]` | Always "StreamLabs" - useful for reusing alert templates across donation services |
| `[[[event.transaction_id]]]` | Unique event identifier |

```html
<div class="donation">
  [[[event.from_name]]] donated [[[event.formatted_amount]]]!
  [[[if:event.message]]]
    <p class="message">[[[event.message]]]</p>
  [[[endif]]]
</div>
```

### Fourthwall

#### Fourthwall Auto-provisioned Controls

Six controls are created on connect and kept up to date with every donation

**Use in any template with the [[[c:fourthwall:key]]] syntax**

| Tag | Description |
|---|---|
| `[[[c:fourthwall:donations_received]]]` | Total number of donations received (counter) |
| `[[[c:fourthwall:latest_donor_name]]]` | Name of the most recent donor |
| `[[[c:fourthwall:latest_donation_amount]]]` | Amount of the most recent donation |
| `[[[c:fourthwall:latest_donation_message]]]` | Message from the most recent donor |
| `[[[c:fourthwall:latest_donation_currency]]]` | Currency of the most recent donation (e.g. USD) |
| `[[[c:fourthwall:total_received]]]` | Running total of all donation amounts (session) |

> [!NOTE]
> Fourthwall, Ko-fi, and StreamLabs share a unified control schema - the six keys are identical across all three integrations, so you can swap the prefix (c:fourthwall:, c:kofi:, c:streamlabs:) and the template keeps working.

#### Fourthwall Donation Event Tags

Available in alert templates triggered by Fourthwall donations

**Event Tags**

| Tag | Description |
|---|---|
| `[[[event.from_name]]]` | Name of the donor |
| `[[[event.message]]]` | Donor's message |
| `[[[event.amount]]]` | Donation amount (e.g. "10") |
| `[[[event.currency]]]` | Currency code (e.g. "USD") |
| `[[[event.type]]]` | Always "donation" |
| `[[[event.source]]]` | Always "Fourthwall" - useful for reusing alert templates across donation services |
| `[[[event.status]]]` | Donation lifecycle state (e.g. "OPEN") - Fourthwall-specific |
| `[[[event.transaction_id]]]` | Unique donation identifier (e.g. don_...) |

```html
<div class="donation">
  [[[event.from_name]]] donated [[[event.amount]]] [[[event.currency]]]!
  [[[if:event.message]]]
    <p class="message">[[[event.message]]]</p>
  [[[endif]]]
</div>
```

### Buy Me a Coffee

#### Buy Me a Coffee Auto-provisioned Controls

Seven controls track every BMAC event - donations, commissions, extras, memberships, monthly support, and wishlist payments

**Use in any template with the [[[c:bmac:key]]] syntax**

| Tag | Description |
|---|---|
| `[[[c:bmac:donations_received]]]` | Total count of BMAC events received (counter, increments on every event type) |
| `[[[c:bmac:latest_donor_name]]]` | Name of the most recent supporter |
| `[[[c:bmac:latest_donation_amount]]]` | Top-level amount paid for the most recent event (includes shipping/extras for orders) |
| `[[[c:bmac:latest_donation_message]]]` | Supporter's note - empty when supporter chose to keep it private (note_hidden) |
| `[[[c:bmac:latest_donation_currency]]]` | Currency of the most recent payment (e.g. USD) |
| `[[[c:bmac:total_received]]]` | Running total of every BMAC payment (session) |
| `[[[c:bmac:latest_support_type]]]` | Type of the most recent support: Supporter, Commission, Extra, Membership, Subscription, or Wishlist |

> [!NOTE]
> BMAC shares the same six core control keys as Ko-fi, StreamLabs, and Fourthwall. Swap the prefix (c:kofi:, c:bmac:, etc.) and the same template renders for all four integrations. Use latest()/oldest() over the _at companion timestamps to pick the most recent supporter across services.

#### All BMAC Events

Available on every BMAC event (donation, commission, extra, membership, recurring, wishlist)

**Common Tags**

| Tag | Description |
|---|---|
| `[[[event.from_name]]]` | Name of the supporter (data.supporter_name) |
| `[[[event.source]]]` | Always "Buy Me a Coffee" - useful for reusing templates across donation services |
| `[[[event.type]]]` | Normalized type: donation, commission, extra, membership, recurring, or wishlist |
| `[[[event.support_type]]]` | Human label from BMAC (Supporter, Commission, Extra, Membership, Subscription, Wishlist) |
| `[[[event.transaction_id]]]` | BMAC transaction_id, or psp_id for memberships and monthly support |
| `[[[event.message]]]` | BMAC-generated description (e.g. "John bought you a coffee") |
| `[[[event.live_mode]]]` | "1" for live events, "0" for BMAC test mode |

#### BMAC Payment Tags

Money- and message-shaped tags emitted by every event type

**Payment Tags**

| Tag | Description |
|---|---|
| `[[[event.amount]]]` | Top-level amount as string (e.g. "5.00") - matches what BMAC reports on the dashboard |
| `[[[event.currency]]]` | Currency code (e.g. "USD") |
| `[[[event.support_note]]]` | Supporter's private note. Empty when note_hidden is true (memberships and monthly support) |
| `[[[event.coffee_count]]]` | Number of coffees purchased (donation events only) |
| `[[[event.commission_name]]]` | Commission product name (commission events only) |
| `[[[event.wishlist_title]]]` | Wishlist item title (wishlist events only) |
| `[[[event.extras_title]]]` | First extra purchased (extra events only) |

```html
<div class="donor">[[[event.from_name]]] sent [[[event.amount]]] [[[event.currency]]]!</div>
<div class="message">[[[if:event.support_note]]][[[event.support_note]]][[[endif]]]</div>
```

#### BMAC Recurring & Membership Tags

Distinguish one-off support from monthly support and membership tiers

**Recurring Tags**

| Tag | Description |
|---|---|
| `[[[event.is_recurring]]]` | "1" for membership and monthly support, "0" otherwise |
| `[[[event.is_membership]]]` | "1" only for membership events (use to read membership_level_name from raw payload) |

## Tips & Best Practices

### Use Meaningful Conditions

Create different alert styles based on the value: small donations vs large donations, new followers vs
milestone followers.

### Test Your Conditions

Use the [Twitch Testing Guide](/settings/testing) to test your alert templates with different event values to
ensure they work as expected. Be sure to install the [Twitch CLI](https://dev.twitch.tv/docs/cli/) first.

### Style Conditional Content

Apply different CSS classes within conditionals to create visual variety for different alert types.

### Copy the Starter Kit

[Copy the Overlabels Starter Kit](/kits/1) to get a great set of defaults to work with.

### High-frequency progress events

Hype train, charity, goal, poll, and prediction `*.progress` events can fire every few seconds during
active engagement. The overlay extends the current alert rather than restacking, so the UI stays calm -
but keep your templates lightweight for good measure.

### Speak HTML & CSS

Overlabels assumes you know your way around HTML, CSS, and a template engine.
