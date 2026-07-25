<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import HelpLayout from '@/layouts/HelpLayout.vue';
import Heading from '@/components/Heading.vue';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Help', href: '/help' },
  { title: 'The Builder', href: '/help/builder' },
];

// What a composed overlay actually compiles to: a fixed grid container and
// one wrapper per placed block. Nothing magical, nothing proprietary.
const compiledExample = `#builder-root {
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

/* each block's own CSS follows, scoped to its wrapper */`;
</script>

<template>
  <HelpLayout
    :breadcrumbs="breadcrumbs"
    title="The Builder - compose an overlay without writing code"
    description="Set up a grid, click a cell, pick a block, save. The Builder compiles your composition into a plain static overlay that works with everything else in Overlabels - OBS, alerts, controls, live data."
    canonical-url="https://overlabels.com/help/builder"
  >
    <!-- Header -->
    <div class="mb-10">
      <Heading
        title="The Builder"
        title-class="text-4xl font-bold mb-4"
        description="Overlabels is HTML and CSS all the way down - but you don't have to write any of it. The Builder lets you compose a full overlay by placing ready-made blocks on a grid. Set up the grid, click a cell, pick a block, save. Done."
      />
    </div>

    <!-- TOC -->
    <div class="mb-12 border border-sidebar-border bg-card p-6">
      <h2 class="mb-4 text-xl font-bold" id="toc">Table of contents</h2>
      <ol class="list-decimal space-y-1 pl-6 text-foreground">
        <li><a href="#what" class="text-violet-400 hover:underline">What the Builder is</a></li>
        <li><a href="#composing" class="text-violet-400 hover:underline">Composing: the grid and the picker</a></li>
        <li><a href="#arranging" class="text-violet-400 hover:underline">Arranging: drag, keys, preview</a></li>
        <li><a href="#saving" class="text-violet-400 hover:underline">Saving: you get a plain static overlay</a></li>
        <li><a href="#controls" class="text-violet-400 hover:underline">Controls come along</a></li>
        <li><a href="#editing" class="text-violet-400 hover:underline">Editing later, refreshing blocks, ejecting</a></li>
      </ol>
    </div>

    <!-- 1. What -->
    <section class="mb-14" id="what">
      <h2 class="mb-4 text-2xl font-bold">1. What the Builder is</h2>
      <p class="mb-4 text-foreground">
        The Builder is a visual composer for static overlays. You place
        <Link href="/help/blocks" class="text-violet-400 hover:underline">blocks</Link> - small, reusable overlay
        pieces made by you or by the community - on a CSS grid sized to a 1920x1080 canvas, exactly the frame OBS
        renders. Every block already knows how to show live data: follower counts, donation goals, timers, whatever
        its author wired up.
      </p>
      <p class="text-foreground">
        The important part is what the Builder is <em>not</em>: it's not a separate kind of overlay with its own
        runtime. When you save, your composition compiles down to the same HTML and CSS every hand-written overlay is
        made of. Everything that works for a hand-written static overlay works for a composed one, automatically.
      </p>
    </section>

    <!-- 2. Composing -->
    <section class="mb-14" id="composing">
      <h2 class="mb-4 text-2xl font-bold">2. Composing: the grid and the picker</h2>
      <p class="mb-4 text-foreground">
        Open the Builder from the templates page (the <strong>Builder</strong> button) or the command palette. You
        start with a 12x8 grid - presets for 6x4 and 16x9 are one click away, and you can set anything up to 24x24
        plus the gap between cells. The canvas behind the grid is always 1920x1080, so what you see is
        pixel-true to what OBS will render.
      </p>
      <p class="mb-4 text-foreground">
        Click any empty cell and the block picker opens: your own blocks plus every public block from the community,
        searchable. Pick one and it lands on that cell at its suggested size, shrunk if needed to fit the free space.
        Each placed block shows a live preview with sample data, so the canvas always looks like a real overlay
        instead of a wireframe.
      </p>
      <div class="rounded-lg border border-violet-400/40 bg-violet-400/5 p-5">
        <p class="text-foreground">
          Saw a full static overlay you like? Its <strong>Copy</strong> action can copy it <em>as a block</em> - which
          means the entire public overlay library is potential Builder material, not just things that were born as
          blocks.
        </p>
      </div>
    </section>

    <!-- 3. Arranging -->
    <section class="mb-14" id="arranging">
      <h2 class="mb-4 text-2xl font-bold">3. Arranging: drag, keys, preview</h2>
      <ul class="mb-4 list-disc space-y-2 pl-6 text-foreground">
        <li><strong>Drag</strong> a block to move it. It snaps cell to cell and refuses to overlap another block - it simply stops at the last valid spot.</li>
        <li><strong>Click</strong> a block to select it. The side panel shows move and resize buttons plus Remove.</li>
        <li><strong>Arrow keys</strong> move the selected block, <strong>Shift + arrows</strong> resize it, <strong>Delete</strong> removes it.</li>
        <li><strong>Preview</strong> renders the actual compiled output with sample data in a dialog - the same code that will ship to OBS.</li>
      </ul>
      <p class="text-foreground">
        Blocks can never overlap, by design. Every position and size runs through the same occupancy check, whether
        it came from a drag, a button, or a keyboard shortcut. A composed overlay is always a clean, gap-respecting
        grid.
      </p>
    </section>

    <!-- 4. Saving -->
    <section class="mb-14" id="saving">
      <h2 class="mb-4 text-2xl font-bold">4. Saving: you get a plain static overlay</h2>
      <p class="mb-4 text-foreground">
        Hitting Save compiles the grid and every placed block into one static overlay. The output is genuinely simple -
        a fixed 1920x1080 grid container and one wrapper per block:
      </p>
      <pre class="mb-4 overflow-x-auto rounded border border-sidebar-border bg-card p-4 font-mono text-sm text-foreground"><code>{{ compiledExample }}</code></pre>
      <p class="mb-4 text-foreground">
        Because the result is an ordinary static overlay, the whole platform just works on it:
        <strong>Add to OBS</strong> gives you the browser-source URL with your access token,
        <Link href="/help/overlays-vs-alerts" class="text-violet-400 hover:underline">alerts</Link> can target it,
        live values update over WebSockets, and it shows up in your overlay list like any other. There is no
        "Builder runtime" - just HTML and CSS you could have written by hand, if you had the time and the inclination.
      </p>
    </section>

    <!-- 5. Controls -->
    <section class="mb-14" id="controls">
      <h2 class="mb-4 text-2xl font-bold">5. Controls come along</h2>
      <p class="mb-4 text-foreground">
        Blocks that use <Link href="/help/controls" class="text-violet-400 hover:underline">Controls</Link> bring them
        with them: when you save, any control a placed block needs is created on your overlay automatically. On the
        overlay's Controls tab, each of those controls wears a little pill naming the block that uses it, so you always
        know which control drives what.
      </p>
      <ul class="mb-4 list-disc space-y-2 pl-6 text-foreground">
        <li>
          <strong>Blocks sharing a control key stay in sync.</strong> Place two blocks that both use
          <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">donation_total</code> and they share one
          control - update it once, both blocks update. That's a feature, not an accident.
        </li>
        <li>
          <strong>Removing a block keeps its controls.</strong> Your counter at 500 survives a layout experiment.
          Leftovers are flagged "Not used by any block" on the Controls tab, where deleting them is one click - your
          call, never automatic.
        </li>
      </ul>
    </section>

    <!-- 6. Editing later -->
    <section class="mb-14" id="editing">
      <h2 class="mb-4 text-2xl font-bold">6. Editing later, refreshing blocks, ejecting</h2>
      <p class="mb-4 text-foreground">
        Reopen a composed overlay's edit page and the Code tab shows the grid editor again, with your layout exactly
        as you left it. Two things worth knowing:
      </p>
      <ul class="mb-4 list-disc space-y-2 pl-6 text-foreground">
        <li>
          <strong>Refresh from source.</strong> Placing a block copies its code at that moment, so a block author
          editing their block can never silently change your overlay. When a source block <em>has</em> changed, the
          editor tells you - a "Source updated" badge on the placement and a sync bar above the canvas - and updating
          to the new version is one click, keeping the block's position and size. Nothing changes until you save.
        </li>
        <li>
          <strong>Open in code editor.</strong> The overlay's actions menu can convert a composed overlay to a
          hand-edited one: you get the full compiled HTML and CSS in the regular code editor, and the grid tools go
          away for that overlay. It's a one-way door - great when you've outgrown the grid, permanent once you walk
          through it.
        </li>
      </ul>
      <div class="rounded-lg border border-yellow-500/40 bg-yellow-500/5 p-5">
        <p class="text-foreground">
          <strong>Heads up:</strong> ejecting to the code editor cannot be undone. The compiled code stays exactly as
          it was - you lose nothing except the grid editing tools for that overlay.
        </p>
      </div>
    </section>

    <!-- Bottom line -->
    <div class="mb-14 rounded-lg border border-violet-400/40 bg-violet-400/5 p-6">
      <p class="mb-3 text-lg font-medium text-foreground">Bottom line</p>
      <p class="text-foreground">
        The Builder is assembly, not magic: blocks on a grid, compiled to the same plain HTML and CSS everything else
        in Overlabels runs on. Compose in minutes, keep every power feature, and if you ever want to see how the
        sausage is made - preview it, or eject and read the code. Want to make blocks of your own? That's the
        <Link href="/help/blocks" class="text-violet-400 hover:underline">Blocks</Link> page.
      </p>
    </div>
  </HelpLayout>
</template>
