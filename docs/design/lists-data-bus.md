# Lists as a Realtime Data Bus - Design Strawman (M8)

> **Status:** strawman, not a spec. Everything here is decided the way I would decide it,
> with the reasoning attached, so you can read it cold and react. Where there is a genuine
> fork I could not just settle for you, it lives in section 8 with the options, my pick, and
> the tradeoff. Your job reading this is to veto, not to author. "No, do it the other way" is
> a complete answer anywhere in here.
>
> Nothing gets built off this until you have read it, sat with it, and said go.

---

## 1. TL;DR

Today, every List mutation broadcasts the **whole list after the change**. The overlay renderer
takes that array and full-replaces its copy. That works for our own dumb renderer and nothing else,
because an outside consumer (a Lottie web component, a custom Vue overlay, a Bun script, a future
Stream Deck plugin) cannot animate a *change* it was never told about. It only ever sees the new
total state and has to diff against its own memory to guess what happened.

M8 flips that. On every mutation we also publish **what changed** - "item X was appended at the end",
"the item at index 4 was drawn and removed", "these three entries aged out" - on a dedicated
per-list channel. Once that exists, Overlabels stops being a thing that renders overlays and starts
being a **bus**: outside consumers subscribe to a list and do their own choreography. The
wheel-of-fortune problem, the "loading an iframe inside a Vue frontend is stupid" problem, the
dead-CSS-wheel problem - they all stop being ours. We ship the events; other people ship the pretty.

The work is mostly mechanical because every mutator already knows its own delta. The only real
design weight is in three places: what exactly goes on the wire, how an outside consumer is allowed
to connect at all, and how we keep payloads under Reverb's hard limit. Those are sections 4, 6, and 7.

---

## 2. Where we are today (so the strawman is honest)

The model is `OptionSet` (Lists and the Recipes "option set" primitive are the same table). Relevant shape:

- `items` - a JSON array of **plain strings**. Not objects. `["Heads", "Tails"]`.
- `item_added_at` - a **parallel** JSON array of Unix-second timestamps, one per item, same index.
- `disabled_at`, `expires_at`, `entry_ttl_seconds` - list-level state.
- `slug` - the identity (`c:list:<slug>`), per user, stable.

One event today: `App\Events\ListUpdated`. It broadcasts on the per-user channel
`alerts.{twitch_id}` as `.list.updated` (or `.list.deleted` when `items === null`). Payload is
after-only: `{slug, items, updated_at, expires_at, disabled_at}`.

Every mutation funnels through `ListUpdated::dispatchFor($twitchId, $list)`. The call sites, and
**the delta each one already has in hand** (this is the important column - it proves the work is wiring, not invention):

| Mutation | Code | Delta already in scope |
|----------|------|------------------------|
| chat append | `ListAppendService::fire` | the one appended string + its timestamp |
| control-driven append | `ListWriterAppend::appendToList` | the appended value (+ FIFO overflow drop) |
| draw (raffle winner) | `ListActionService::actionDraw` | `$winnerIdx` + `$winner` |
| pop first/last | `ListActionService::actionPop` | end + `$popped` value |
| clear | `ListActionService::actionClear` | the count it just wiped (snapshot taken first) |
| clone | `ListActionService::actionClone` | source slug + the copied items (new list) |
| disable / enable | `ListActionService::actionDisable/Enable`, `ListController::update` | the new `disabled_at` |
| dashboard save (textarea) | `ListController::update` (items path) + `store` | old items + new items both in scope |
| delete | `ListController::destroy` | the slug |
| ttl / expiry / permission edit | `ListController::update` (focused PATCH paths) | which fields changed |
| restore snapshot | `ListActionWebController::restoreSnapshot` | snapshot id + restored items |
| entry-TTL age-out | `ListExpirySweeper::sweepEntries` | exactly which items + stamps were dropped |
| whole-list expiry | `ListExpirySweeper::expireList` | snapshot taken, then cleared |

