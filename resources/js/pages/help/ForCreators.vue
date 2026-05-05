<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Help', href: '/help' },
  { title: 'For creators', href: '/help/for-creators' },
];
</script>

<template>
  <Head>
    <title>Overlabels for Creators - what this thing actually is</title>
    <meta
      name="description"
      content="A long-form description of Overlabels: a reactive value graph that happens to render to a Twitch overlay. The expensive part is the live math layer. Here's what that means, what's possible, and what's still missing."
    />

    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://overlabels.com/help/for-creators" />
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" content="Overlabels for Creators - what this thing actually is" />
    <meta
      property="og:description"
      content="A reactive value graph that happens to render to a Twitch overlay. The expensive part is the live math layer. Here's what's possible, and what's still missing."
    />
    <meta property="og:image"
          content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="Overlabels - build Twitch overlays with HTML, CSS, and live data" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Overlabels for Creators - what this thing actually is" />
    <meta
      name="twitter:description"
      content="A reactive value graph that happens to render to a Twitch overlay. The expensive part is the live math layer. Here's what's possible."
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
          <h1 class="mb-4 text-4xl font-bold">Overlabels for Creators</h1>
          <p class="mb-3 text-lg text-foreground">
            If you've heard of Overlabels, you probably heard "Twitch overlays you build with HTML and CSS". That's true
            and it's also selling the cheap part. The expensive part - the part that makes Overlabels different from
            every other overlay tool - is the live math layer underneath.
          </p>
          <p class="text-lg text-foreground">
            This page is the long-form description of what that actually means. It's written for creators in the broad
            sense: streamers who want to know what they're holding, and creative coders who might want to help map the
            space.
          </p>
        </div>

        <!-- TOC -->
        <div class="mb-12 rounded-lg border border-sidebar bg-sidebar-accent p-6">
          <h2 class="mb-4 text-xl font-bold" id="toc">Table of contents</h2>
          <ol class="list-decimal space-y-1 pl-6 text-foreground">
            <li><a href="#thesis" class="text-violet-400 hover:underline">The thesis</a></li>
            <li><a href="#primitives" class="text-violet-400 hover:underline">The primitives</a></li>
            <li><a href="#what-it-means" class="text-violet-400 hover:underline">What this means for creators</a></li>
            <li><a href="#why-different" class="text-violet-400 hover:underline">Why this is different from other overlay tools</a></li>
            <li><a href="#constraint" class="text-violet-400 hover:underline">The constraint is the feature</a></li>
            <li><a href="#gaps" class="text-violet-400 hover:underline">Honest gaps</a></li>
            <li><a href="#collaborate" class="text-violet-400 hover:underline">Looking for collaborators</a></li>
            <li><a href="#deep-dives" class="text-violet-400 hover:underline">Deep dives</a></li>
          </ol>
        </div>

        <!-- 1. The thesis -->
        <section class="mb-14" id="thesis">
          <h2 class="mb-4 text-2xl font-bold">1. The thesis</h2>
          <p class="mb-4 text-foreground">
            Overlabels is a <strong>reactive value graph</strong> that happens to render to a browser source. Every
            overlay is a tree of named values. Every value can be a constant, a live data feed, a formula derived from
            other values, or a chain of formulas derived from formulas. When any value changes, every value that depends
            on it recomputes. When a formula's result lands in your overlay's HTML or CSS, the pixels react.
          </p>
          <p class="text-foreground">
            That's it. The rest is just primitives.
          </p>
        </section>

        <!-- 2. The primitives -->
        <section class="mb-14" id="primitives">
          <h2 class="mb-6 text-2xl font-bold">2. The primitives</h2>

          <!-- Values -->
          <div class="mb-6 rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <h3 class="mb-3 text-xl font-semibold">Values (Controls)</h3>
            <p class="mb-3 text-foreground">
              Every named value is a Control. Controls have a key, a type, and a current value. You reference a Control
              in your overlay HTML or CSS with
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">[[[c:my_control]]]</code>.
              You also reference it from other Controls through expressions with
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">c.my_control</code>.
            </p>
            <p class="mb-3 text-foreground">
              Types: <em>text, number, counter, timer, boolean, datetime, expression</em>. Most of these are static
              unless you change them. The interesting two are <strong>timer</strong> (ticks in real time) and
              <strong>expression</strong> (recomputes whenever its dependencies change).
            </p>
            <p class="text-foreground">
              A Control isn't just a variable. It's the unit of reactivity. The whole system watches Controls and
              re-renders the right things when one changes.
            </p>
          </div>

          <!-- Sources -->
          <div class="mb-6 rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <h3 class="mb-3 text-xl font-semibold">Sources (live data)</h3>
            <p class="mb-3 text-foreground">
              A Source produces values automatically. The streamer connects a Source once; from then on it emits
              Controls in the
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">c.&lt;service&gt;.&lt;key&gt;</code>
              namespace.
            </p>
            <p class="mb-3 text-foreground">Sources today:</p>
            <ul class="mb-3 list-disc space-y-1 pl-6 text-foreground">
              <li><strong>Twitch</strong> - per-stream counters, latest cheer / latest donor, follower / sub / raid / redemption tallies</li>
              <li><strong>Ko-fi</strong>, <strong>Streamlabs</strong>, <strong>StreamElements</strong>, <strong>Fourthwall</strong>, <strong>Buy Me a Coffee</strong> - donations and tips</li>
              <li><strong>Overlabels GPS</strong> - live phone GPS: lat, lng, speed, distance, battery, accuracy, plus session aggregates</li>
              <li><strong>GPSLogger</strong> - legacy GPS app, kept for existing setups</li>
              <li><strong>Time itself</strong> -
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">now()</code>
                and
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">now_ms()</code>
                are bare functions in expressions that always return the current timestamp
              </li>
            </ul>
            <p class="text-foreground">
              Every value any Source emits is <em>just a Control</em>. Which means anything you can do with a Control
              you can do with a live data feed. Which means a CSS rule that bends a sprite based on
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">c.gps.speed</code>
              works exactly the same as a CSS rule that bends a sprite based on
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">c.my_manual_slider</code>.
            </p>
          </div>

          <!-- Expression engine -->
          <div class="mb-6 rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <h3 class="mb-3 text-xl font-semibold">The expression engine</h3>
            <p class="mb-3 text-foreground">
              Expression Controls let you write formulas that compute their value live. The engine is sandboxed (no
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">eval</code>,
              no prototype walking, no network). It supports:
            </p>
            <ul class="mb-3 list-disc space-y-1 pl-6 text-foreground">
              <li>Arithmetic:
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">+ - * / %</code>
              </li>
              <li>Comparisons and conditionals:
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">== != &gt; &lt; &gt;= &lt;= &amp;&amp; || ? :</code>
              </li>
              <li>Trigonometry:
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">sin cos tan asin acos atan atan2</code>
              </li>
              <li>Math utilities:
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">sqrt abs round floor ceil max min clamp sum avg</code>
              </li>
              <li>Label selectors:
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">argmax argmin latest oldest</code>
                - return the <em>label</em> paired with the winning value, useful for "which service had the most recent donation"
              </li>
              <li>GLSL-style helpers:
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">fract mod</code>
              </li>
              <li>Time:
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">now() now_ms()</code>
              </li>
              <li>Constants:
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">PI</code>
              </li>
            </ul>
            <p class="text-foreground">
              Expressions can reference other expressions. Cycles are blocked. The full reference, including the
              Haversine great-circle distance walkthrough, is at
              <Link href="/help/expressions" class="text-violet-400 hover:underline">/help/expressions</Link>.
            </p>
          </div>

          <!-- Output -->
          <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
            <h3 class="mb-3 text-xl font-semibold">Output (the overlay itself)</h3>
            <p class="mb-3 text-foreground">
              An overlay is an HTML and CSS template. Anywhere you can write text, you can drop
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">[[[c:something]]]</code>
              and it gets replaced with the live value of that Control. CSS isn't special - you can put
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">calc([[[c:progress_pct]]] * 1%)</code>
              in a
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">left:</code>
              rule and the engine keeps it in sync.
            </p>
            <p class="text-foreground">
              <strong>No JavaScript, no
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">&lt;script&gt;</code>,
              no
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">&lt;iframe&gt;</code>,
              no embeds</strong> - all dynamism comes from Controls and CSS. See
              <a href="#constraint" class="text-violet-400 hover:underline">"The constraint is the feature"</a>
              below for why.
            </p>
            <p class="mb-2 text-foreground">Templates can react two ways:</p>
            <ul class="list-disc space-y-1 pl-6 text-foreground">
              <li><strong>Pull</strong> - the value is interpolated into your HTML/CSS, and CSS transitions or keyframes do the smoothing.</li>
              <li><strong>Push</strong> - an Alert template fires once when an EventSub event arrives (a follow, a sub, a raid, a donation), animates, and disappears.</li>
            </ul>
          </div>
        </section>

        <!-- 3. What this means for creators -->
        <section class="mb-14" id="what-it-means">
          <h2 class="mb-4 text-2xl font-bold">3. What this means for creators</h2>
          <p class="mb-4 text-foreground">
            A streamer wants a thing on their overlay to react to data. The conventional overlay tool gives them a knob
            to turn or a widget to drop in. Overlabels gives them a number, a formula, and a CSS rule. The expressivity
            gap is enormous and undersold. Each of the following is a few-line expression and a few-line CSS rule:
          </p>
          <ul class="mb-4 list-disc space-y-2 pl-6 text-foreground">
            <li>
              A <strong>donation goal</strong> that physically opens a treasure chest as
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">c.kofi.total_received / c.goal_amount</code>
              crosses thresholds.
            </li>
            <li>
              A <strong>subathon timer</strong> that tints the overlay redder as remaining seconds drop, using
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">clamp</code>
              and HSL
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">calc()</code>.
            </li>
            <li>
              A <strong>GPS-driven cyclist sprite</strong> that bends forward proportional to current speed.
            </li>
            <li>
              A <strong>chat-vote split bar</strong> that wobbles harder when the vote is close (read: when
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">abs(c.option_a - c.option_b)</code>
              is small).
            </li>
            <li>
              A <strong>latest-donor name</strong> that pulls from whichever service tipped most recently:
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">latest(c.kofi.latest_donor_name_at, c.kofi.latest_donor_name, c.streamlabs.latest_donor_name_at, c.streamlabs.latest_donor_name)</code>.
            </li>
            <li>
              A <strong>Lissajous curve, a wave, a breathing UI element, a pseudo-random shader effect</strong> -
              all expressible as a formula on top of
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">now()</code>.
            </li>
          </ul>
          <p class="text-foreground">
            None of those require deploying code, restarting OBS, or shipping a plugin update. The streamer types into
            a textbox, hits save, and the change is live within a second.
          </p>
        </section>

        <!-- 4. Why this is different -->
        <section class="mb-14" id="why-different">
          <h2 class="mb-4 text-2xl font-bold">4. Why this is different from other overlay tools</h2>
          <div class="overflow-x-auto rounded-lg border border-sidebar bg-sidebar-accent">
            <table class="w-full text-sm">
              <thead class="border-b border-sidebar text-left">
                <tr>
                  <th class="p-3 font-semibold">Tool</th>
                  <th class="p-3 font-semibold">What you get</th>
                  <th class="p-3 font-semibold">Composability</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-sidebar">
                <tr>
                  <td class="p-3 text-foreground">Streamlabs / StreamElements widgets</td>
                  <td class="p-3 text-foreground">Pre-built widgets with config knobs</td>
                  <td class="p-3 text-foreground">You configure. You can't really compose.</td>
                </tr>
                <tr>
                  <td class="p-3 text-foreground">OBS source plugins</td>
                  <td class="p-3 text-foreground">Anything, in code</td>
                  <td class="p-3 text-foreground">Per-streamer engineering. Compile, deploy, restart.</td>
                </tr>
                <tr>
                  <td class="p-3 text-foreground">Browser-source HTML overlays</td>
                  <td class="p-3 text-foreground">A static page that polls or listens</td>
                  <td class="p-3 text-foreground">You write the JS. You host the page. You handle reconnects.</td>
                </tr>
                <tr>
                  <td class="p-3 text-foreground"><strong>Overlabels</strong></td>
                  <td class="p-3 text-foreground"><strong>Any value, any source, any formula, any consumer.</strong></td>
                  <td class="p-3 text-foreground"><strong>Composable end-to-end. Live in &lt;1s after save.</strong></td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="mt-4 text-foreground">
            The Overlabels overlay is the cheap part. The expensive part is the reactive value graph underneath, and
            the integrations that pump real data into it.
          </p>
        </section>

        <!-- 5. The constraint is the feature -->
        <section class="mb-14" id="constraint">
          <h2 class="mb-4 text-2xl font-bold">5. The constraint is the feature</h2>
          <div class="rounded-lg border border-violet-400/30 bg-violet-400/5 p-6">
            <p class="mb-4 text-lg font-medium text-foreground">
              The "no JavaScript" rule isn't an oversight - it's the load-bearing security and shareability decision
              the whole system rests on.
            </p>
            <p class="mb-3 text-foreground">
              Templates flow between users. Streamers copy each other's overlays, paste tags from screenshots,
              and remix designs in the wild. If templates could run JS, every copied overlay would be a potential
              supply-chain attack: a hidden fetch loop that exfils session tokens, an iframe pointing at a phishing
              page, an event listener that ships streaming patterns to an unknown server. Overlabels would last about
              a week before someone shipped a popular template that quietly turned every overlay into a
              data-collection node.
            </p>
            <p class="mb-3 text-foreground">
              So templates get sanitized server-side before they ever reach a browser source. No
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">&lt;script&gt;</code>,
              no
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">&lt;iframe&gt;</code>,
              no
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">on*=</code>
              handlers, no inline
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">javascript:</code>
              URLs. Tags are parsed exactly once per render, so even Control values can't smuggle markup through a
              template-injection trick. Rendered overlays don't phone home either - the URL-fragment auth-token
              model means the page literally can't report telemetry back, by design.
            </p>
            <p class="mb-3 text-foreground">
              What you do instead: <strong>the expression engine and CSS animation are your runtime.</strong>
              Anything you would have reached for JS to do - state machines, conditional renders, animation timing,
              easing curves, periodic effects - you do as a chained Expression Control feeding into a
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">calc()</code>,
              a CSS variable, or a
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">transition</code>.
              The Haversine walkthrough on
              <Link href="/help/expressions" class="text-violet-400 hover:underline">/help/expressions</Link>
              is exactly this: five chained formulas plus a CSS rule, no JavaScript anywhere, animating a sprite
              across a 1080p browser source in response to live phone GPS.
            </p>
            <p class="text-foreground">
              For a creative coder this is the headline: <strong>your recipe is safe to ship</strong>. You don't
              have to convince a stranger to trust your code. The streamer who copies it doesn't need a security
              review. The overlay just runs, in any browser source, with no escape hatch and therefore no exploit
              path. Recipes published in the Overlabels recipe book carry the same guarantee every other template
              does: nothing reaches outside the sandbox, ever. The constraint is what makes the whole "copy
              someone else's clever overlay" loop work at all.
            </p>
          </div>
        </section>

        <!-- 6. Honest gaps -->
        <section class="mb-14" id="gaps">
          <h2 class="mb-4 text-2xl font-bold">6. Honest gaps</h2>
          <p class="mb-4 text-foreground">
            Things creators ask for that aren't possible yet. If you're a creative coder evaluating the surface, you
            should know where the walls are:
          </p>
          <ul class="list-disc space-y-2 pl-6 text-foreground">
            <li><strong>Audio analysis</strong> - no mic level, no music BPM detection. Open question.</li>
            <li><strong>MIDI / hardware controllers</strong> - no mapping today.</li>
            <li>
              <strong>Direct EventSub data in expressions</strong> - EventSub triggers update preset Controls (which
              you can reference) and fire Alerts (one-shot animations), but there's no direct
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">e.&lt;event&gt;.&lt;field&gt;</code>
              namespace inside expressions.
            </li>
            <li>
              <strong>Persistent state across stream sessions for arbitrary expressions</strong> - counters and sliders
              persist; computed values are re-derived from inputs each time.
            </li>
            <li><strong>Multi-overlay synchronization</strong> - each overlay is independent.</li>
          </ul>
        </section>

        <!-- 7. Looking for collaborators -->
        <section class="mb-14" id="collaborate">
          <h2 class="mb-4 text-2xl font-bold">7. Looking for collaborators</h2>
          <div class="rounded-lg border border-violet-400/40 bg-violet-400/5 p-6">
            <p class="mb-3 text-foreground">
              If you're a creative coder, shader artist, or generative-art person who reads
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">sin(t * PI / 2)</code>
              like a sentence, here's the gig:
            </p>
            <p class="mb-3 text-foreground">
              I'm looking to co-author a recipe book of Expression Control examples. Each recipe is a screenshot or gif,
              the formulas, and the HTML/CSS that consumes them. Think Shadertoy entries, but each one is a self-contained
              overlay effect a streamer can copy in 30 seconds. Donation pulses, GPS-driven sprites, subathon-timer
              tints, follow-count auras, raid wormhole transitions, vote-bar wobbles - any combination of value source
              + expression + CSS that's worth its own gif.
            </p>
            <p class="mb-3 text-foreground">
              Paid. Open call. Attribution included. The bar is "would another creative coder find this clever",
              not "is it useful to the median streamer" - the median streamer copies what other people built.
            </p>
            <p class="text-foreground">
              Mail:
              <a href="mailto:jasper@emailjasper.com" class="text-violet-400 hover:underline">jasper@emailjasper.com</a>.
              Include a portfolio link, a paragraph on what kind of effect you'd want to start with, and a rate.
            </p>
          </div>
        </section>

        <!-- 8. Deep dives -->
        <section class="mb-14" id="deep-dives">
          <h2 class="mb-4 text-2xl font-bold">8. Deep dives</h2>
          <p class="mb-4 text-foreground">
            The rest of the help section is the developer-style reference for each primitive:
          </p>
          <ul class="list-disc space-y-2 pl-6 text-foreground">
            <li>
              <Link href="/help/controls" class="text-violet-400 hover:underline">Controls</Link>
              - the seven control types and how they behave on an overlay.
            </li>
            <li>
              <Link href="/help/expressions" class="text-violet-400 hover:underline">Expression Controls</Link>
              - the math layer in full, with the Haversine walkthrough.
            </li>
            <li>
              <Link href="/help/integration-presets" class="text-violet-400 hover:underline">Integration Presets</Link>
              - the catalog of every auto-managed Control across Twitch, Ko-fi, Streamlabs, StreamElements, Fourthwall,
              BMAC, and Overlabels GPS.
            </li>
            <li>
              <Link href="/help/math" class="text-violet-400 hover:underline">Math Engine</Link>
              - waves, modulo wheels, pseudo-random one-liners, timestamp racing.
            </li>
            <li>
              <Link href="/help/conditionals" class="text-violet-400 hover:underline">Conditional and Event Tags</Link>
              - if/else logic in templates.
            </li>
            <li>
              <Link href="/help/formatting" class="text-violet-400 hover:underline">Formatting Pipes</Link>
              - locale-aware number, currency, duration, distance, and speed formatting.
            </li>
            <li>
              <Link href="/help/manifesto" class="text-violet-400 hover:underline">Manifesto</Link>
              - principles and philosophy, if that's your thing.
            </li>
          </ul>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
