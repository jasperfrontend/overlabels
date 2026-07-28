---
title: Lists in Overlabels
description: User-owned arrays of values that streamers manage from the dashboard or chat. Raffles, queues, quote walls, leaderboards, donation goals.
heading: Lists in Overlabels
lead: User-owned arrays of values that streamers manage from the dashboard or chat. Raffles, queues, quote walls, leaderboards, donation goals.
canonical: https://overlabels.com/help/lists
context: lists.index
---

## What is a List?

A List is a named array of values you own. Each List has a slug (the identifier you reference in overlays
and chat) and an optional human-readable label. The values inside can be anything - viewer names, custom
messages, numbers, URLs - and Overlabels stores them exactly as they were entered. No deduplication, no
whitespace trimming, no quiet reordering. Lists are lists.

You reference a List in your overlay HTML/CSS with `[[[c:list:your_slug]]]`. That tag resolves to the
full array as a JSON string when used bare; the more common usage is one of the derived read tags (see
Reading from a List) or a `foreach` loop.

Lists are **user-scoped**: every streamer has their own. They are **not** shared across users. A List you
create is only visible inside your overlays, your chat commands, and your dashboard.

## Creating and editing Lists

Lists live at [/dashboard/lists](/dashboard/lists). Click "New list", pick a slug (lowercase letters,
digits, underscores - must start with a letter, max 50 chars), optionally a label, and optionally a
starting set of items - one per line.

> [!NOTE]
> **Why no dashes in the slug?** Tag parser context. A slug like `my-raffle` would collide with hyphen
> handling inside the tag namespace. Snake_case keeps everything composable.

