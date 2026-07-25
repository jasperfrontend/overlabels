<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import HelpLayout from '@/layouts/HelpLayout.vue';
import Heading from '@/components/Heading.vue';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Help', href: '/help' },
  { title: 'Blocks', href: '/help/blocks' },
];

// A minimal cell-filling block: fills whatever cell area it's placed on.
const fillExample = `<!-- Body -->
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
}`;

// What the compiler does to a block's CSS at placement.
const scopingExample = `/* you write */
:root { --accent: #a78bfa; }
.label { color: var(--accent); }

/* the Builder compiles, for a placement with id a3f2 */
#blk-a3f2 { --accent: #a78bfa; }
#blk-a3f2 .label { color: var(--accent); }`;
</script>

<template>
  <HelpLayout
    :breadcrumbs="breadcrumbs"
    title="Blocks - reusable building pieces for the Builder"
    description="Blocks are the third template type in Overlabels: small, self-contained overlay pieces with live data and controls, built once and placed on any grid in the Builder. How to author one, how CSS scoping and snapshots work, and how controls travel."
    canonical-url="https://overlabels.com/help/blocks"
  >
    <!-- Header -->
    <div class="mb-10">
      <Heading
        title="Blocks"
        title-class="text-4xl font-bold mb-4"
        description="A block is a small, self-contained overlay piece: a follower counter, a donation goal, a chat box frame. Build it once with the same HTML, CSS, and tags as any overlay - then anyone can place it on a grid in the Builder, no code required on their end."
      />
    </div>

    <!-- TOC -->
    <div class="mb-12 border border-sidebar-border bg-card p-6">
      <h2 class="mb-4 text-xl font-bold" id="toc">Table of contents</h2>
      <ol class="list-decimal space-y-1 pl-6 text-foreground">
        <li><a href="#what" class="text-violet-400 hover:underline">The third template type</a></li>
        <li><a href="#creating" class="text-violet-400 hover:underline">Creating a block</a></li>
        <li><a href="#css" class="text-violet-400 hover:underline">Writing block HTML and CSS</a></li>
        <li><a href="#controls" class="text-violet-400 hover:underline">Controls travel with your block</a></li>
        <li><a href="#snapshots" class="text-violet-400 hover:underline">Snapshots: placing copies your code</a></li>
        <li><a href="#publishing" class="text-violet-400 hover:underline">Publishing your block</a></li>
      </ol>
    </div>

    <!-- 1. What -->
    <section class="mb-14" id="what">
      <h2 class="mb-4 text-2xl font-bold">1. The third template type</h2>
      <p class="mb-4 text-foreground">
        Overlabels has three template types. <strong>Static overlays</strong> are the always-on layer you add to OBS.
        <strong>Alerts</strong> fire on events and render inside a static overlay (see
        <Link href="/help/overlays-vs-alerts" class="text-violet-400 hover:underline">Overlays vs Alerts</Link>).
        <strong>Blocks</strong> are the new third kind: pieces. A block is never added to OBS by itself - it exists to
        be placed on a grid in the
        <Link href="/help/builder" class="text-violet-400 hover:underline">Builder</Link>, alongside other blocks,
        compiling into a full static overlay.
      </p>
      <p class="text-foreground">
        Inside a block, everything you already know works: template tags like
        <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">[[[follower_count]]]</code>, conditionals,
        <Link href="/help/formatting" class="text-violet-400 hover:underline">formatting pipes</Link>, and
        <Link href="/help/controls" class="text-violet-400 hover:underline">Controls</Link>. A block is a regular
        template that happens to be small and composable.
      </p>
    </section>

    <!-- 2. Creating -->
    <section class="mb-14" id="creating">
      <h2 class="mb-4 text-2xl font-bold">2. Creating a block</h2>
      <p class="mb-4 text-foreground">
        Two ways in:
      </p>
      <ul class="mb-4 list-disc space-y-2 pl-6 text-foreground">
        <li>
          <strong>From scratch:</strong> Create Overlay and pick the <strong>Block</strong> card. You get the same
          editor as every other template.
        </li>
        <li>
          <strong>From an existing overlay:</strong> the <strong>Copy</strong> action on any static overlay asks
          whether the copy should be a static overlay or a block. Great for turning a piece of the public library
          into Builder material.
        </li>
      </ul>
      <p class="text-foreground">
        On the block's Meta tab you set a <strong>suggested size</strong>: how many grid columns and rows the block
        occupies when someone places it (they can always resize afterwards). A compact counter might suggest 4x2; a
        big centerpiece might want 8x4. If the free space at the clicked cell is smaller, the Builder shrinks the
        placement to fit.
      </p>
    </section>

    <!-- 3. CSS -->
    <section class="mb-14" id="css">
      <h2 class="mb-4 text-2xl font-bold">3. Writing block HTML and CSS</h2>
      <p class="mb-4 text-foreground">
        When a block is placed, it lives inside a wrapper element that spans its grid cells - a box with a definite
        width and height. The single most useful pattern for a block is therefore <em>fill the box you're given</em>:
      </p>
      <pre class="mb-6 overflow-x-auto rounded border border-sidebar-border bg-card p-4 font-mono text-sm text-foreground"><code>{{ fillExample }}</code></pre>
      <p class="mb-4 text-foreground">
        <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">width: 100%; height: 100%</code> on your
        root element makes the block fill whatever cell area a user gives it - 2x2 or 8x4, it adapts. Content-sized
        blocks are fine too; they'll simply sit at their natural size inside their area.
      </p>

      <h3 class="mb-2 text-xl font-semibold">Your CSS is scoped for you</h3>
      <p class="mb-4 text-foreground">
        Write plain CSS with whatever class names you like. At compile time, the Builder prefixes every selector to
        the block's own wrapper, so two blocks both styling
        <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">.label</code> can never collide.
        Selectors targeting <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">:root</code>,
        <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">html</code>, or
        <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">body</code> are mapped onto the wrapper -
        so CSS variables you define on <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">:root</code>
        become variables on your block, private to it:
      </p>
      <pre class="mb-6 overflow-x-auto rounded border border-sidebar-border bg-card p-4 font-mono text-sm text-foreground"><code>{{ scopingExample }}</code></pre>
      <div class="rounded-lg border border-yellow-500/40 bg-yellow-500/5 p-5">
        <p class="text-foreground">
          <strong>Two limits to know:</strong>
          <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">@keyframes</code> and
          <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">@font-face</code> pass through globally,
          so give animations distinctive names - two blocks defining
          <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">@keyframes pulse</code> will fight over
          it. And keep CSS reasonably flat: media queries are handled, but deeply nested exotic constructs may degrade
          to plain prefixing.
        </p>
      </div>
    </section>

    <!-- 4. Controls -->
    <section class="mb-14" id="controls">
      <h2 class="mb-4 text-2xl font-bold">4. Controls travel with your block</h2>
      <p class="mb-4 text-foreground">
        Add controls to your block exactly as you would on any overlay, and reference them with
        <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">[[[c:your_key]]]</code>. When someone
        places your block and saves, the controls it needs are created on their overlay automatically, with your
        defaults - the block arrives batteries included.
      </p>
      <ul class="list-disc space-y-2 pl-6 text-foreground">
        <li>
          If a control with the same key already exists on the overlay, it's <strong>shared</strong>, not duplicated.
          Blocks that agree on a key stay in sync - deliberate, and worth designing for: a generic key like
          <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">donation_total</code> invites sharing, a
          specific one like <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">subathon_end_at</code>
          keeps to itself.
        </li>
        <li>
          Integration-managed controls (Ko-fi, StreamLabs, and friends) are account-wide already and don't travel with
          blocks - only your own custom controls do.
        </li>
      </ul>
    </section>

    <!-- 5. Snapshots -->
    <section class="mb-14" id="snapshots">
      <h2 class="mb-4 text-2xl font-bold">5. Snapshots: placing copies your code</h2>
      <p class="mb-4 text-foreground">
        When someone places your block, the Builder copies your code <em>at that moment</em> into their overlay. From
        then on, their overlay never references your block again. You can edit your block freely, rename it, even
        delete it - overlays that placed it keep working, byte for byte, forever.
      </p>
      <div class="mb-4 rounded-lg border border-violet-400/40 bg-violet-400/5 p-5">
        <p class="text-foreground">
          This is a promise in both directions: <strong>your edits can never break someone's live stream</strong>, and
          nobody's overlay can be changed behind their back. Trust by construction, not by policy.
        </p>
      </div>
      <p class="text-foreground">
        Updates still flow - just explicitly. When a placed block's source has newer code, the Builder shows a
        "Source updated" badge on the placement and offers <strong>Refresh from source</strong>: one click re-takes
        the snapshot in place, keeping position and size. Renames are even gentler - a block's name is just a label,
        so placements pick up your new name automatically.
      </p>
    </section>

    <!-- 6. Publishing -->
    <section class="mb-14" id="publishing">
      <h2 class="mb-4 text-2xl font-bold">6. Publishing your block</h2>
      <p class="mb-4 text-foreground">
        Flip your block to <strong>public</strong> and it appears in everyone's Builder picker, searchable by name and
        description. A good public block ships with:
      </p>
      <ul class="mb-4 list-disc space-y-2 pl-6 text-foreground">
        <li>a clear name and a description that says what it shows and which controls it uses,</li>
        <li>a sensible suggested size,</li>
        <li>fill-the-box CSS (see section 3) so it looks right at any placement size,</li>
        <li>sane control defaults, so it renders something meaningful the moment it's placed.</li>
      </ul>
      <p class="text-foreground">
        Private blocks are just as useful - build a personal kit of pieces and recompose your own overlays in minutes
        instead of hours.
      </p>
    </section>

    <!-- Bottom line -->
    <div class="mb-14 rounded-lg border border-violet-400/40 bg-violet-400/5 p-6">
      <p class="mb-3 text-lg font-medium text-foreground">Bottom line</p>
      <p class="text-foreground">
        A block is a regular template with a composable attitude: fill-the-box CSS, scoped styles, controls included,
        snapshot-copied on placement so nothing breaks behind anyone's back. Build one well and it gets placed on
        overlays you'll never see - which is exactly the point. To see the other side of the handshake, read
        <Link href="/help/builder" class="text-violet-400 hover:underline">The Builder</Link>.
      </p>
    </div>
  </HelpLayout>
</template>
