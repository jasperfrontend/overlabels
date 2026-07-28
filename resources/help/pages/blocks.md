---
title: Blocks - reusable building pieces for the Builder
description: Blocks are the third template type in Overlabels: small, self-contained overlay pieces with live data and controls, built once and placed on any grid in the Builder. How to author one, how CSS scoping and snapshots work, and how controls travel.
heading: Blocks
lead: A block is a small, self-contained overlay piece: a follower counter, a donation goal, anything that can live within Overlabels. Build it once with the same HTML, CSS, and tags as any overlay - then anyone can place it on a grid in the Builder, no code required on their end.
canonical: https://overlabels.com/help/blocks
context: templates.index?type=block, templates.show?type=block, templates.blocks.library, builder.create
---

## 1. The third template type

Overlabels has three template types. **Static overlays** are the always-on layer you add to OBS.
**Alerts** fire on events and render inside a static overlay (see
[Overlays vs Alerts](/help/overlays-vs-alerts)).
**Blocks** are the new third kind: pieces. A block is never added to OBS by itself - it exists to
be placed on a grid in the [Builder](/help/builder), alongside other blocks, compiling into a full
static overlay.

Inside a block, everything you already know works: template tags like `[[[follower_count]]]`,
conditionals, [formatting pipes](/help/formatting), and [Controls](/help/controls). A block is a
regular template that happens to be small and composable.

## 2. Creating a block

Two ways in:

- **From scratch:** Create Overlay and pick the **Block** card. You get the same editor as every
  other template.
- **From an existing overlay:** the **Copy** action on any static overlay asks whether the copy
  should be a static overlay or a block. Great for turning a piece of the public library into
  Builder material.

On the block's Meta tab you set a **suggested size**: how many grid columns and rows the block
occupies when someone places it (they can always resize afterwards). A compact counter might suggest
4x2; a big centerpiece might want 8x4. If the free space at the clicked cell is smaller, the Builder
shrinks the placement to fit.

## 3. Writing block HTML and CSS

When a block is placed, it lives inside a wrapper element that spans its grid cells - a box with a
definite width and height. The single most useful pattern for a block is therefore *fill the box
you're given*:

```html
<!-- Body -->
<div class="item">
  [[[follower_count]]] followers
</div>

/* CSS */
.item {
  width: 100%;
  height: 100%;
  display: grid;
  place-content: center;
  background-color: #15803d;
  color: #ffffff;
  font-size: 1.875rem;
}
```

`width: 100%; height: 100%` on your root element makes the block fill whatever cell area a user
gives it - 2x2 or 8x4, it adapts. Content-sized blocks are fine too; they'll simply sit at their
natural size inside their area.

### Your CSS is scoped for you

Write plain CSS with whatever class names you like. At compile time, the Builder prefixes every
selector to the block's own wrapper, so two blocks both styling `.label` can never collide.
Selectors targeting `:root`, `html`, or `body` are mapped onto the wrapper - so CSS variables you
define on `:root` become variables on your block, private to it:

```css
/* you write */
:root { --accent: #a78bfa; }
.label { color: var(--accent); }

/* the Builder compiles, for a placement with id a3f2 */
#blk-a3f2 { --accent: #a78bfa; }
#blk-a3f2 .label { color: var(--accent); }
```

> [!WARNING]
> **Two limits to know:** `@keyframes` and `@font-face` pass through globally, so give animations
> distinctive names - two blocks defining `@keyframes pulse` will fight over it. And keep CSS
> reasonably flat: media queries are handled, but deeply nested exotic constructs may degrade to
> plain prefixing.

## 4. Controls travel with your block

Add controls to your block exactly as you would on any overlay, and reference them with
`[[[c:your_key]]]`. When someone places your block and saves, the controls it needs are created on
their overlay automatically, with your defaults - the block arrives batteries included.

- If a control with the same key already exists on the overlay, it's **shared**, not duplicated.
  Blocks that agree on a key stay in sync - deliberate, and worth designing for: a generic key like
  `donation_total` invites sharing, a specific one like `subathon_end_at` keeps to itself.
- Integration-managed controls (Ko-fi, StreamLabs, and friends) are account-wide already and don't
  travel with blocks - only your own custom controls do.

## 5. Snapshots: placing copies your code

When someone places your block, the Builder copies your code *at that moment* into their overlay.
From then on, their overlay never references your block again. You can edit your block freely,
rename it, even delete it - overlays that placed it keep working, byte for byte, forever.

> [!NOTE]
> This is a promise in both directions: **your edits can never break someone's live stream**, and
> nobody's overlay can be changed behind their back. Trust by construction, not by policy.

Updates still flow - just explicitly. When a placed block's source has newer code, the Builder shows
a "Source updated" badge on the placement and offers **Refresh from source**: one click re-takes the
snapshot in place, keeping position and size. Renames are even gentler - a block's name is just a
label, so placements pick up your new name automatically.

## 6. Publishing your block

Flip your block to **public** and it appears in everyone's Builder picker, searchable by name and
description. A good public block ships with:

- a clear name and a description that says what it shows and which controls it uses,
- a sensible suggested size,
- fill-the-box CSS (see section 3) so it looks right at any placement size,
- sane control defaults, so it renders something meaningful the moment it's placed.

Private blocks are just as useful - build a personal kit of pieces and recompose your own overlays
in minutes instead of hours.

> [!IMPORTANT]
> **Bottom line.** A block is a regular template with a composable attitude: fill-the-box CSS, scoped
> styles, controls included, snapshot-copied on placement so nothing breaks behind anyone's back.
> Build one well and it gets placed on overlays you'll never see - which is exactly the point. To see
> the other side of the handshake, read [The Builder](/help/builder).
