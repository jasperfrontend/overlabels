---
title: How to Use Controls
description: Learn how to create, manage, and use Controls in your Twitch overlays. Counters, timers, toggles, and more - all updated live during your stream.
heading: How to Use Controls
lead: Learn how to create, manage, and use Controls in your Twitch overlays. Counters, timers, toggles, and more - all updated live during your stream.
canonical: https://overlabels.com/help/controls
context: settings.controls, controls.index
---

You can also use Controls in CSS. This opens up possibilities for dynamic styling, which is incredibly
powerful.

## What are Controls?

A Control is a named value that lives on your overlay or alert template. You define its key, type, and
optional label, and then reference it in your overlay HTML with the `[[[c:key]]]` syntax. During your
stream, you update its value from the **Control Panel** and the change appears in OBS within a few
seconds.

Controls are **overlay-scoped**: each overlay has its own set. They are never shared between overlays
unless you explicitly import them when copying.

| Type | What it is |
|---|---|
| `text` | Free-form text. Displayed as-is in your overlay. |
| `number` | A numeric value with optional min, max, and step. |
| `counter` | A whole-number counter with +/-/Reset buttons. Great for wins, rounds played, donations. |
| `timer` | A stopwatch, countdown, or count-to-date. Ticks in real time on the overlay. |
| `boolean` | An on/off toggle. Outputs `1` or `0`. |
| `datetime` | A fixed date and time value. |
| `expression` | A formula that derives its value from other controls. Evaluated live on the overlay. |
| `list writer` | Records another control's value to a List every time it changes. Works with any control type, including Expressions. |

## Control Types in Detail

### text

Free-form text displayed as-is in your overlay. HTML is stripped for safety, so you can't accidentally
inject markup through a Control Panel update.

Text controls are the most versatile type. Use them for player names, status messages, song titles, or
anything that doesn't need numeric logic.

```html
<div>
  Now playing: <span>[[[c:song_title]]]</span>
</div>
```

Text controls also work well for storing URLs (image sources, links, etc.) that you want to swap out
without editing overlay code.

### number

A numeric value with optional **min**, **max**, and **step** constraints. Saved and displayed as a plain
number. You type the value directly in the Control Panel.

Numbers are great for goal amounts, percentages, scores, or any value where you want to set it to a
specific number rather than increment/decrement.

```html
<div>
  <progress value="[[[c:goal_current]]]" max="[[[c:goal_target]]]"></progress>
  [[[c:goal_current]]] / [[[c:goal_target]]]
</div>
```

> [!NOTE]
> **Random mode:** Number controls can be set to "random mode" in the config. When enabled, the overlay
> generates a random integer between min and max on a configurable interval. Useful for slot machines,
> randomized choices, or whack-a-mole style games.

### counter

A whole-number counter with **+**, **-**, and **Reset** buttons in the Control Panel. Each press fires
immediately - no save button needed. Counters are the fastest way to track things that change during a
stream.

Configure a **step** size (default 1), **min/max** bounds, and a **reset value** (default 0).

```html
<div>
  Wins: <span>[[[c:wins]]]</span>
</div>

<!-- Combine with conditionals for reactive messaging -->
[[[if:c:wins >= 10]]]
  <div>On a tear tonight!</div>
[[[endif]]]
```

> [!NOTE]
> **Random mode:** Like Number controls, Counters also support random mode for generating random values
> on an interval.

### timer

A live-ticking timer that runs on the overlay in real time. Control it from the Control Panel with Start,
Stop, and Reset buttons. Timer controls support three modes:

| Mode | Behaviour |
|---|---|
| Count up | Counts upward from zero. A classic stopwatch. |
| Countdown | Counts down from a base duration you set (in seconds). Stops at zero. |
| Count to | Counts down to a specific date and time. Always ticking - no start/stop needed. |

The raw output is **seconds**. Use [formatting pipes](/help/formatting) to display it as a clock:

```html
<!-- Shows "02:34:15" -->
<span>[[[c:stream_timer|duration:hh:mm:ss]]]</span>

<!-- Shows "4:15" (minutes and seconds only) -->
<span>[[[c:round_timer|duration:mm:ss]]]</span>

<!-- Auto-format picks the smartest display -->
<span>[[[c:stream_timer|duration]]]</span>
```

#### Detecting if a timer is running

Every timer exposes a companion `:running` value that outputs `1` when the timer is active or `0` when it
is stopped. "Count to" timers are always considered running since they tick continuously.

Use it as a tag or in conditionals to show or hide content based on timer state:

```html
<!-- Show a pulsing dot when the timer is live -->
[[[if:c:stream_timer:running]]]
  <span></span> LIVE
[[[else]]]
  <span>PAUSED</span>
[[[endif]]]

<!-- Only show the timer block while it's running -->
[[[if:c:round_timer:running]]]
  <div>
    <span>[[[c:round_timer|duration:mm:ss]]]</span>
  </div>
[[[endif]]]
```

The `:running` value updates instantly when you press Start or Stop in the Control Panel.

### boolean

An on/off toggle switch. Stores `1` (on) or `0` (off). In the Control Panel, it shows as a simple toggle
you can flip instantly.

Booleans are ideal for conditionally showing or hiding entire sections of your overlay without touching
the code.

```html
<!-- Toggle a sponsor banner on/off -->
[[[if:c:show_sponsor]]]
  <div>
    <img src="[[[c:sponsor_logo]]]" />
  </div>
[[[endif]]]

<!-- Toggle between two layouts -->
[[[if:c:compact_mode]]]
  <div>...</div>
[[[else]]]
  <div>...</div>
[[[endif]]]
```

### datetime

A fixed date and time value, set from a datetime picker in the Control Panel. Useful for "next stream
starts at" displays, event countdowns, or logging purposes.

```html
<div>
  Next stream: <span>[[[c:next_stream|date:short]]]</span>
</div>
```

Use [formatting pipes](/help/formatting) like `|date:short` or `|date:long` to format the output. If you
need a live countdown to a date, use a Timer in "count to" mode instead.

### expression

A formula that derives its value from other controls. Expressions are evaluated live on the overlay with
zero latency - no server round-trip needed. You cannot edit an expression's value directly; it's always
computed from its formula.

Reference other controls using `c.key` syntax inside the formula. For service-managed controls, use
`c.kofi.total_received` (dots instead of colons).

```
// Simple math
Expression: c.wins / (c.wins + c.losses) * 100
<div>Win rate: [[[c:win_rate|round]]]%</div>

// Ternary / if-else logic
Expression: c.wins >= 10 ? "on_fire" : "warming_up"
<div>Mood: [[[c:mood]]]</div>

// Cross-service total
Expression: c.streamlabs.total_received + c.kofi.total_received
<div>Total donations: $[[[c:total_donations|round]]]</div>

// Latest donor across all services
Expression: latest(
  c.streamlabs.latest_donor_name_at, c.streamlabs.latest_donor_name,
  c.kofi.latest_donor_name_at, c.kofi.latest_donor_name
)
<div>Latest donor: [[[c:latest_donor]]]</div>

// Seconds since last donation
Expression: now() - max(c.kofi.latest_donor_at, c.streamlabs.latest_donor_at)
<div>Last donation: [[[c:since_last_donation|duration:mm:ss]]] ago</div>
```

Expressions support standard math operators (`+ - * / %`), comparisons, ternary operators, and
parentheses. Circular dependencies (A depends on B, B depends on A) are detected and blocked when you
save.

#### Available functions

**`latest()` `oldest()` `argmax()` `argmin()`** - accept pairs of `value, label` arguments. Return the
label paired with the highest (`latest` / `argmax`) or lowest (`oldest` / `argmin`) value. Works with
numbers and timestamps. First pair wins on ties.

