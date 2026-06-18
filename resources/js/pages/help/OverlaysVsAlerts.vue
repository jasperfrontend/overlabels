<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Help', href: '/help' },
  { title: 'Overlays vs Alerts', href: '/help/overlays-vs-alerts' },
];

// A static overlay: always-on HTML, with its own CSS scaffolding and variables.
const staticExample = `<style>
  :root { --brand: #6d28ff; }
  .avatar { border: 3px solid var(--brand); }
</style>

<body class="overlay">
  <div class="hud">
    <img class="avatar" src="logo.png" />
    <span class="followers">[[[follower_count]]]</span>
  </div>
</body>`;

// The same alert rendered INSIDE the static overlay it targets: appended to the
// same document, just before </body>, so it inherits the static overlay's CSS.
const insideExample = `<body class="overlay">
  <!-- your static overlay's own HTML -->
  <div class="hud">
    <img class="avatar" src="logo.png" />
    <span class="followers">1,337</span>
  </div>

  <!-- your alert is appended here, just before </body> -->
  <div class="alert">
    <img class="avatar" src="newsub.png" />
    <strong>NightboticaLIVE</strong> just subscribed!
  </div>
</body>`;

// The same alert added directly to OBS as its own browser source: it renders
// alone, with none of the static overlay's variables or scaffolding around it.
const standaloneExample = `<body>
  <!-- nothing else: the alert is alone in its own browser source -->
  <div class="alert">
    <img class="avatar" src="newsub.png" />
    <strong>NightboticaLIVE</strong> just subscribed!
  </div>
</body>`;
</script>