Once a List exists you can edit its items in a freeform textarea, one item per line. Empty lines, leading
or trailing whitespace, and duplicates are all preserved exactly. The only character we strip is the NUL
byte (because it breaks JSON encoding and you didn't actually mean to type it).

Some Lists are created automatically by recipes you install. Those show a "from Recipe" badge and may be
locked (the recipe declares whether you can edit the items). Locking only affects items - you can still
disable/enable a recipe-managed List from your dashboard.

## Reading from a List in your overlay

Every List ships a set of tags into your overlay's data store on render. Here's the full set for a List
with slug `donors` and items `["Alice", "Bob", "Carol"]`:

| Tag | Resolves to |
|---|---|
| `[[[c:list:donors]]]` | `["Alice","Bob","Carol"]` (the **values** as a JSON string) |
| `[[[c:list:donors:json]]]` | The full item **objects** as a JSON string. |
| `[[[c:list:donors:first]]]` | `Alice` |
| `[[[c:list:donors:last]]]` | `Carol` |
| `[[[c:list:donors:count]]]` | `3` |
| `[[[c:list:donors:empty]]]` | `0` (would be `1` if empty) - pair with [conditional tags](/help/conditionals) |
| `[[[c:list:donors:random]]]` | Random item - stable per overlay mount (does not re-roll on each broadcast) |
| `[[[c:list:donors:sum]]]` | Numeric sum of items. Empties and whitespace are 0; non-numeric content shows an inline error pointing at the offending row. |
| `[[[c:list:donors:expires_at]]]` | Unix seconds when the List expires (empty when no deadline set). |
| `[[[c:list:donors:countdown]]]` | Live seconds remaining until expiry. Ticks every frame; pair with [formatting pipes](/help/formatting). |

All tags update live. When the underlying List changes - because you edited it, because a chat appender
fired, because the sweeper aged out an entry - every overlay reading it patches its data store and
re-renders. No reload, no polling.

## The item data model and `:json`

Under the hood, a List item is no longer just a bare string - it is a small **object** with a stable
identity and room for richer data. You don't have to think about this for everyday use: every tag and
`foreach` on this page keeps working exactly as before, projecting to the item's value. This section is
for when you want to build something richer on top of a List - a custom wheel, an animated leaderboard, a
web component - and need the full data.

> [!NOTE]
> **Nothing you already built changes.** `[[[c:list:slug]]]`, `.first`, `:last`, `:sum`, `:count`, and
> `[[[foreach:c:list:slug as item]]][[[item]]]` all still resolve to value strings, exactly as they
> always have. The object model is purely additive - it adds the new `:json` tag and gives every item a
> stable id, without touching any tag you're already using.

### The item shape

Each item carries six fields:

| Field | Meaning |
|---|---|
| `id` | A whole number assigned by the server, unique within the List, stable for the life of the item, and **never reused** - even after the item is drawn, popped, or the List is cleared. This is the reliable key for animation: two items with the same value still have different ids. |
| `value` | The content you typed (or a viewer appended). The string every scalar tag projects to. Always present; may be empty - we never strip your content. |
| `added_at` | Unix seconds for when the item was added. This is what per-item age-out measures against, and it's handy for "added 3 minutes ago" displays. |
| `label` | *reserved* Optional display label. Always `null` today. |
| `weight` | *reserved* Picker weight for weighted draws. Always `1` today. |
| `color` | *reserved* Optional hex color (`#rgb` / `#rrggbb`). Always `null` today. |

> [!WARNING]
> **About the reserved fields.** `label`, `weight`, and `color` are part of the data shape and ship in
> `:json`, but there is **no way to set them yet** - every item is created with `label: null`,
> `weight: 1`, `color: null`. They're reserved so the editing UI for them can land later without another
> data migration. Build against `id`, `value`, and `added_at` today; treat the rest as defaults until
> weighted/colored editing ships.

### The `:json` tag

`[[[c:list:donors:json]]]` resolves to the full array of item objects as a JSON string. For a List with
items `Alice, Bob, Carol`:

```json
[
  { "id": 1, "value": "Alice", "added_at": 1730000000, "label": null, "weight": 1, "color": null },
  { "id": 2, "value": "Bob",   "added_at": 1730000060, "label": null, "weight": 1, "color": null },
  { "id": 3, "value": "Carol", "added_at": 1730000120, "label": null, "weight": 1, "color": null }
]
```

It updates live like every other List tag - append, draw, edit, or age-out, and the `:json` payload
re-renders with the new objects. The bare `[[[c:list:slug]]]` tag stays an array of value *strings* for
backward compatibility; `:json` is the opt-in that gives you the objects.

> [!WARNING]
> **Important: overlays run no JavaScript.** Overlay templates are display-only HTML and CSS - we strip
> `<script>`, inline event handlers, and `<iframe>` on save. So dropping `[[[c:list:slug:json]]]` into a
> template just prints the JSON as *text*. Useful for a quick peek at the data; useless for building a
> wheel, because there's no way to parse it *inside* the overlay.
>
> To actually **use** the rich data, read it from **outside** the overlay - a small page you host
> yourself, where JavaScript is allowed. That's what the read endpoint below is for.

### Reading a List from outside: the JSON endpoint

Every List is also readable as pure JSON from a public, token-authed endpoint:

```
GET https://overlabels.com/api/lists/<slug>?token=<your overlay token>
```

The `token` is an Overlay Access Token from your dashboard - the same kind your overlay URLs use. It
identifies you, so you only ever read your own Lists (treat it like sharing an overlay URL: anyone who
has it can read that List). The response is the full item objects plus a little metadata:

```json
{
  "slug": "wheel",
  "label": null,
  "count": 2,
  "items": [
    { "id": 1, "value": "Pizza", "added_at": 1730000000, "label": null, "weight": 1, "color": null },
    { "id": 2, "value": "Tacos", "added_at": 1730000060, "label": null, "weight": 1, "color": null }
  ],
  "disabled_at": null, "expires_at": null, "entry_ttl_seconds": null,
  "updated_at": 1730000060, "ts": 1730000061
}
```

It's read-only and cross-origin (CORS-open), so a browser page on any host can fetch it. Note this does
**not** break the "overlays never phone home" rule - the overlay isn't calling anything (it can't, no
JS); a separate page *you* control is, and only to read.

### Live updates (no polling)

The response also carries a `realtime` block - everything your page needs to **subscribe** to live
changes instead of polling:

```json
"realtime": {
  "channel": "lists.<your twitch id>.wheel",
  "event": "list.updated",
  "auth_endpoint": "https://overlabels.com/api/overlay/broadcasting/auth",
  "key": "...", "host": "...", "port": 443, "scheme": "https"
}
```

Point a Pusher/Echo client at `key/host/port`, authorize the channel by POSTing your token to
`auth_endpoint` (the same token gates both the read and the subscribe), and every chat append, draw,
edit, or age-out pushes the new state to your page instantly - a chatter types `!raffle` and your wheel
grows a slice in real time. The copy-paste consumer at `docs/examples/list-data-consumer.html` does the
full bootstrap-then-subscribe dance.

> [!TIP]
> **Want the full walkthrough?** The [Lists in realtime](/help/lists-realtime) guide takes you end to
> end: get a token, read the list, render it, subscribe to live updates, and add your page to OBS - with
> a troubleshooting table for when it doesn't work the first time.

### Why this matters: stable identity

The single biggest reason items became objects is the `id`. A wheel, a leaderboard, or any animated
consumer needs to answer "is this the same item I drew last frame, or a new one that happens to have the
same name?" With bare strings you couldn't - two viewers both named `guest` were indistinguishable. With
ids, you key your DOM and your animations off `item.id` and the ambiguity is gone at the root.

### Worked example: feeding a custom wheel

The wheel is a small page **you host yourself** (GitHub Pages, tiiny.host, anywhere) and add to OBS as
**its own** Browser Source - separate from your Overlabels overlay. It fetches the endpoint and renders
however you like, in plain JavaScript:

```html
<div id="wheel"></div>
<script>
  const TOKEN = '...'; // an Overlay Access Token from your dashboard
  const URL = `https://overlabels.com/api/lists/wheel?token=${TOKEN}`;
  async function load() {
    const res = await fetch(URL);
    const { items } = await res.json();
    // items: [{ id, value, added_at, label, weight, color }, ...]
    // Key each slice by item.id so two identical names never collide,
    // and so an item that survives a draw keeps its slice.
    document.getElementById('wheel').replaceChildren(...items.map(it => {
      const slice = document.createElement('div');
      slice.dataset.id = it.id;
      slice.textContent = it.value;
      return slice;
    }));
  }
  load();
  // Fetch once, or poll for now: setInterval(load, 5000)
</script>
```

That's the whole pattern: fetch the endpoint, objects out, render however you like, key by `id`. A
copy-paste-ready version of this page lives in the repo at `docs/examples/list-data-consumer.html`.

Once weighted and colored editing lands, the same page reads `it.weight` and `it.color` with no other
change - that's exactly why those fields are already in the shape.

## Iterating with `foreach`

The derived tags above are great for "show the first item" or "show the count". When you want to render
every item, use `foreach`:

```html
<ul>
  [[[foreach:c:list:donors as donor]]]
    <li>[[[donor]]]</li>
  [[[endforeach]]]
