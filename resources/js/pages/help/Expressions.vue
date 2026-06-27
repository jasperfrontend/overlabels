<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Help', href: '/help' },
  { title: 'Expression Controls', href: '/help/expressions' },
];
</script>

<template>
  <Head>
    <title>Expression Controls - Overlabels</title>
    <meta
      name="description"
      content="Expression Controls in Overlabels: math-powered live data with no code and no server. Build chained formulas like the Haversine distance, progress bars, and more, evaluated live as your data changes."
    />

    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://overlabels.com/help/expressions" />
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" content="Expression Controls - Overlabels" />
    <meta
      property="og:description"
      content="Math-powered live data, no code and no server. Build chained formulas like the Haversine distance and progress bars, evaluated live as your data changes."
    />
    <meta property="og:image"
          content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="Overlabels - build Twitch overlays with HTML, CSS, and live data" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Expression Controls - Overlabels" />
    <meta
      name="twitter:description"
      content="Math-powered live data, no code and no server. Build chained formulas like the Haversine distance and progress bars, evaluated live as your data changes."
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
            title="Expression Controls"
            title-class="text-4xl font-bold mb-4"
            description="Math-powered live data, no code and no server. Build chained formulas like the Haversine distance and progress bars, evaluated live as your data changes."
          />
        </div>

        <!-- TOC -->
        <div class="mb-12 border border-sidebar-border bg-card p-6">
          <h2 class="mb-4 text-xl font-bold" id="toc">Table of contents</h2>
          <ol class="list-decimal space-y-1 pl-6 text-foreground">
            <li><a href="#what" class="text-violet-400 hover:underline">What is an Expression Control?</a></li>
            <li><a href="#syntax" class="text-violet-400 hover:underline">Syntax basics</a></li>
            <li><a href="#referencing" class="text-violet-400 hover:underline">Referencing Controls in expressions</a></li>
            <li><a href="#functions" class="text-violet-400 hover:underline">Available functions</a></li>
            <li><a href="#worked-example" class="text-violet-400 hover:underline">Worked example: GPS distance to destination</a></li>
            <li><a href="#things-to-know" class="text-violet-400 hover:underline">Things to know</a></li>
            <li><a href="#quick-ref" class="text-violet-400 hover:underline">Quick reference card</a></li>
          </ol>
        </div>

        <!-- What is an Expression Control? -->
        <section class="mb-14" id="what">
          <h2 class="mb-4 text-2xl font-bold">What is an Expression Control?</h2>
          <p class="mb-4 text-foreground">
            An Expression Control is a Control whose value is computed from a formula you write. Instead of typing a static
            number or text, you write a math expression - and Overlabels evaluates it live, every time any of the values it
            references change.
          </p>
          <p class="text-foreground">
            The result behaves like any other Control. You reference it in your overlay HTML/CSS with
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">[[[c:your_control_name]]]</code>.
            You can reference it from <em>other</em> Expression Controls too. It's just a number, computed automatically.
          </p>
        </section>

        <!-- Syntax basics -->
        <section class="mb-14" id="syntax">
          <h2 class="mb-4 text-2xl font-bold">Syntax basics</h2>
          <p class="mb-4 text-foreground">
            Inside an Expression Control, you write a math expression using:
          </p>
          <ul class="mb-4 list-disc space-y-2 pl-6 text-foreground">
            <li>
              <strong>Bare math functions</strong> -
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">sin(x)</code>,
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">cos(x)</code>,
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">sqrt(x)</code>,
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">atan2(y, x)</code>,
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">abs(x)</code>,
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">round(x)</code>,
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">floor(x)</code>,
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">ceil(x)</code>,
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">tan(x)</code>,
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">asin(x)</code>,
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">acos(x)</code>,
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">atan(x)</code>
            </li>
            <li>
              <strong>Constants</strong> -
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">PI</code>
            </li>
            <li>
              <strong>Standard operators</strong> -
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">+</code>,
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">-</code>,
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">*</code>,
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">/</code>,
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">%</code>
            </li>
            <li>
              <strong>Other Controls</strong> - referenced with
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">c.control_name</code>
            </li>
            <li>
              <strong>Service Controls</strong> - referenced with
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">c.service.key</code>,
              e.g.
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">c.gps.lat</code>,
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">c.kofi.total_received</code>
            </li>
            <li>
              <strong>Twitch Helix data</strong> - referenced with
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">t.</code>, e.g.
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">t.followers_total</code>
            </li>
          </ul>
          <p class="text-foreground">
            You do <strong>not</strong> use
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">Math.sin</code> or
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">Math.PI</code> -
            just <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">sin</code> and
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">PI</code> directly.
          </p>
        </section>

        <!-- Referencing Controls -->
        <section class="mb-14" id="referencing">
          <h2 class="mb-4 text-2xl font-bold">Referencing Controls in expressions</h2>
          <div class="overflow-x-auto border border-sidebar-border bg-card">
            <table class="w-full text-sm">
              <thead class="border-b border-sidebar text-left">
                <tr>
                  <th class="p-3 font-semibold">What you want</th>
                  <th class="p-3 font-semibold">In an expression</th>
                  <th class="p-3 font-semibold">In overlay HTML/CSS</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-sidebar">
                <tr>
                  <td class="p-3 text-foreground">Your own control <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">goal_km</code></td>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">c.goal_km</code></td>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[c:goal_km]]]</code></td>
                </tr>
                <tr>
                  <td class="p-3 text-foreground">GPS latitude</td>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">c.gps.lat</code></td>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[c:gps:lat]]]</code></td>
                </tr>
                <tr>
                  <td class="p-3 text-foreground">GPS session distance</td>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">c.gps.session_distance</code></td>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[c:gps:session_distance]]]</code></td>
                </tr>
                <tr>
                  <td class="p-3 text-foreground">Ko-fi total received</td>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">c.kofi.total_received</code></td>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[c:kofi:total_received]]]</code></td>
                </tr>
                <tr>
                  <td class="p-3 text-foreground">Twitch total followers</td>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">t.followers_total</code></td>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[followers_total]]]</code></td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="mt-4 text-foreground">
            Expression Controls can reference other Expression Controls. The only hard rule:
            <strong>no circular references</strong>. A control cannot reference itself, directly or
            through a chain. Overlabels blocks this.
          </p>
        </section>

        <!-- Available functions -->
        <section class="mb-14" id="functions">
          <h2 class="mb-6 text-2xl font-bold">Available functions</h2>

          <!-- Trig -->
          <h3 class="mb-3 text-xl font-semibold">Trig</h3>
          <div class="mb-8 overflow-x-auto border border-sidebar-border bg-card">
            <table class="w-full text-sm">
              <thead class="border-b border-sidebar text-left">
                <tr>
                  <th class="p-3 font-semibold w-1/3">Function</th>
                  <th class="p-3 font-semibold">What it does</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-sidebar">
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">sin(x)</code></td>
                  <td class="p-3 text-foreground">Sine of x (x in radians)</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">cos(x)</code></td>
                  <td class="p-3 text-foreground">Cosine of x (x in radians)</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">tan(x)</code></td>
                  <td class="p-3 text-foreground">Tangent of x</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">asin(x)</code></td>
                  <td class="p-3 text-foreground">Arcsine - inverse of sin</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">acos(x)</code></td>
                  <td class="p-3 text-foreground">Arccosine - inverse of cos</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">atan(x)</code></td>
                  <td class="p-3 text-foreground">Arctangent - inverse of tan</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">atan2(y, x)</code></td>
                  <td class="p-3 text-foreground">Two-argument arctangent. Handles all quadrants correctly. Use this for angular calculations involving GPS coordinates.</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">sqrt(x)</code></td>
                  <td class="p-3 text-foreground">Square root of x</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Rounding & utility -->
          <h3 class="mb-3 text-xl font-semibold">Rounding and utility</h3>
          <div class="mb-8 overflow-x-auto border border-sidebar-border bg-card">
            <table class="w-full text-sm">
              <thead class="border-b border-sidebar text-left">
                <tr>
                  <th class="p-3 font-semibold w-1/3">Function</th>
                  <th class="p-3 font-semibold">What it does</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-sidebar">
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">abs(x)</code></td>
                  <td class="p-3 text-foreground">Absolute value - strips the sign</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">round(x)</code></td>
                  <td class="p-3 text-foreground">Round to nearest integer</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">round(x, decimals)</code></td>
                  <td class="p-3 text-foreground">
                    Round to N decimal places. Returns a <strong>string</strong>
                    (e.g. <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">round(0.1 + 0.2, 2)</code>
                    -> <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">"0.30"</code>).
                    Because it returns a string, use it last in an expression or use the
                    <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">|round:2</code>
                    pipe in your DSL token instead.
                  </td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">floor(x)</code></td>
                  <td class="p-3 text-foreground">Round down</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">ceil(x)</code></td>
                  <td class="p-3 text-foreground">Round up</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Multi-argument math -->
          <h3 class="mb-3 text-xl font-semibold">Multi-argument math</h3>
          <div class="mb-8 overflow-x-auto border border-sidebar-border bg-card">
            <table class="w-full text-sm">
              <thead class="border-b border-sidebar text-left">
                <tr>
                  <th class="p-3 font-semibold w-1/3">Function</th>
                  <th class="p-3 font-semibold">What it does</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-sidebar">
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">max(a, b, ...)</code></td>
                  <td class="p-3 text-foreground">Highest value among all arguments</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">min(a, b, ...)</code></td>
                  <td class="p-3 text-foreground">Lowest value among all arguments</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">sum(a, b, ...)</code></td>
                  <td class="p-3 text-foreground">Sum of all arguments</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">avg(a, b, ...)</code></td>
                  <td class="p-3 text-foreground">Average of all arguments</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">clamp(x, min, max)</code></td>
                  <td class="p-3 text-foreground">Clamps x between min and max - useful for keeping progress bars between 0 and 100</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Label selectors -->
          <h3 class="mb-3 text-xl font-semibold">Label selectors</h3>
          <p class="mb-4 text-foreground">
            These accept pairs of <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">value, label</code>
            arguments and return the <strong>label</strong> paired with the winning value. Useful for picking a display
            name based on a numeric or timestamp comparison.
          </p>
          <div class="mb-6 overflow-x-auto border border-sidebar-border bg-card">
            <table class="w-full text-sm">
              <thead class="border-b border-sidebar text-left">
                <tr>
                  <th class="p-3 font-semibold w-1/3">Function</th>
                  <th class="p-3 font-semibold">What it does</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-sidebar">
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">latest(v1, l1, v2, l2, ...)</code></td>
                  <td class="p-3 text-foreground">Returns the label paired with the highest value. Use with timestamps to find the most recent event.</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">oldest(v1, l1, v2, l2, ...)</code></td>
                  <td class="p-3 text-foreground">Returns the label paired with the lowest value.</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">argmax(v1, l1, v2, l2, ...)</code></td>
                  <td class="p-3 text-foreground">Returns the label paired with the highest numeric value.</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">argmin(v1, l1, v2, l2, ...)</code></td>
                  <td class="p-3 text-foreground">Returns the label paired with the lowest numeric value.</td>
                </tr>
              </tbody>
            </table>
          </div>

          <p class="mb-3 text-foreground">
            <strong>Example</strong> - show which donation service sent the biggest single donation:
          </p>
          <pre class="mb-6 overflow-x-auto rounded border border-sidebar bg-sidebar p-4 font-mono text-sm text-foreground">argmax(c.kofi.latest_donation_amount, "Ko-fi", c.bmac.latest_donation_amount, "BMAC", c.streamlabs.latest_donation_amount, "Streamlabs")</pre>
          <p class="text-foreground">
            Returns <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">"Ko-fi"</code>,
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">"BMAC"</code>, or
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">"Streamlabs"</code> -
            whichever had the highest last donation.
          </p>

          <!-- Constants -->
          <h3 class="mt-8 mb-3 text-xl font-semibold">Constants</h3>
          <div class="overflow-x-auto border border-sidebar-border bg-card">
            <table class="w-full text-sm">
              <thead class="border-b border-sidebar text-left">
                <tr>
                  <th class="p-3 font-semibold w-1/3">Constant</th>
                  <th class="p-3 font-semibold">Value</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-sidebar">
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">PI</code></td>
                  <td class="p-3 text-foreground">3.14159265...</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Worked example -->
        <section class="mb-14" id="worked-example">
          <h2 class="mb-4 text-2xl font-bold">Worked example: GPS distance to destination</h2>
          <p class="mb-4 text-foreground">
            This is the real-world scenario that motivated the trig functions being added. A streamer is cycling to a
            destination. Their overlay needs to show how far they still have to go, and move a character across the screen
            proportional to their progress.
          </p>
          <p class="mb-6 text-foreground">
            The <strong>Haversine formula</strong> gives you the straight-line distance between two GPS coordinates on
            Earth. Here's how to build it entirely in Expression Controls.
          </p>

          <h3 class="mt-8 mb-3 text-xl font-semibold">Step 1 - Create your static number controls</h3>
          <p class="mb-4 text-foreground">
            Create three <strong>Number Controls</strong> manually. These are the destination coordinates and the length of your trip in km.
            Set them once and leave them.
          </p>
          <div class="mb-8 overflow-x-auto border border-sidebar-border bg-card">
            <table class="w-full text-sm">
              <thead class="border-b border-sidebar text-left">
                <tr>
                  <th class="p-3 font-semibold">Control key</th>
                  <th class="p-3 font-semibold">Value</th>
                  <th class="p-3 font-semibold">What it is</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-sidebar">
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">dest_lat</code></td>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">51.5074</code></td>
                  <td class="p-3 text-foreground">Destination latitude</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">dest_lng</code></td>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">4.3571</code></td>
                  <td class="p-3 text-foreground">Destination longitude</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">goal_km</code></td>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">450</code></td>
                  <td class="p-3 text-foreground">Total distance goal in km</td>
                </tr>
              </tbody>
            </table>
          </div>

          <h3 class="mb-3 text-xl font-semibold">Step 2 - Build the Haversine as chained Expression Controls</h3>
          <p class="mb-6 text-foreground">
            Create each of these as an <strong>Expression Control</strong>, in order. Each one builds on the previous.
          </p>

          <div class="mb-4">
            <p class="mb-2 text-foreground">
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">dLat</code> - latitude delta in radians
            </p>
            <pre class="overflow-x-auto rounded border border-sidebar bg-sidebar p-4 font-mono text-sm text-foreground">(c.dest_lat - c.gps.lat) * PI / 180</pre>
          </div>

          <div class="mb-4">
            <p class="mb-2 text-foreground">
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">dLng</code> - longitude delta in radians
            </p>
            <pre class="overflow-x-auto rounded border border-sidebar bg-sidebar p-4 font-mono text-sm text-foreground">(c.dest_lng - c.gps.lng) * PI / 180</pre>
          </div>

          <div class="mb-4">
            <p class="mb-2 text-foreground">
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">haversine_a</code> - the intermediate value
            </p>
            <pre class="overflow-x-auto rounded border border-sidebar bg-sidebar p-4 font-mono text-sm text-foreground">sin(c.dlat / 2) * sin(c.dlat / 2) + cos(c.gps.lat * PI / 180) * cos(c.dest_lat * PI / 180) * sin(c.dlng / 2) * sin(c.dlng / 2)</pre>
          </div>

          <div class="mb-4">
            <p class="mb-2 text-foreground">
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">distance_to_dest</code> - distance remaining in km
            </p>
            <pre class="overflow-x-auto rounded border border-sidebar bg-sidebar p-4 font-mono text-sm text-foreground">6371 * 2 * atan2(sqrt(c.haversine_a), sqrt(1 - c.haversine_a))</pre>
          </div>

          <div class="mb-8">
            <p class="mb-2 text-foreground">
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">progress_pct</code> - how far through the journey, as a percentage
            </p>
            <pre class="overflow-x-auto rounded border border-sidebar bg-sidebar p-4 font-mono text-sm text-foreground">(c.goal_km - c.distance_to_dest) / c.goal_km * 100</pre>
          </div>

          <h3 class="mb-3 text-xl font-semibold">Step 3 - Use it in your overlay</h3>
          <pre class="mb-6 overflow-x-auto rounded border border-sidebar bg-sidebar p-4 font-mono text-sm text-foreground">&lt;!-- Show remaining distance --&gt;
&lt;p&gt;[[[c:distance_to_dest]]] km to go&lt;/p&gt;

&lt;!-- Move a character across the screen --&gt;
&lt;style&gt;
  .cyclist {
    position: absolute;
    left: calc([[[c:progress_pct]]] * 1%);
    transition: left 2s linear;
  }
&lt;/style&gt;</pre>
          <p class="text-foreground">
            That's it. Every time the GPS app sends a position update,
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">c.gps.lat</code> and
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">c.gps.lng</code> update,
            and the entire chain recomputes automatically - distance, progress, character position. No server roundtrip.
            No JS in the overlay. Pure Controls.
          </p>
        </section>

        <!-- Things to know -->
        <section class="mb-14" id="things-to-know">
          <h2 class="mb-6 text-2xl font-bold">Things to know</h2>

          <div class="space-y-6">
            <div class="border border-sidebar-border bg-card p-6">
              <h3 class="mb-2 text-lg font-semibold">Expressions are evaluated live.</h3>
              <p class="text-foreground">
                When any referenced Control changes value, all Expression Controls that depend on it recompute. This
                cascades through chains - so <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">progress_pct</code>
                recomputes when <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">distance_to_dest</code>
                recomputes, which recomputes when <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">c.gps.lat</code>
                updates.
              </p>
            </div>

            <div class="border border-sidebar-border bg-card p-6">
              <h3 class="mb-2 text-lg font-semibold">GPS controls update on every app ping.</h3>
              <p class="text-foreground">
                The Overlabels GPS Android app sends updates every 2-60 seconds (configurable). Each ping updates
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">c.gps.lat</code>,
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">c.gps.lng</code>,
                etc., which triggers the whole expression chain.
              </p>
            </div>

            <div class="border border-sidebar-border bg-card p-6">
              <h3 class="mb-2 text-lg font-semibold">Angles are in radians.</h3>
              <p class="text-foreground">
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">sin</code>,
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">cos</code>,
                and all trig functions expect radians. To convert degrees to radians:
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">degrees * PI / 180</code>.
                GPS coordinates are in degrees, so always convert before passing them to trig functions.
              </p>
            </div>

            <div class="border border-sidebar-border bg-card p-6">
              <h3 class="mb-2 text-lg font-semibold"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">atan2</code> takes two arguments.</h3>
              <p class="text-foreground">
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">atan2(y, x)</code> -
                not one, two. It's the only function in the set that works this way.
              </p>
            </div>

            <div class="border border-sidebar-border bg-card p-6">
              <h3 class="mb-2 text-lg font-semibold">Expression Controls can reference service presets.</h3>
              <p class="text-foreground">
                Any of the GPS, Twitch, Ko-fi, Streamlabs, StreamElements, Fourthwall, or BMAC preset controls are
                referenceable with <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">c.service.key</code>.
                A donation progress bar that moves toward a goal is just
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">c.kofi.total_received / c.goal_amount * 100</code>.
                See <Link href="/help/integration-presets" class="text-violet-400 hover:underline">Integration Presets</Link>
                for the full catalog.
              </p>
            </div>

            <div class="border border-sidebar-border bg-card p-6">
              <h3 class="mb-2 text-lg font-semibold">No EventSub data directly in expressions yet.</h3>
              <p class="text-foreground">
                EventSub triggers (follows, subs, raids etc.) update the preset Controls, which you <em>can</em>
                reference. But there's no direct <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">e.</code>
                namespace for EventSub in expressions yet - use the presets.
              </p>
            </div>

            <div class="border border-sidebar-border bg-card p-6">
              <h3 class="mb-2 text-lg font-semibold">
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">?? defaults</code> don't touch the math.
              </h3>
              <p class="text-foreground">
                A <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">?? fallback</code> on a template tag
                (like <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">[[[c:hue_base ?? 100]]]</code>)
                is display-only - it fills in literal text when a tag renders <em>empty</em>. It never changes the
                control's stored value, so an Expression Control like
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">c.hue_base + 40</code> keeps computing
                on the real value (an empty control reads as 0 in math). The model: compute first with Expression
                Controls, then catch any empties at display time with
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">??</code> in your template. See
                <Link href="/help/formatting" class="text-violet-400 hover:underline">Formatting Pipes</Link> for the
                full syntax.
              </p>
            </div>
          </div>
        </section>

        <!-- Quick reference -->
        <section class="mb-14" id="quick-ref">
          <h2 class="mb-4 text-2xl font-bold">Quick reference card</h2>
          <pre class="overflow-x-auto border border-sidebar-border bg-sidebar p-4 font-mono text-sm leading-relaxed text-foreground">sin(x)           cos(x)           tan(x)
asin(x)          acos(x)          atan(x)
atan2(y, x)      sqrt(x)          abs(x)
round(x)         round(x, n)      floor(x)         ceil(x)
max(...)         min(...)         sum(...)         avg(...)
clamp(x, min, max)
latest(v, l, ...)   oldest(v, l, ...)
argmax(v, l, ...)   argmin(v, l, ...)
PI

c.my_control          -> your own control
c.gps.lat             -> service control (GPS latitude)
c.kofi.total_received -> service control (Ko-fi total)
t.followers_total     -> Twitch Helix data</pre>
        </section>

        <p class="mb-12 text-sm text-muted-foreground">
          Want more math tricks (waves, modulo wheels, pseudo-random)? See the
          <Link href="/help/math" class="text-violet-400 hover:underline">Math Engine</Link> page.
          Want the catalog of preset controls you can reference? See
          <Link href="/help/integration-presets" class="text-violet-400 hover:underline">Integration Presets</Link>.
        </p>
      </div>
    </div>
  </AppLayout>
</template>