**`max()` `min()` `sum()` `avg()` `abs()` `round()` `floor()` `ceil()`** - standard math functions.
`max`, `min`, `sum`, and `avg` accept multiple arguments. `round` takes an optional decimals count:
`round(0.1 + 0.2, 2)` returns the string `"0.30"` (trailing zero preserved, matching the
[|round:2 pipe](/help/formatting)). Because the 2-arg form returns a string, put it at the end of the
expression; further math after it concatenates instead of adding.

**`sin()` `cos()` `fract()` `mod()` `PI`** - animation-friendly helpers. `sin` and `cos` take radians.
`fract(x)` returns the fractional part (`x - floor(x)`, so `fract(-0.3) === 0.7`). `mod(a, b)` is
floor-based modulo (GLSL-style), so `mod(-1, 5) === 4`. Use the `%` operator if you want JS remainder
(`-1 % 5 === -1`). `PI` is a bare identifier - use `PI`, not `PI()`.

Heads up on float precision: `fract(10.2)` evaluates to `0.19999999999999993`, not `0.2`. That's expected
- it's how IEEE 754 doubles work, same in every language (JavaScript, GLSL, Python, C). For animation
math the trailing noise is invisible. For text display, pipe through a [formatter](/help/formatting) like
`|round:2` to get a clean `0.2`.

**`now()`** - returns the current timestamp in seconds. Useful for calculating time since an event, e.g.
`now() - c.kofi.latest_donor_at`.

### list writer

A side-effect control with one job: every time the source control's value changes, append the new value
to a target [List](/help/lists). Unlike every other control type, a list writer has no value of its own
and renders nothing in your overlay - the row exists purely as a binding between a source control and a
list.

The source can be **any control type**: a counter you bump from chat, a service-managed control (Ko-fi
donor name, StreamLabs tip amount, Twitch cheer count), or even an Expression Control. Yes, even an
Expression Control - the server evaluates the same formula your overlay does, then writes the result to
the list. So an expression that combines values from multiple services can have its history persisted
automatically.

```
// Bind a chat-driven counter to a log list
Source: c.wins
Target: wins_log

// Mod types !inc wins 1 - the new value gets appended to wins_log automatically

// Persist the result of an expression that aggregates across services
Expression: latest(
  c.kofi.latest_donor_at, c.kofi.latest_donor_name,
  c.streamlabs.latest_donor_at, c.streamlabs.latest_donor_name
)

Source: c.latest_donor (the expression above)
Target: donor_history
```