Destructive ops already take a `ListSnapshot` of the before-state first. So "before" is not just
derivable, it is already being persisted at the moment of mutation.

The consumer today: `OverlayRenderer.vue::handleListUpdated()`. It takes `event.items`, rebuilds the
`c:list:<slug>` data slots (the JSON string, the `.N` indexed scalars, `:count`, `:first`, `:last`,
`:empty`, `:sum`), re-seats the expiry countdown timer, and strips stale indices. It is a full
re-derive on every broadcast. **It works and we are not touching it** (see decision D3).

---

## 3. The shift, in one sentence

> Stop only answering "what is the list now?" and start also answering "what just happened to it?"

The first question is for dumb consumers that re-render from scratch (our renderer). The second is
for smart consumers that animate transitions. We keep answering the first and start answering the
second, on a separate channel, so neither audience pays for the other.

---

## 4. The wire format

> **Superseded by §13.6 (D5).** The envelope below is described in string-era terms (op carries
> value + index). With items-as-objects, ops carry whole items keyed by `id` and indices are dropped.
> Read §13.6 for the authoritative payloads; the envelope structure (op + size-guarded after) is unchanged.

A new event, `App\Events\ListMutated`, published per mutation. One envelope:

```jsonc
{
  "slug": "raffle",
  "op": {
    "type": "remove",
    "reason": "draw",
    "items": ["bob"],          // the values that left
    "indices": [4]             // where they were, in the pre-op array
  },
  "after": {
    "items": ["alice", "carol", "dave"],   // resulting array, SIZE-GUARDED (see §7)
    "count": 3,
    "disabled_at": null,
    "expires_at": null,
    "entry_ttl_seconds": null
  },
  "after_truncated": false,    // true => "after.items" was too big to ship, refetch via REST
  "ts": 1716500000
}
```

Two halves, on purpose:

- **`op`** is the surgical truth. Small, always present, this is what an animator reads. "An item
  left, it was `bob`, it sat at index 4." A wheel spins to index 4 and flings it off. A leaderboard
  slides row 4 out. The consumer never has to diff anything.
- **`after`** is the convenience snapshot for a consumer that would rather just re-render (same data
  the dumb renderer wants). It is **size-guarded**: if shipping `after.items` would push the whole
  Reverb message over budget, we drop the array, set `after_truncated: true`, and the consumer
  refetches current state via the REST endpoint in §6. `after.count` and the metadata always ship
  (they are tiny).

**What is deliberately NOT on the wire: `before`.** The Supabase old/new pattern ships both because
Supabase clients are stateless. Ours are not - a consumer subscribes to one list, fetches its state
once on mount (§6), and maintains it. Its "before" is its own current copy. Shipping `before` would
double the payload (the exact thing that crashes us at the Reverb limit) to send data the consumer
already has. The one honest cost of dropping `before` is gap-resilience: if a consumer misses a
message, it drifts. That has a clean answer (a sequence number + refetch) but it is not free, so it
is a real decision - see D1. The pragmatic v1 leans on `after` as the resync crutch instead.

Note `op.indices` are positions in the **pre-op** array (where the item *was*), which is what an
animator needs to know what to grab. `after.items` reflects the **post-op** array. A consumer using
`op` works in pre-op coordinates; a consumer using `after` works in post-op coordinates; they never
mix.

---

## 5. The op catalog

> **Superseded by §13.6 (D5).** The op *types* below are final; their *payloads* now carry item
> objects (and `ids[]` on remove) instead of values + indices. See §13.6.

Every op type, the mutator that emits it, and the surgical fields. This is the whole vocabulary.