<template>
  <Head>
    <title>Overlays vs Alerts - how alerts render inside your static overlay</title>
    <meta
      name="description"
      content="The difference between a static overlay and an alert in Overlabels, why alerts are most powerful rendered inside a static overlay's DOM, and how Targeting and Triggers fit together."
    />

    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://overlabels.com/help/overlays-vs-alerts" />
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" content="Overlays vs Alerts - how alerts render inside your static overlay" />
    <meta
      property="og:description"
      content="Why alerts are most powerful rendered inside a static overlay's DOM, and how Targeting and Triggers fit together."
    />
    <meta property="og:image"
          content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="Overlabels - build Twitch overlays with HTML, CSS, and live data" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Overlays vs Alerts - how alerts render inside your static overlay" />
    <meta
      name="twitter:description"
      content="Why alerts are most powerful rendered inside a static overlay's DOM, and how Targeting and Triggers fit together."
    />
    <meta name="twitter:image"
          content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta name="twitter:image:alt" content="Overlabels - build Twitch overlays with HTML, CSS, and live data" />
  </Head>

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="min-h-screen bg-background">
      <div class="mx-auto max-w-4xl p-6">

        <!-- Header -->
        <div class="mb-10">
          <Heading
            title="Overlays vs Alerts"
            title-class="text-4xl font-bold mb-4"
            description="Overlabels has two kinds of overlay: the always-on static overlay, and the one-shot alert. They look similar in the editor, but they're meant to work together - and understanding how is the difference between a uniform, polished overlay and a bunch of disconnected boxes."
          />
        </div>

        <!-- TOC -->
        <div class="mb-12 border border-sidebar-border bg-card p-6">
          <h2 class="mb-4 text-xl font-bold" id="toc">Table of contents</h2>
          <ol class="list-decimal space-y-1 pl-6 text-foreground">
            <li><a href="#two-kinds" class="text-violet-400 hover:underline">The two kinds: static and alert</a></li>
            <li><a href="#inside" class="text-violet-400 hover:underline">Alerts render inside your static overlay</a></li>
            <li><a href="#standalone" class="text-violet-400 hover:underline">Adding an alert straight to OBS</a></li>
            <li><a href="#targeting-triggers" class="text-violet-400 hover:underline">Targeting vs Triggers</a></li>
          </ol>
        </div>

        <!-- 1. Two kinds -->
        <section class="mb-14" id="two-kinds">
          <h2 class="mb-4 text-2xl font-bold">1. The two kinds: static and alert</h2>
          <div class="mb-4 border border-sidebar-border bg-card p-6">
            <h3 class="mb-2 text-xl font-semibold">Static overlay</h3>
            <p class="text-foreground">
              The always-on layer you add to OBS as a browser source. Follower counters, donation goals, current game,
              your webcam frame, a subathon timer. It sits on screen for hours, and live values mutate inside it. This
              is also where your shared styling lives - fonts, colors, CSS variables, the scaffolding everything else
              hangs off.
            </p>
          </div>

          <div class="border border-sidebar-border bg-card p-6">
            <h3 class="mb-2 text-xl font-semibold">Alert</h3>
            <p class="text-foreground">
              The one-shot layer. It fires when an event arrives (a follow, a sub, a raid, a Ko-fi donation), shows the
              event data for a few seconds, and disappears. An alert is not a standalone scene - it's designed to render
              <em>inside</em> a static overlay you've already built.
            </p>
          </div>
        </section>

        <!-- 2. Inside -->
        <section class="mb-14" id="inside">
          <h2 class="mb-4 text-2xl font-bold">2. Alerts render inside your static overlay</h2>
          <p class="mb-4 text-foreground">
            Here's the key idea. When an alert is targeted at a static overlay, it doesn't open its own page - it's
            appended into the static overlay's DOM, right before the closing
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">&lt;/body&gt;</code> tag. Same document,
            same stylesheet, same everything.
          </p>

          <p class="mb-2 text-foreground">Say your static overlay defines a brand color and a styled avatar:</p>
          <pre class="mb-6 overflow-x-auto rounded border border-sidebar-border bg-card p-4 font-mono text-sm text-foreground"><code>{{ staticExample }}</code></pre>

          <p class="mb-2 text-foreground">
            When a sub alert fires inside that static overlay, the document looks like this:
          </p>
          <pre class="mb-4 overflow-x-auto rounded border border-sidebar-border bg-card p-4 font-mono text-sm text-foreground"><code>{{ insideExample }}</code></pre>

          <div class="rounded-lg border border-violet-400/40 bg-violet-400/5 p-5">
            <p class="text-foreground">
              Because the alert lives in the <strong>same document</strong>, its
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">.avatar</code> picks up the exact same
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">border: 3px solid var(--brand)</code>
              you defined on the static overlay. Every CSS variable, every class, every font you set up once is
              instantly available to your alert. That's an awesome amount of power: define your structure and styling
              in one place, and your alerts inherit it for free. The result is a beautifully uniform overlay and alert
              system that all rely on the same definitions.
            </p>
          </div>
        </section>

        <!-- 3. Standalone -->
        <section class="mb-14" id="standalone">
          <h2 class="mb-4 text-2xl font-bold">3. Adding an alert straight to OBS</h2>
          <p class="mb-4 text-foreground">
            You <em>can</em> add an alert directly to OBS as its own browser source, and it'll render perfectly fine on
            its own whenever it fires. Who are we to judge - sometimes that's exactly what you want. But be aware of
            what you're giving up. A standalone alert is alone in its own document:
          </p>
          <pre class="mb-4 overflow-x-auto rounded border border-sidebar-border bg-card p-4 font-mono text-sm text-foreground"><code>{{ standaloneExample }}</code></pre>
          <p class="text-foreground">
            No <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">--brand</code> variable, no
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">.avatar</code> rule, none of your static
            overlay's scaffolding. The alert still works, but you style it from scratch and it won't automatically match
            the rest of your overlay. If you want that uniform look, render it inside a static overlay instead (see
            above).
          </p>
        </section>

        <!-- 4. Targeting vs Triggers -->
        <section class="mb-14" id="targeting-triggers">
          <h2 class="mb-4 text-2xl font-bold">4. Targeting vs Triggers</h2>
          <p class="mb-4 text-foreground">
            To render an alert inside a static overlay, open the alert and visit the <strong>Targeting</strong> tab,
            then choose one or more static overlays where this alert should render.
          </p>
          <div class="mb-4 rounded-lg border border-yellow-500/40 bg-yellow-500/5 p-5">
            <p class="text-foreground">
              <strong>Heads up:</strong> if you don't set a target overlay, your alert renders in
              <strong>ALL</strong> of your static overlays. That's usually not what you want - pick your targets
              deliberately.
            </p>
          </div>
          <p class="mb-3 text-foreground">
            It's easy to mix up the two alert tabs, so to be clear:
          </p>
          <ul class="list-disc space-y-2 pl-6 text-foreground">
            <li><strong>Triggers</strong> decide <em>on which event</em> this alert fires (a follow, a sub, a Ko-fi donation, and so on).</li>
            <li><strong>Targeting</strong> decides <em>in which static overlay</em> this alert renders.</li>
          </ul>
        </section>

        <!-- Bottom line -->
        <div class="mb-14 rounded-lg border border-violet-400/40 bg-violet-400/5 p-6">
          <p class="mb-3 text-lg font-medium text-foreground">Bottom line</p>
          <p class="text-foreground">
            Build your structure and styling once in a static overlay, then target your alerts at it so they render in
            the same DOM and inherit everything. Adding an alert straight to OBS is valid too - it just renders on its
            own, without your shared scaffolding. Either path works. This is indeed a mouthful, but hey: Overlabels is a
            mouthful. Enjoy, whichever path you choose. When you're ready, the
            <Link href="/help/for-designers" class="text-violet-400 hover:underline">For Designers</Link> and
            <Link href="/help/conditionals" class="text-violet-400 hover:underline">Conditional and Event Tags</Link>
            pages go deeper on building each surface.
          </p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