**Why this exists.** Controls hold the *current* value of something. The moment a counter bumps or a
donation lands, the previous value is gone. A list writer keeps the trail. Once values are in a list,
every existing [list action](/help/lists#actions) works on them:
`[[[c:list:donor_history:count]]]`, foreach iteration, `:last` for the most recent, `:random` for a
shout-out picker, and so on.

**Capping.** Set `max_items` on the target list (from the Lists dashboard) for a rolling window. List
writers FIFO-drop the oldest entry when the cap is hit, so "last 10 donors" is just `max_items = 10` on
the list. Without a cap, the list grows unbounded.

**Curation.** The target list is editable from the Lists dashboard exactly like any other List. Delete
entries you don't want, rename items, clear the whole thing - the writer keeps feeding new values either
way. If a donor you'd rather not advertise lands in the history, just remove the row.

**Disabled lists.** Disabling a list (from the Lists dashboard) silently skips appends. The writer
doesn't error; the value just doesn't land. Re-enable the list to resume.

## Preset Controls (from integrations)

Everything above describes controls *you* create and update by hand. There is a second family of controls
that Overlabels creates and updates *for* you: **preset controls**. When you connect an integration -
Twitch, a donation service, or the Overlabels GPS app - that service can feed live values straight into
your overlays.

### How they differ from the controls above

- **Auto-managed value.** You never type their value in the Control Panel. The connected service updates
  it whenever an event lands - a donation, a cheer, a GPS ping. The Control Panel shows them as
  read-only.
- **Namespaced tag.** Reference them with a source-qualified tag: `[[[c:source:key]]]`, e.g.
  `[[[c:kofi:latest_donor_name]]]` or `[[[c:gps:speed]]]`. The extra segment keeps two services from
  colliding on the same key.
- **Shared across every overlay.** Unlike the overlay-scoped controls above, preset controls are
  **user-scoped**. Add one on any static template and it becomes available in *all* of your overlays
  automatically - you don't add it per overlay.
- **Only when connected.** A service's presets only appear once you've connected that integration. Twitch
  is the exception - its per-stream counters are available as soon as you sign in.

### Adding a preset control

Open a **static** template, click **Add control**, and pick a preset from the **Stream Controls** dropdown
at the top of the modal. The key, type, and label are filled in for you - you can override the label.
Presets you've already added are hidden from the list so you can't add the same one twice.

Prefer to skip the modal? Every preset tag is copy-to-clipboard on the
[Integration presets](/help/integration-presets) reference - paste the tag straight into your overlay
HTML and it resolves the same way.

### The shared donation family

Every donation service (Ko-fi, StreamLabs, Fourthwall, Buy Me a Coffee, Throne) exposes
the same six-key shape, so you can swap services - or combine them - without relearning the keys:

```
donations_received        // counter, bumps per donation
latest_donor_name
latest_donation_amount
latest_donation_message
latest_donation_currency
total_received            // running total this session
```

Because the keys line up, an Expression like `c.kofi.total_received + c.streamlabs.total_received` totals
donations across services. Some services add extras on top: Throne carries an item name, thumbnail URL,
and surprise-gift flag; Buy Me a Coffee adds the latest support type.

### Available integrations

| Integration | What it feeds |
|---|---|
| Twitch | Per-stream counters (follows, subs, raids, cheers, bits) that reset when you go live. Available the moment you connect Twitch. |
| Ko-fi | Donation, subscription, and shop-sale data from your connected Ko-fi account. |
| StreamLabs | Live donation data delivered through the StreamLabs listener. |
| Fourthwall | Donation and tip data for creators using Fourthwall for merch and supporter tiers. |
| Buy Me a Coffee | Supporter and membership data, including the latest support type. |
| Throne | Gift data plus Throne-only extras: item name, product thumbnail URL, and a surprise-gift flag. |
| Overlabels GPS | Live location data from the Overlabels GPS app: speed, coordinates, distance, battery, and per-session aggregates. |
| Overlabels Alerts | The global alert mute state, no integration required. Show a banner while muted: `[[[if:c:alerts:muted]]]ALERTS ARE MUTED[[[endif]]]` - it flips live when you hit the mute button on the Events page. |

[Browse every preset →](/help/integration-presets)

## Managing Controls

Controls live on the **Controls** tab of your overlay's detail page. You must be the overlay owner to
manage them.

### Creating a Control

Click **Add control** in the Controls tab to open the creation modal.

- **Key** - a lowercase slug used in template tags, e.g. `wins`, `goal_amount`. Must start with a letter
  and contain only lowercase letters, digits, and underscores. The key is permanent and cannot be changed
  after creation.
- **Label** - an optional human-readable name displayed in the Control Panel, e.g. "Death Counter". If
  omitted, the key is used.
- **Type** - one of: text, number, counter, timer, boolean, datetime, expression.
- **Sort order** - controls the display order in the Control Panel. Lower numbers appear first.
- **Type-specific config** - Number and counter controls accept min, max, step, and reset value. Timer
  controls accept a mode (count up, countdown, or count to date/time). Expression controls require a
  formula.

### Editing a Control

Click the pencil icon on any control row in the Controls tab. You can update the label, sort order, and
type-specific configuration. The **key** and **type** cannot be changed after creation to protect
references already used in your overlay HTML.

### Deleting a Control

Click the trash icon on a control row and confirm the prompt. Deletion is permanent. Any `[[[c:key]]]`
references left in your overlay will render as blank after deletion - no errors, just empty space.

### Copying the Snippet

Each row in the Controls table shows a copy button with the ready-to-paste snippet `[[[c:key]]]`. Click
it to copy the snippet to your clipboard so you can paste it directly into your overlay editor.

## Using Controls in Overlays

Once a control exists, reference its current value anywhere in your overlay or alert overlay HTML using
the `[[[c:key]]]` syntax.

### Displaying a Value

Place the tag wherever you want the value to appear. At render time the overlay substitutes the current
value.

```html
<div>
  Wins: <span>[[[c:wins]]]</span>
</div>
```

The overlay updates in real time whenever the value changes, no page reload required.

### Conditionals with Control Values

Control values participate fully in the conditional engine. Use them exactly as you would any other
template variable.

```html
[[[if:c:wins >= 10]]]
  <div>On a tear tonight!</div>
[[[elseif:c:wins >= 5]]]
  <div>Building momentum.</div>
[[[else]]]
  <div>Just getting started.</div>
[[[endif]]]

<!-- Show a goal bar only when goal is set -->
[[[if:c:goal_label]]]
  <div>
    <span>[[[c:goal_label]]]</span>
    <progress value="[[[c:goal_current]]]" max="[[[c:goal_target]]]"></progress>
  </div>
[[[endif]]]
```

String comparison, numeric comparison, boolean truthiness... All operators work the same way as with
Twitch data tags. See the [Syntax Help](/help/conditionals) page for the full comparison reference.

### Controls in CSS

Just like Twitch data tags, control tags can appear inside `<style>` blocks, which opens up dynamic
styling.

```html
<style>
  .goal-fill {
    [[[if:c:goal_pct >= 100]]]
    background: #22c55e; /* green when complete */
    [[[else]]]
    background: #3b82f6;
    [[[endif]]]
  }
</style>
```

### Controls in Alerts

Alerts also support control tags. This lets an alert read the current state of your overlay to decide
what to display.

```html
<!-- Alert for a sub that mentions the current death count -->
<div>
  [[[event.user_name]]] just subscribed!
  [[[if:c:wins > 0]]]
    <span>(and yes, [[[c:wins]]] wins so far)</span>
  [[[endif]]]
</div>
```

## The Control Panel

The **Control Panel** is a live dashboard for updating control values during your stream. It lives on the
**Control Panel** tab of your overlay's detail page. Open it in a browser window before going live and
keep it on a second monitor or phone.

### How each type works

| Type | In the panel |
|---|---|
| Text & Number | Type a new value into the input field and click **Save**. The overlay updates immediately. Number controls respect the min, max, and step you configured. |
| Counter | Three buttons: **−** decrements by one step, **+** increments by one step, and **Reset** returns the counter to its configured reset value (default 0). Each press fires immediately, no save button needed. |
| Timer | **Start** begins counting (count up or countdown, depending on your config). The display ticks in the Control Panel and in the overlay simultaneously. **Stop** pauses at the current time. **Reset** returns to zero (or the base duration for countdowns). "Count to" timers show the target datetime and tick automatically - no start/stop needed. |
| Boolean | A single toggle switch. Flip it on or off - the value updates immediately. Pairs well with conditionals to show/hide overlay sections. |
| Datetime | Pick a date and time from the datetime picker and click **Save**. Useful for "Next stream: `[[[c:next_stream]]]`" display text. |
| Expression | Expressions have no input in the Control Panel - their value is always derived from the formula. The panel shows the current expression and its live-evaluated result. |

### Real-time updates

Every Control Panel action broadcasts the new value over your live channel. Any open overlay that
references the changed control re-renders that value in real time - typically in under a second. No
refresh required in OBS.

### Access

The Control Panel is available only to the overlay owner and requires a logged-in session. Your viewers
or collaborators cannot accidentally change your values - there is no public endpoint for mutations.

## Copying an Overlay with Controls

When you copy a public overlay that has Controls attached, Overlabels walks you through the **Import
Wizard** before navigating to your new copy.

### The Import Wizard

The wizard shows a table of every control from the source overlay. For each one you can choose:

| Choice | What happens |
|---|---|
| Create | Recreate this control in your copy with the same type and config. You can edit the key before confirming if you want to rename it. |
| Skip | Leave this control out of your copy. Any overlay tags referencing it will render blank until you add a matching control yourself. |

### What gets copied

- **Copied:** key, label, type, configuration (min/max/mode/base duration, etc.), and sort order.
- **Also copied:** the current value. Although this may bring in some stale data when you copy the
  overlay to your account, it does allow for sharing fully pre-configured overlay templates.
- **New IDs:** each created control gets a brand-new database ID. Changes you make to your copy's
  controls never affect the original template.

### Skipping the wizard

Clicking **Skip all, take me to the copy** skips import entirely and takes you straight to your new
overlay. Your copy will have zero controls at that point. You can always add controls manually from the
Controls tab later, as long as you give them the same keys that your overlay HTML references.

## Tips and Best Practices

### Choose descriptive keys

Keys are effectively permanent, so name them like Future You is tired and mildly annoyed. Use `boss_wins`
instead of `d`.

### Use sort order on purpose

The Control Panel displays controls by sort order. Put the values you touch most during a stream at the
top using sort orders like `0`, `1`, `2`, and so on. That way the important stuff stays within easy reach
when things get hectic.

### Use counters for values that change often

If you are tracking something numeric that changes during the stream, use a counter instead of a text
control. Hitting `+` or `-` is much faster and safer under pressure than manually typing a new number
every time.

Good examples:

- boss fights cleared
- rounds won
- times chat bullied you into a bad idea 🤣

### Controls are more powerful with conditionals

A Control does not have to be shown as raw text. You can use it inside `[[[if:c:wins >= 10]]]` logic to
change content, styling, or full layout states.

```html
[[[if:c:wins >= 10]]]
<div>
  this run is incredible
</div>
[[[endif]]]
```

### Use functions instead of nested ternaries

When comparing values across multiple services, avoid chaining `? :` operators. Use `latest()`,
`oldest()`, `max()`, or `min()` instead - they scale to any number of services without nesting.

Instead of this:

```
c.streamlabs.latest_donor_name_at > c.kofi.latest_donor_name_at
  ? c.streamlabs.latest_donor_name
  : c.kofi.latest_donor_name
```

Do this:

```
latest(
  c.streamlabs.latest_donor_name_at, c.streamlabs.latest_donor_name,
  c.kofi.latest_donor_name_at, c.kofi.latest_donor_name
)
```

### Use now() to track time since an event

Combine `now()` with `max()` and `_at` timestamps to show how long ago something happened - across any
number of services.

Example - seconds since the latest donation:

```
now() - max(
  c.kofi.latest_donor_at,
  c.streamlabs.latest_donor_at,
  c.fourthwall.latest_donor_at
)
```

Pair it with the `duration` pipe formatter in your template to display it as
`[[[c:since_last_donation|duration:mm:ss]]]`.

### Values are sanitized

HTML is stripped from text values before storage. You can't accidentally inject markup through a Control
Panel update.

### URLs and asset links work well in Controls

Controls can store URLs just fine, which makes them useful for reusable assets like:

- image URLs
- CSS file URLs
- profile links
- external media links

That lets you define a value once and reuse it throughout your overlay without hardcoding the same URL in
multiple places.

### Controls can also be used in alerts

Controls created on a static overlay can also parse inside alerts rendered on that same static overlay.
So if your underlying static overlay has a `[[[c:myname]]]` Control, your alerts can use that value too.

The important rule is scope: the Control must exist on the static overlay that the alert is rendered
through. If it does not exist there, it will not parse.

## If you need more help

You can always reach out on [jasper@emailjasper.com](mailto:jasper@emailjasper.com) or
[open a new issue](https://github.com/jasperfrontend/overlabels/issues) on
[Github](https://github.com/jasperfrontend/overlabels).