| `op.type` | reason(s) | fields | emitted by |
|-----------|-----------|--------|-----------|
| `append` | - | `item`, `index`, `item_added_at` | chat append, control-driven append |
| `remove` | `draw`, `pop_first`, `pop_last`, `sweep_ttl` | `items[]`, `indices[]`, `reason` | draw, pop, entry-TTL sweep |
| `clear` | `manual`, `expiry` | `count` (what was wiped), `reason` | clear action, whole-list expiry |
| `replace` | - | `items[]` (new array, size-guarded) | dashboard textarea save, create |
| `restore` | - | `from_snapshot_id`, `items[]` (size-guarded) | snapshot restore |
| `clone_create` | - | `source_slug`, `items[]` (size-guarded) | clone (fires on the **new** list's channel) |
| `state` | - | `disabled` (bool) | disable / enable |
| `meta_change` | - | `changed[]` (e.g. `["expires_at"]`), new values | ttl / expiry / permission PATCH |
| `delete` | - | (none beyond slug) | list delete |

Implementation shape: a single helper, e.g. `ListMutated::emit($twitchId, $slug, array $op, ?OptionSet $list)`,
dropped in next to each existing `ListUpdated::dispatchFor(...)` call. The mutator passes the op it
already computed. `ListUpdated` keeps firing unchanged (for the dumb renderer); `ListMutated` fires
alongside it. Roughly a dozen call sites, each a one-to-three-line addition.

`replace`, `restore`, and `clone_create` carry full arrays because there is no surgical delta worth
computing for them (a textarea save is a wholesale swap; a streamer typing in a box is not an
animation-critical path). They get the same size guard as `after.items`.

---

## 6. How an outside consumer actually connects

This is the part the memory glossed and the code made concrete. A private channel is useless to a
third party if they cannot authenticate to it, and our list channels **must** be private - lists
hold donor names, raffle entrants, and other PII. Public channels are off the table.

Today there are exactly two ways to get onto `alerts.{twitch_id}`:
1. A logged-in dashboard session (`routes/channels.php`), or
2. An overlay presenting an `OverlayAccessToken` via `POST /api/overlay/broadcasting/auth` (the
   64-char hex token model, where the token lives in the URL fragment and the server only ever sees
   `sha256(token)`).

An external Bun script or a Lottie component is neither. So the design:

**New per-list channel:** `lists.{twitch_id}.{slug}`. A consumer that cares about one list subscribes
to one channel and never has to filter. (A power user wanting everything can subscribe to several;
a per-user firehose is a possible later addition, not v1.)

**Auth: reuse the OverlayAccessToken model.** The consumer presents an access token (the same
primitive overlays already use) and we authorize the list channel through the same
`broadcasting/auth` style path that overlays use, extended to recognize `lists.*`. This keeps one
access model across the whole product, keeps the token-in-fragment / `sha256` security story intact,
and means "give my buddy read access to my raffle list" is the same mental model as "give my buddy
my overlay URL." This is a real decision because it touches the security surface - see D2.

**Bootstrap endpoint (read-only, GET):** `GET /api/lists/{twitch_id}/{slug}`, authed by the same
token, returns the current `{items, count, disabled_at, expires_at, entry_ttl_seconds, ts}`. The
consumer lifecycle is:

```
1. GET current state (REST, authed by token)   -> seed local copy
2. subscribe to lists.{twitch}.{slug}           -> receive ListMutated ops
3. apply each op to local copy / animate
4. on after_truncated, or on a detected gap     -> GET again, reseed
```

This is fully compatible with **overlays never phone home**: the consumer only ever *reads* (GET) and
*receives* (server-to-client broadcast). It never pushes state back. The broadcast remains one-way.

And not coincidentally, those two endpoints - "GET current state, subscribe to changes" - are
exactly the shape of the "Overlabels as your API provider" idea (`const ol = overlabels`). v1 does
not ship an npm package, but it lays the two rails an SDK would wrap. See §12.

---

## 7. Hard constraints this must not break

These are non-negotiable and the design above already respects them; calling them out so a future
change does not quietly violate one.

1. **Reverb's ~10 KB payload cap is real and has bitten us.** The cap is on the *full wrapped*
   request, and escaping inflates JSON by ~20%. A bounded game log blew this in gamejam room 5. So:
   every `ListMutated` payload is bounded. The `op` half is inherently small. The `after.items` /
   `replace.items` / `restore.items` / `clone_create.items` arrays are the only unbounded parts, and
   they are all size-guarded with the same rule: if the serialized envelope would exceed a budget
   (propose 8 KB, leaving headroom under 10 KB), drop the array, set `after_truncated`/`truncated`,
   and let the consumer refetch. A 100-item list of short chat strings is ~1-2 KB, so in practice the
   guard only ever trips on a pathological textarea paste, which is exactly the non-animation path
   where a refetch is fine.
2. **Overlays never phone home.** Consumers GET once and receive diffs; never push. (§6.)
3. **Tags parse exactly once per render.** The diff stream updates *data slots*, never re-runs the
   template parser. The existing renderer already obeys this; `ListMutated` consumers are external
   and never touch our parser at all.
4. **Items are objects (D5 resolved).** Each item carries a stable `id`; animations key off it, which
   solves the "two identical names" problem at the root. The string-era assumptions in §2, §4, and §5
   are superseded by §13.

---

## 8. Decisions (D1-D5)

The forks I could not just settle. **D1-D4 are locked (2026-05-24); D5 is open with a default that
holds until items-as-objects is worth pulling forward.** Each entry below now reads "Decided" instead
of "my pick" where it has been settled.

### D1 - Do we ship `before`, and do we add gap-resilience now?
- **Option A - Supabase-faithful:** ship `before` + `after`. Stateless consumers, simplest consumer
  code. Cost: biggest payloads (worst case for the 10 KB cap), and `before` is redundant for any
  consumer that maintains state.
- **Option B - op-delta + `after` crutch (my pick for v1):** no `before`; ship the surgical `op`
  plus a size-guarded `after`. Consumers seed from REST and apply ops. Occasional dropped message
  causes drift until the next `after` (which most ops carry) re-syncs them. Cheapest, ships closest
  to the ~1 day estimate.
- **Option C - op-delta + sequence number + REST resync (the robust version):** add a monotonic
  `version` per list, stamp every op with it, consumer detects a gap (version jumped >1) and
  refetches. This is the "correct" distributed answer and not much code, but it is real scope on top
  of B (a column, a bump, consumer resync logic) and pushes past ~1 day.

**Decided (2026-05-24):** ship **B** - drop `before`, send the surgical `op` + a size-guarded `after`.
The envelope reserves a `version` field, left null in v1, so **C** (sequence number + resync) is a
pure addition the day scale demands it. A is rejected; `before` is the payload killer for the one
benefit we do not need.

### D2 - How do external consumers authenticate to a list channel?
- **Option A - reuse OverlayAccessToken (my pick):** one access model for the whole product; the
  token-in-fragment / `sha256` security story already exists and is understood; "share my list" ==
  "share my overlay URL." Slight conceptual stretch (a Bun script is not an "overlay").
- **Option B - a new list-scoped read token:** cleaner mental model (a token that means "read these
  lists"), finer-grained revocation. Cost: a second token system to build, store, document, and
  secure.

**Decided (2026-05-24):** A - reuse the OverlayAccessToken model. One access model across the product,
the `sha256`/fragment security story already exists, and "share my list" == "share my overlay URL."
If list-sharing-as-a-product ever grows up, B (a dedicated list-scoped read token) becomes its own
milestone.

### D3 - Touch the overlay renderer, or leave it alone?
- **Leave it alone (my pick):** `ListUpdated` keeps firing on `alerts.{twitch}`; the renderer is
  unchanged; `ListMutated` is purely additive on the new channel. Zero risk to the thing that works
  today. Cost: every mutator dispatches two events. At our volume that is negligible.
- **Migrate the renderer onto the diff channel:** one event, one channel, eventually retire
  `ListUpdated`. Cleaner end state, but it is a refactor of a working hot path for no user-visible
  gain right now.

**Decided (2026-05-24):** leave the renderer alone for M8 (purely additive). "Consolidate onto one
channel and retire `ListUpdated`" is a later cleanup, once external consumers have proven the diff
format in the wild.

### D4 - `replace` / `restore` payload strategy
- **Full array + size guard (my pick):** simplest, correct, and the size guard already exists for
  `after`. A consumer treats `replace` as "swap your whole copy."
- **Structural sequence diff (LCS/Myers):** richest (added/removed/moved with indices) so even a
  textarea save could animate. Cost: the only genuinely non-trivial algorithm in the whole feature,
  for the least animation-critical path (a human typing in a box). Over-engineering.

**Decided (2026-05-24):** full array + size guard. The surgical ops (append/draw/pop/sweep) are where
animation matters, and those are already precise.

### D5 - Item identity for v1
- **Strings + index (my pick, forced by current schema):** animate by value and position. Cannot
  distinguish two identical strings. Fine for raffles-by-name, wheels, leaderboards.
- **Items as objects now (`{id, label, ...}`):** stable identity, the "consumers really start
  cooking" unlock. But it is a schema migration touching every mutator, the renderer's `:sum`/`.N`
  derivation, the recipe manifest, and chat append semantics. That is its own milestone-sized change.

**Decided (2026-05-24):** **items as objects, now** - not strings + index. Doing it right beats
migrating live list data later, and moving the timestamp into the item collapses the fragile
parallel `item_added_at` array that every mutator currently juggles. This is a real scope increase
(schema migration, every mutator, the tag-projection layer, and the wire format all change) and
turns M8 from ~1 day into a multi-day foundation piece. Three follow-on decisions fall out of it:
the **object shape** (being chosen now), the **tag backward-compat contract** (bare `c:list:slug.0`
must still render the item's value string), and the **data migration**. The id strategy is settled:
a per-list integer counter (smallest on the wire, stable, never reused). The full items-as-objects
design lands in a new section once the object shape is locked - which supersedes the "out of scope"
note for it in §9.

---

## 9. Out of scope for v1 (named so they are not silently forgotten)

- ~~Items as objects~~ - **pulled into M8 (D5 resolved 2026-05-24, fixed rich schema). Designed in §13.**
- **A monotonic version / sequence number and gap-driven resync** (D1 Option C). Envelope leaves room
  for it; v1 does not implement it.
- **A per-user firehose channel** (`lists.{twitch_id}` carrying all slugs). Per-slug only for v1.
- **The npm/SDK package** (`import overlabels from ...`). v1 ships the rails (§6, §12), not the wrapper.
- **A list-scoped token system** (D2 Option B).

---

## 10. Build sequence (each step independently landable)

1. **`ListMutated` event + the size-guard helper.** Define the envelope, the budget check, the
   `after`/`truncated` logic. No call sites yet. Unit-test the guard with a pathological array.
2. **Wire the surgical ops** at the existing `dispatchFor` sites: append, draw, pop, clear, sweep.
   These are the high-frequency, animation-critical paths and the ones that prove the format.
3. **Wire the wholesale ops:** replace (textarea save + create), restore, clone_create, state,
   meta_change, delete.
4. **The new channel + auth.** `lists.{twitch}.{slug}` authorization through the extended
   overlay-token path (D2-A). Dashboard-session auth for the owner too.
5. **The REST bootstrap endpoint** `GET /api/lists/{twitch}/{slug}`, token-authed, read-only.
6. **A reference consumer** - a tiny standalone HTML+JS page (or a dotlottie-wc demo) that seeds via
   REST, subscribes, and logs/animates ops. This is both the proof and the first doc example. It is
   also the thing that tells us the format is actually pleasant to consume before we call it stable.

Steps 1-3 are the "~1 day" core. Steps 4-6 are what turn it from "our renderer could use this" into
"anyone can use this," and are where the real product value is.

---

## 11. Test surface

- Size guard: an oversized `after.items` trips `after_truncated` and omits the array; a normal list
  does not.
- Each op type emits the correct surgical payload (draw removes the right index; pop_first vs
  pop_last; sweep_ttl reports exactly the aged-out entries and their old indices; clone fires on the
  new slug's channel, not the source's).
- `ListUpdated` still fires unchanged alongside `ListMutated` (renderer regression guard).
- Channel auth: a valid token authorizes `lists.{twitch}.{slug}`; a token for a different user is
  refused; the owner's session authorizes.
- REST bootstrap returns current state and refuses a bad/absent token.
- Per memory, the existing private-channel auth had a real outage once - so an explicit
  "subscribe succeeds end to end" test on the new channel is worth the cost.

---

## 12. The horizon (why this is bigger than wheels)

Section 6's two endpoints - GET current state, subscribe to changes - are the entire surface area of
a realtime data provider. Today they serve Lists. But "everything should eventually be able to become
a list, even an immutable one" is the stated endgame: every loop over any data (followers, donations,
chat, recipe results) becomes a List, and therefore becomes something an outside consumer can read
once and then receive live diffs of. At that point `const ol = overlabels` is a thin client over
exactly these two rails, and Overlabels is a Supabase-shaped realtime backend that happens to
specialize in stream data. M8 is the first vertical slice of that, built for the one data type
(Lists) that most needs it first. We are not building the SDK now - we are making sure the two things
an SDK would wrap exist and are shaped right.

This is also the messaging primitive Flows will need anyway: a Flow that Emits to a List, and another
consumer that reacts to the change, is the same producer/bus/consumer split, now with a wire format
that carries intent instead of just state.

---

## 13. Items as objects (D5 build-out, fixed rich schema)

D5 resolved to items-as-objects with a fixed, validated schema. This section is the authoritative item
model. Where sections 2, 4, and 5 assume bare strings, this supersedes them.

### 13.1 The item shape

Every entry in `option_sets.items` is an object:

```jsonc
{
  "id": 7,                 // int, server-assigned, stable for the item's life, unique-in-list, never reused
  "value": "Tacos",        // string, the primary content (REQUIRED; may be empty - we never strip user content)
  "added_at": 1716500000,  // int unix seconds, when it was appended (honors the _at contract)
  "label": null,           // string|null, optional human display label
  "weight": 1,             // number, picker weight; defaults to 1 (equal), always present
  "color": null            // string|null, optional hex (#rgb / #rrggbb), validated when present
}
```

- `id`, `value`, `added_at` are system-owned and always present. `value` is the backward-compat anchor:
  every scalar tag, plus the chat / search / sum surface, reads it.
- `label`, `color` are nullable - null means absent, and per the null-over-placeholder rule a null
  field renders nothing (no dash, no "N/A"), never a fabricated default.
- `weight` is the one rich field that defaults rather than nulls: `1` = equal weight, always present, so
  weighted-picker math is `total / weight` with no null branch. Validated as a positive number.
- **Accepted tradeoff (chosen at D5):** the schema is closed. A future field (`avatar_url`, `emote_id`, ...)
  is a migration, not a config change. That was the explicit cost of "validated up front," and it is on
  the record as accepted.

### 13.2 id strategy

A per-list monotonic integer counter, `option_sets.next_item_id`, incremented on every append. Ids are
small (cheap on the wire), strictly increasing (free insertion order), unique within the list, and never
reused even after a draw/pop. No UUIDs/ULIDs - lists never merge across users, so global uniqueness buys
nothing and costs payload weight.

### 13.3 The parallel timestamp array goes away

`item_added_at` is deleted; `added_at` lives inside each item. This removes the `ListItemTimestamps`
helper and the `stamp ?? null` / `removeAt` / `preserveByValue` juggling from every mutator and the
sweeper - a net simplification that pays for part of the objects work. The entry-TTL sweep reads
`item.added_at` directly.

### 13.4 Tag backward-compat contract (the fork, resolved)

Existing overlay templates must not break, so scalar access keeps projecting to `value`:

| Tag | Resolves to |
|-----|-------------|
| `[[[c:list:slug.0]]]` | item 0's `value` (string) - unchanged |
| `[[[c:list:slug:first]]]` / `:last` | first / last item's `value` |
| `[[[c:list:slug:sum]]]` | sum of numeric `value`s |
| `[[[c:list:slug:count]]]` / `.count` | item count - unchanged |
| `[[[foreach:c:list:slug as item]]] ... [[[item]]]` | bare `[[[item]]]` -> `value` (string) - unchanged |

New field access via a dotted suffix (the power objects unlock):

- `[[[c:list:slug.0.label]]]`, `.weight`, `.color`, and also `.id` / `.added_at` (useful for keying/animation)
- inside foreach: `[[[item.label]]]`, `[[[item.weight]]]`, `[[[item.color]]]`, `[[[item.id]]]`

The one bare-tag sub-choice: `[[[c:list:slug]]]` (the JSON-array tag) stays an **array of value strings**
for backward compatibility; a new `[[[c:list:slug:json]]]` returns the **array of full objects**. (My
pick; trivial to flip to bare = objects if you'd rather.)

Both the server-side initial tag resolution (render time, parsed once - tags never reparse) and the
client-side renderer patch apply this projection identically - the same server/client mirror discipline
`computeListSum` already follows.

### 13.5 Consequence for the renderer (relates to D3)

D3 ("leave the renderer alone") meant "do not move it to the diff channel," and that still holds. But
the renderer must learn the object shape because the data it consumes changed:
`OverlayRenderer.vue::handleListUpdated()` derives `.N` from `item.value` and additionally exposes
`.N.label/.weight/.color`. This is real renderer work the strings version would not have needed. It does
not move the renderer onto the diff channel - `ListUpdated` keeps carrying the (now object) array on the
existing `alerts.{twitch}` channel.

### 13.6 Wire format in object terms (supersedes §4 / §5)

`ListMutated` ops carry whole items by `id` instead of value + index:

- `append` -> `op: { type: "append", item: { id, value, added_at, label, weight, color } }`. No index;
  consumers append or insert by id order.
- `remove` -> `op: { type: "remove", ids: [7], items: [ {…} ], reason }`. `ids` is the animation key; the
  full removed objects ride along so a consumer can animate the thing leaving without having cached it.
- `replace` / `restore` / `clone_create` -> carry the new array of objects, size-guarded as before.
- `clear` / `state` / `meta_change` / `delete` -> unchanged (no per-item payload).
- `after.items` (the size-guarded convenience snapshot) is now an array of objects.

Indices are dropped from the wire entirely: `id` is the stable key that indices were only ever a weak
stand-in for. That substitution is the whole reason objects were worth doing.

### 13.7 Migration

A schema migration converts existing data in place:

- `option_sets.items`: `["A","B"]` + `item_added_at: [t1,t2]` becomes
  `[{id:1,value:"A",added_at:t1,label:null,weight:1,color:null}, {id:2,value:"B",added_at:t2,…}]`.
  Missing stamps fall back to the row's `created_at`.
- add `option_sets.next_item_id` = count + 1; drop `item_added_at`.
- `list_snapshots.items` migrate the same way (string arrays today) so restore stays consistent.
- Recipe manifests still author `items: ["Heads","Tails"]` as friendly string arrays; the installer
  wraps them into objects at install time. **The recipe manifest schema is unchanged.**
- Chat append + list_writer still take a value string and wrap it: `{id: next, value, added_at: now,
  label: null, weight: 1, color: null}`.

Per the no-prod-writes rule, this migration runs against prod only on your explicit go, with rollback
tested locally first (the CLAUDE.md DB-change discipline).

### 13.8 Updated scope

On top of the §10 build sequence, items-as-objects adds: the migration (13.7), the object-aware tag
resolver on both server and client (13.4-13.5), and the object-shaped wire payloads (13.6). The
op-wiring and channel / auth / REST work from §10 still stands - it just carries objects now. Realistic
shape: a few days, not one, with the migration as the single riskiest step.

---

*Strawman drafted 2026-05-24, D1-D5 resolved same day. Reacts welcome anywhere. Nothing is built until you say go.*