</ul>
```

Inside the loop, `[[[donor]]]` resolves to each item's **value** in turn. The loop body can use any other
tag the overlay knows about, and the template engine materialises one block per item.

> [!WARNING]
> **Per-item field access in a loop is not available yet.** `[[[donor]]]` gives you the value;
> `[[[donor.color]]]` and friends are **not** wired up yet (it needs a change to how `foreach`
> materialises items). If you need each item's `id` or `added_at` today, read `:json` and iterate it in
> your own script instead.

See the [Reference page](/help/reference) for the full `foreach` syntax, including index access and
nested iteration.

## Chat appenders - viewers grow your List

A chat appender wires a custom command (like `!raffle` or `!join`) to a List. When a viewer runs the
command, the appender resolves a template string and pushes the result into the target List. The viewer's
name lands in the array; the overlay updates live.

Each appender configures:

- **Command** - the bang word viewers type (must be unique across your custom commands, recipes, and
  meta-commands)
- **Target List** - which List the appended value goes to
- **Permission level** - everyone, follower, subscriber, vip, moderator, broadcaster
- **Cooldown** - global cooldown in seconds (broadcaster bypasses)
- **Value template** - the string to append. Uses the same template language as
  [Bot Expressions](/help/expressions): `[[[bot:from_user]]]`, `[[[bot:args]]]`, control reads, pipe
  formatters
- **Empty-args reply** - what to say when the chatter forgot to type an argument the template expects
- **Dedup policy** - `none`, `per_chatter`, or `per_chatter_per_stream`
- **Max size** - hard cap; further fires silently refuse (so slot 100 actually means slot 100)

**Example - raffle entry by display name, one per chatter per stream:**

| Setting | Value |
|---|---|
| Command | `raffle` |
| Target List | `raffle_entries` |
| Value template | `[[[bot:from_user]]]` |
| Dedup | `per_chatter_per_stream` |

**Example - quote wall, value is whatever the viewer typed:** command `quote`, value template
`[[[bot:from_user]]]: [[[bot:args]]]`, empty-args reply
`@[[[bot:from_user]]] you forgot the quote! Use !quote whatever the streamer said.`

## The `!list` meta-command

`!list` is a single configurable chat command that gives you (and your mods) the full action vocabulary
against any of your Lists from chat, without wiring a separate command per action. You opt in once from
[/dashboard/lists](/dashboard/lists) and pick the command name - default is `!list`, but if that collides
with a bot you already use (StreamElements, Nightbot, Fossabot), rename it to whatever you like (`!ol`,
`!l`, `!mylist`...).

Syntax is always:

```
!list <slug> <action> [args...]
```

Examples:

```
!list raffle_entries count
!list raffle_entries draw
!list raffle_entries clear
!list raffle_entries pop first
!list raffle_entries clone backup_today
!list quotes random 3
!list quotes last
!list shoutouts disable
```

**Permission level is fixed to moderator and above.** The action vocabulary is destructive or
chat-emitting, and we don't want a random chatter clearing your raffle list. Broadcaster and moderators
can run it; everyone else's invocations are ignored silently.

The command is **self-documenting**. Bare `!list` replies with global help. `!list <slug>` with no action
replies with the per-list help. An unknown action lists the valid ones. Missing required arguments (like
`!list raffle pop` with no `first|last`) reply with usage hints inline rather than failing silently.

## The action vocabulary in detail

The same ten verbs are available from both the chat `!list` command and the action buttons on
[/dashboard/lists](/dashboard/lists). They split into three groups by semantics.

### Read actions (no mutation, no snapshot)

| Action | What it does |
|---|---|
| `count` | Replies with the number of items. |
| `first [N]` | Replies with the first `N` items (default 1, max = list size). |
| `last [N]` | Replies with the last `N` items (default 1, max = list size). |
| `random [N]` | Replies with `N` random items, without replacement (default 1). |

### Destructive actions (auto-snapshot, broadcast)

| Action | What it does |
|---|---|
| `draw` | Picks a random item, removes it, announces the winner. The classic raffle action. |
| `clear` | Empties the List. Use before starting a fresh raffle or queue. |
| `pop first` | Removes and announces the head of the List. Useful for FIFO queues - "who's next?". |
| `pop last` | Removes and announces the tail of the List. |
| `clone <new_slug>` | Duplicates the List into a new List with the given slug. Inherits items, label, and item ages verbatim. |

Every destructive action automatically creates a snapshot of the List's previous state before it mutates.
That snapshot is what makes undo possible.

### State actions

| Action | What it does |
|---|---|
| `disable` | Disables the List. Chat appenders silently refuse; existing items stay visible. |
| `enable` | Re-enables a disabled List. |

## Snapshots - undo for destructive actions

Every time a destructive action runs - `clear`, `draw`, `pop`, or a `restore` from an earlier snapshot -
Overlabels writes the List's previous state into a snapshot row. You can also take manual snapshots from
the dashboard before a risky edit.

The snapshots panel under each List shows up to 50 recent snapshots with their reason badge, item count,
and age. You can:

- **Restore** - replace current items with the snapshot's items. Creates a `before_restore` snapshot
  first, so the restore is itself undoable.
- **Pin** - exempt the snapshot from auto-retention. Pinned snapshots stay until you unpin or delete
  them.
- **Delete** - remove a single snapshot immediately. No further undo.
- **Save snapshot** - take a manual snapshot of the current state right now.

> [!NOTE]
> **Retention.** Unpinned snapshots are automatically deleted 30 days after they were created and cannot
> be recovered after that point. Pinned snapshots stay until you act on them. Deleting a List removes all
> its snapshots, pinned or not. This retention behavior is also covered in the
> [privacy policy](/privacy).

## Auto-expiry - entry age-out and whole-list deadlines

Two independent timers you can set on a List from the dashboard's Expiry panel:

### Per-item age-out

Set **Per-item age-out** (seconds, minutes, or hours, max 30 days) and any item older than that is
removed automatically. The sweeper runs every minute. Useful for rolling shoutout walls, "recent donors"
displays, queue cleanups, anything where staleness is bad.

Reordering items in the dashboard preserves their age. Renaming an item (or typing a new one) resets that
entry's age to zero. Cloning a List inherits item ages verbatim, so a mid-stream clone of a 5-minute-old
raffle entry keeps the 5 minutes already accrued.

### Whole-list deadline

Set **Whole-list deadline** to a future moment, and at that moment the List is snapshotted, cleared, and
disabled. The snapshot follows the regular 30-day retention rule. Clearing the deadline on a
previously-expired List also re-enables it - "reopen" is a single action.

### Tags for the deadline

Two tags surface the deadline directly in your overlay:

| Tag | What it resolves to |
|---|---|
| `[[[c:list:raffle:expires_at]]]` | Unix seconds of the deadline. Empty string when no deadline is set. |
| `[[[c:list:raffle:countdown]]]` | Seconds remaining (clamped at zero), updated every frame. Pair with a duration formatter for display. |

**Example - live mm:ss countdown in your overlay:**

```
Raffle closes in [[[c:list:raffle:countdown|duration:mm:ss]]]
```

See [formatting pipes](/help/formatting) for the full duration pattern reference (`hh:mm:ss`,
`dd:hh:mm:ss`, etc).

## Disable and enable

Disabling a List flips a single flag with two visible effects:

- Chat appenders silently refuse new appends. No error message, no apology - you disabled it
  intentionally.
- Existing items stay visible to overlays. You can still curate them manually from the dashboard.

Use it when a raffle has closed but you're not ready to draw yet, when a queue is paused for a break, or
when you want to freeze a state for screenshotting without losing chat-appender wiring. Toggle from the
dashboard, from chat via `!list <slug> disable`, or implicitly through whole-list expiry.

## Worked examples

### Raffle - !raffle to enter, !list raffle draw to pick a winner

1. Create a List `raffle_entries`.
2. Create a chat appender: command `raffle`, target `raffle_entries`, value template
   `[[[bot:from_user]]]`, dedup `per_chatter_per_stream`.
3. Opt into the `!list` meta-command.
4. Optionally set a whole-list deadline so the raffle closes on its own. Put
   `[[[c:list:raffle_entries:countdown|duration:mm:ss]]]` in your overlay.
5. When the deadline hits, the List disables itself. Run `!list raffle_entries draw` from chat to pick
   the winner.
6. Want to redraw? The before-draw snapshot is right there in the snapshots panel - restore and `draw`
   again.

### FIFO queue - !join to enter, !list queue pop first for next up

1. Create a List `queue`.
2. Create a chat appender: command `join`, value template `[[[bot:from_user]]]`, dedup `per_chatter`
   (lifetime, not per-stream).
3. Put the queue in your overlay as a foreach:

   ```
   [[[foreach:c:list:queue as player]]]
     <li>[[[player]]]</li>
   [[[endforeach]]]
   ```

4. Each play, run `!list queue pop first` in chat to grab the next person.

### Quote wall - !quote to add, random rotation in overlay

1. Create a List `quotes`.
2. Create a chat appender: command `quote`, value template `[[[bot:from_user]]]: [[[bot:args]]]`,
   permission `moderator`.
3. In your overlay use `[[[c:list:quotes:random]]]` to show one quote at a time. It stays stable per
   overlay mount; reload the browser source to pick a new one.
4. Per-item age-out optional - set 30 days to keep the wall recent without manual pruning.

### Donation tally - List of amounts, :sum as the goal driver

When you don't want to wire a full Ko-fi or StreamLabs integration but you do want a quick tally:

1. Create a List `tips`.
2. Create a chat appender: command `tip`, value template `[[[bot:args]]]`, permission `moderator` (so
   only mods log tips after confirming them out-of-band).
3. In your overlay:

   ```
   Raised: €[[[c:list:tips:sum]]] of €500 goal
   ```

4. Want a progress bar? Create an [Expression Control](/help/expressions) `tip_pct` with expression
   `c.list.tips.sum / 500 * 100` and reference `[[[c:tip_pct]]]`.

## Things to know

### Lists are lists. We don't sanitise content.

Whatever you (or your viewers) put in, we keep. Empty lines, duplicates, 200x the same value, lengthy
whitespace - it's all yours. The only character stripped is the NUL byte, because it would break JSON
serialisation downstream. This contract is intentional: opinionated trimming would surprise people who
actually want what they typed.

### `:random` is stable per overlay mount.

The random tag picks once on initial render and keeps the same value across broadcasts. Otherwise every
append would re-roll it and your overlay would flicker. To pick a new random item, reload the browser
source. To pick at the moment of an action, use the chat `!list <slug> random` action instead.

### Mod permission is the floor for `!list`.

The meta-command can clear, draw, disable, and clone. We don't want a viewer running `!list raffle clear`
mid-raffle. Chat appenders (`!join`, `!raffle`) have their own independent permission setting that can be
looser (everyone, follower, subscriber, etc).

### Command-name collisions are checked at save time.

A chat appender's command, the `!list` meta-command, your Bot Expressions, your recipe triggers, and the
built-in commands all share the same namespace. Save-time validation refuses collisions with a clear
error rather than silently letting one of them win at runtime.

### Sweeps run every minute.

Both per-item age-out and whole-list deadlines are evaluated by a sweep that runs every minute. So a
30-second TTL doesn't mean entries vanish on the second; they vanish on the next sweep tick after they
age past the cutoff. This is fine for "trim things that should not stick around" - if you need exact
timing, use a timer Control instead.

### Recipe-managed Lists.

Recipes can create Lists on your behalf. They show up with a "from Recipe" badge. If the recipe declared
the items locked, you can't edit them - uninstall the recipe to free the List. You can still disable,
enable, set TTLs, and use the action vocabulary against locked Lists.

### Live updates everywhere.

Every change to a List (manual edit, chat append, action, sweeper) broadcasts to your overlays and your
dashboard. Overlays patch their data store in place. The dashboard page patches the active row; if you're
mid-edit with unsaved changes, your draft wins until you save - we don't trash your work.

## Quick reference card

```
Tags
  [[[c:list:slug]]]              JSON array of value strings
  [[[c:list:slug:json]]]         JSON array of item objects {id,value,added_at,label,weight,color}
  [[[c:list:slug:first]]]        first item
  [[[c:list:slug:last]]]         last item
  [[[c:list:slug:count]]]        item count
  [[[c:list:slug:empty]]]        "1" if empty, "0" otherwise
  [[[c:list:slug:random]]]       random item (stable per mount)
  [[[c:list:slug:sum]]]          numeric sum of items
  [[[c:list:slug:expires_at]]]   deadline as Unix seconds
  [[[c:list:slug:countdown]]]    live seconds remaining

Foreach
  [[[foreach:c:list:slug as item]]] [[[item]]] [[[endforeach]]]

Chat - !list meta-command (mod+)
  !list <slug> count
  !list <slug> first [N]
  !list <slug> last [N]
  !list <slug> random [N]
  !list <slug> draw
  !list <slug> clear
  !list <slug> pop first|last
  !list <slug> clone <new_slug>
  !list <slug> disable
  !list <slug> enable

In an Expression Control
  c.list.slug.sum
  c.list.slug.count
```

For deeper context on the template language and pipe formatters, see
[Conditional and Event Tags](/help/conditionals) and [Formatting Pipes](/help/formatting). For numeric
computation on top of Lists, see [Expression Controls](/help/expressions).
