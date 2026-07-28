---
title: The Builder - compose an overlay without writing code
description: "Set up a grid, click a cell, pick a block, save. The Builder compiles your composition into a plain static overlay that works with everything else in Overlabels - OBS, alerts, controls, live data."
heading: The Builder
lead: "Overlabels is HTML and CSS all the way down - but you don't have to write any of it. The Builder lets you compose a full overlay by placing ready-made blocks on a grid. Set up the grid, click a cell, pick a block, save. Done."
canonical: https://overlabels.com/help/builder
---

## 1. What the Builder is

The Builder is a visual composer for static overlays. You place [blocks](/help/blocks) - small, reusable
overlay pieces made by you or by the community - on a CSS grid sized to a 1920x1080 canvas, exactly the
frame OBS renders. Every block already knows how to show live data: follower counts, donation goals,
timers, whatever its author wired up.

The important part is what the Builder is *not*: it's not a separate kind of overlay with its own
runtime. When you save, your composition compiles down to the same HTML and CSS every hand-written
overlay is made of. Everything that works for a hand-written static overlay works for a composed one,
automatically.

## 2. Composing: the grid and the picker

Open the Builder from the templates page (the **Builder** button) or the command palette. You start with
a 12x8 grid - presets for 6x4 and 16x9 are one click away, and you can set anything up to 24x24 plus the
gap between cells. The canvas behind the grid is always 1920x1080, so what you see is pixel-true to what
OBS will render.

Click any empty cell and the block picker opens: your own blocks plus every public block from the
community, searchable. Pick one and it lands on that cell at its suggested size, shrunk if needed to fit
the free space. Each placed block shows a live preview with sample data, so the canvas always looks like
a real overlay instead of a wireframe.

> [!NOTE]
> Saw a full static overlay you like? Its **Copy** action can copy it *as a block* - which means the
> entire public overlay library is potential Builder material, not just things that were born as blocks.

## 3. Arranging: drag, keys, preview

- **Drag** a block to move it. It snaps cell to cell and refuses to overlap another block - it simply
  stops at the last valid spot.
- **Click** a block to select it. The side panel shows move and resize buttons plus Remove.
- **Arrow keys** move the selected block, **Shift + arrows** resize it, **Delete** removes it.
- **Preview** renders the actual compiled output with sample data in a dialog - the same code that will
  ship to OBS.

Blocks can never overlap, by design. Every position and size runs through the same occupancy check,
whether it came from a drag, a button, or a keyboard shortcut. A composed overlay is always a clean,
gap-respecting grid.

## 4. Saving: you get a plain static overlay

Hitting Save compiles the grid and every placed block into one static overlay. The output is genuinely
simple - a fixed 1920x1080 grid container and one wrapper per block:

```css
#builder-root {
  position: fixed;
  width: 1920px;
  height: 1080px;
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  grid-template-rows: repeat(8, 1fr);
  gap: 8px;
}

#blk-a3f2 { grid-area: 1 / 1 / span 2 / span 4; }
#blk-9k1x { grid-area: 3 / 5 / span 3 / span 4; }

/* each block's own CSS follows, scoped to its wrapper */
```

Because the result is an ordinary static overlay, the whole platform just works on it: **Add to OBS**
gives you the browser-source URL with your access token, [alerts](/help/overlays-vs-alerts) can target
it, live values update over WebSockets, and it shows up in your overlay list like any other. There is no
"Builder runtime" - just HTML and CSS you could have written by hand, if you had the time and the
inclination.

## 5. Controls come along

Blocks that use [Controls](/help/controls) bring them with them: when you save, any control a placed
block needs is created on your overlay automatically. On the overlay's Controls tab, each of those
controls wears a little pill naming the block that uses it, so you always know which control drives what.

- **Blocks sharing a control key stay in sync.** Place two blocks that both use `donation_total` and
  they share one control - update it once, both blocks update. That's a feature, not an accident.
- **Removing a block keeps its controls.** Your counter at 500 survives a layout experiment. Leftovers
  are flagged "Not used by any block" on the Controls tab, where deleting them is one click - your call,
  never automatic.

## 6. Editing later, refreshing blocks, ejecting

Reopen a composed overlay's edit page and the Code tab shows the grid editor again, with your layout
exactly as you left it. Two things worth knowing:

- **Refresh from source.** Placing a block copies its code at that moment, so a block author editing
  their block can never silently change your overlay. When a source block *has* changed, the editor tells
  you - a "Source updated" badge on the placement and a sync bar above the canvas - and updating to the
  new version is one click, keeping the block's position and size. Nothing changes until you save.
- **Open in code editor.** The overlay's actions menu can convert a composed overlay to a hand-edited
  one: you get the full compiled HTML and CSS in the regular code editor, and the grid tools go away for
  that overlay. It's a one-way door - great when you've outgrown the grid, permanent once you walk
  through it.

> [!WARNING]
> **Heads up:** ejecting to the code editor cannot be undone. The compiled code stays exactly as it was -
> you lose nothing except the grid editing tools for that overlay.

> [!IMPORTANT]
> **Bottom line.** The Builder is assembly, not magic: blocks on a grid, compiled to the same plain HTML
> and CSS everything else in Overlabels runs on. Compose in minutes, keep every power feature, and if you
> ever want to see how the sausage is made - preview it, or eject and read the code. Want to make blocks
> of your own? That's the [Blocks](/help/blocks) page.
