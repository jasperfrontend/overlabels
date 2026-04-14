<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import MathEquation from '@/components/help/MathEquation.vue';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Help', href: '/help' },
  { title: 'Math Engine', href: '/help/math' },
];
</script>

<template>
  <Head>
    <title>Math Engine - Overlabels</title>
    <meta
      name="description"
      content="The math-side of Overlabels: waves, modulo wheels, pseudo-random shaders, timestamp racing, and the expression tricks that make overlays feel alive."
    />

    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://overlabels.com/help/math" />
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" content="Math Engine - Overlabels" />
    <meta
      property="og:description"
      content="Waves, modulo wheels, pseudo-random shaders, timestamp racing, and the expression tricks that make overlays feel alive."
    />
  </Head>

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="min-h-screen bg-background">
      <div class="mx-auto max-w-4xl p-6">

        <!-- Header -->
        <div class="mb-10">
          <h1 class="mb-4 text-4xl font-bold">The Math Engine</h1>
          <p class="mb-3 text-lg text-foreground">
            Overlabels ships with a tiny, sandboxed expression engine. Give it an expression string,
            it gives you a live value that re-evaluates whenever its dependencies change. That is enough
            rope to build waves, counters, rotations, pseudo-random digits, and cross-service racing logic
            entirely in a text field.
          </p>
          <p class="text-lg text-foreground">
            This page is for the math-heads. If you came here for gentle examples, try
            <Link href="/help/conditionals" class="text-violet-400 hover:underline">Conditionals</Link>
            or
            <Link href="/help/formatting" class="text-violet-400 hover:underline">Formatting Pipes</Link>
            first. Still here? Good. Put on the goggles.
          </p>
        </div>

        <!-- TOC -->
        <div class="mb-12 rounded-lg border border-sidebar bg-sidebar-accent p-6">
          <h2 class="mb-4 text-xl font-bold" id="toc">Table of contents</h2>
          <ol class="list-decimal space-y-1 pl-6 text-foreground">
            <li><a href="#toolbox" class="text-violet-400 hover:underline">The toolbox</a></li>
            <li><a href="#heartbeat" class="text-violet-400 hover:underline">The heartbeat: <code>now()</code></a></li>
            <li><a href="#waves" class="text-violet-400 hover:underline">Waves from trigonometry</a></li>
            <li><a href="#sawtooth" class="text-violet-400 hover:underline">Sawtooth, ramps, and <code>fract()</code></a></li>
            <li><a href="#prng" class="text-violet-400 hover:underline">Decoded: the pseudo-random one-liner</a></li>
            <li><a href="#modulo" class="text-violet-400 hover:underline">The modulo wheel</a></li>
            <li><a href="#ranges" class="text-violet-400 hover:underline">Clamp, round, abs: the cleanup crew</a></li>
            <li><a href="#winners" class="text-violet-400 hover:underline">Winners and timestamp racing</a></li>
            <li><a href="#tags" class="text-violet-400 hover:underline">Mixing with template tags</a></li>
            <li><a href="#pitfalls" class="text-violet-400 hover:underline">Pitfalls and things that will not work</a></li>
          </ol>
        </div>

        <!-- 1. The toolbox -->
        <section class="mb-14" id="toolbox">
          <h2 class="mb-4 text-2xl font-bold">1. The toolbox</h2>
          <p class="mb-5 text-foreground">
            Everything the engine understands. Anything not on this list is intentionally absent -
            no <code>eval</code>, no <code>new Function</code>, no prototype walking. The sandbox
            is the point.
          </p>

          <h3 class="mt-6 mb-2 text-lg font-semibold">Operators</h3>
          <div class="rounded border border-sidebar bg-sidebar p-4 font-mono text-sm text-foreground">
            +&nbsp;&nbsp;-&nbsp;&nbsp;*&nbsp;&nbsp;/&nbsp;&nbsp;%&nbsp;&nbsp;&nbsp;&nbsp;==&nbsp;&nbsp;!=&nbsp;&nbsp;&gt;&nbsp;&nbsp;&lt;&nbsp;&nbsp;&gt;=&nbsp;&nbsp;&lt;=&nbsp;&nbsp;&nbsp;&nbsp;&amp;&amp;&nbsp;&nbsp;||&nbsp;&nbsp;!&nbsp;&nbsp;&nbsp;&nbsp;?&nbsp;:
          </div>
          <p class="mt-2 text-sm text-muted-foreground">
            No exponentiation operator (<code>**</code>, <code>^</code>). For squares, write <code>x * x</code>.
            The ternary <code>a ? b : c</code> is available, including nested form.
          </p>

          <h3 class="mt-8 mb-2 text-lg font-semibold">Constants</h3>
          <ul class="list-disc space-y-1 pl-6 text-foreground">
            <li><code>PI</code> - <MathEquation tex="\pi \approx 3.14159265" />. Identifier only, not <code>PI()</code>.</li>
          </ul>

          <h3 class="mt-8 mb-2 text-lg font-semibold">Scalar math</h3>
          <div class="overflow-x-auto rounded-lg border border-sidebar">
            <table class="w-full text-sm">
              <thead class="bg-sidebar">
                <tr class="text-left">
                  <th class="px-4 py-2 font-semibold">Call</th>
                  <th class="px-4 py-2 font-semibold">Meaning</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-sidebar">
                <tr><td class="px-4 py-2 font-mono">max(a, b, ...)</td><td class="px-4 py-2 text-foreground">Largest of the args</td></tr>
                <tr><td class="px-4 py-2 font-mono">min(a, b, ...)</td><td class="px-4 py-2 text-foreground">Smallest of the args</td></tr>
                <tr><td class="px-4 py-2 font-mono">clamp(lo, x, hi)</td><td class="px-4 py-2 text-foreground">x pinned to [lo, hi]</td></tr>
                <tr><td class="px-4 py-2 font-mono">sum(a, b, ...)</td><td class="px-4 py-2 text-foreground">Arithmetic sum</td></tr>
                <tr><td class="px-4 py-2 font-mono">avg(a, b, ...)</td><td class="px-4 py-2 text-foreground">Arithmetic mean</td></tr>
                <tr><td class="px-4 py-2 font-mono">abs(x)</td><td class="px-4 py-2 text-foreground">|x|</td></tr>
                <tr><td class="px-4 py-2 font-mono">round(x) / round(x, n)</td><td class="px-4 py-2 text-foreground">Nearest integer, or n decimals</td></tr>
                <tr><td class="px-4 py-2 font-mono">floor(x) / ceil(x)</td><td class="px-4 py-2 text-foreground">Round toward &minus;&infin; / +&infin;</td></tr>
                <tr><td class="px-4 py-2 font-mono">sin(x) / cos(x)</td><td class="px-4 py-2 text-foreground">Trig, x in <em>radians</em></td></tr>
                <tr><td class="px-4 py-2 font-mono">fract(x)</td><td class="px-4 py-2 text-foreground">x &minus; floor(x). Always &isin; [0, 1)</td></tr>
                <tr><td class="px-4 py-2 font-mono">mod(a, b)</td><td class="px-4 py-2 text-foreground">Floor-modulo (GLSL-style, not JS <code>%</code>)</td></tr>
                <tr><td class="px-4 py-2 font-mono">now()</td><td class="px-4 py-2 text-foreground">Unix timestamp in seconds</td></tr>
              </tbody>
            </table>
          </div>

          <h3 class="mt-8 mb-2 text-lg font-semibold">Argument-pair family</h3>
          <p class="mb-3 text-foreground">
            Take value/label pairs and return the <em>label</em> paired with the winning value.
            Ties go to the first pair.
          </p>
          <div class="rounded border border-sidebar bg-sidebar p-4 font-mono text-sm text-foreground">
            argmax(v1, l1, v2, l2, ...)<br />
            argmin(v1, l1, v2, l2, ...)<br />
            latest(v1, l1, v2, l2, ...)&nbsp;&nbsp;&nbsp;&nbsp;// alias of argmax, but intent: timestamps<br />
            oldest(v1, l1, v2, l2, ...)&nbsp;&nbsp;&nbsp;&nbsp;// alias of argmin<br />
          </div>
          <p class="mt-2 text-sm text-muted-foreground">
            Values are coerced to numbers. Strings that parse as numbers work. Strings that look like ISO dates
            are parsed as milliseconds since epoch.
          </p>
        </section>

        <!-- 2. Heartbeat: now() -->
        <section class="mb-14" id="heartbeat">
          <h2 class="mb-4 text-2xl font-bold">2. The heartbeat: <code>now()</code></h2>
          <p class="mb-3 text-foreground">
            Every time-based trick in this page reduces to one identity. Let
            <MathEquation tex="t = \text{now}()" /> be the current Unix time in seconds.
            Time only matters once you <em>take its fractional part</em>, <em>feed it through trig</em>, or
            <em>compare it to another timestamp</em>. Three tricks cover most of the design space.
          </p>
          <p class="mb-3 text-foreground">
            Overlabels also stamps every control with an automatic companion: <code>c:key_at</code> is the
            Unix timestamp of the last change to <code>c:key</code>. This is what turns <code>latest()</code>
            into a cross-service race.
          </p>

          <h3 class="mt-6 mb-2 text-lg font-semibold">Elapsed seconds since an event</h3>
          <p class="mb-3 text-foreground">
            Any control's <code>_at</code> companion is a Unix timestamp you can subtract <code>now()</code>
            from. Pick any event-driven control - for example, the one that stores the latest follower -
            and you can show how long ago it fired.
          </p>
          <div class="rounded border border-sidebar bg-sidebar p-4 font-mono text-sm text-foreground">
            now() - c.followers_latest_date_at
          </div>
          <p class="mt-2 text-sm text-muted-foreground">
            Pipe the result through <code>|duration:mm:ss</code> and you have a "last follow was N minutes ago"
            display built from subtraction alone.
          </p>
        </section>

        <!-- 3. Waves -->
        <section class="mb-14" id="waves">
          <h2 class="mb-4 text-2xl font-bold">3. Waves from trigonometry</h2>
          <p class="mb-3 text-foreground">
            The canonical animation primitive. A sine wave with amplitude <MathEquation tex="A" />,
            period <MathEquation tex="T" />, and baseline <MathEquation tex="C" />:
          </p>
          <MathEquation
            display
            tex="y(t) = A \sin\!\left(\frac{2\pi t}{T}\right) + C"
          />
          <p class="mb-3 text-foreground">
            Map that one formula to controls and you have a breathing badge, a pulsing circle, a
            lighthouse sweep, or a subtle bob. The pattern:
          </p>
          <div class="rounded border border-sidebar bg-sidebar p-4 font-mono text-sm leading-7 text-foreground">
            <span class="text-muted-foreground">// 1 Hz pulse, mapped to 0..1 (use as opacity / scale normaliser)</span><br />
            0.5 + 0.5 * sin(2 * PI * now())<br /><br />
            <span class="text-muted-foreground">// Slow breathe, &plusmn;5% around 1.0, period 6 s</span><br />
            1 + 0.05 * sin(2 * PI * now() / 6)<br /><br />
            <span class="text-muted-foreground">// Lighthouse sweep, 0..1 once every 8 s (always positive)</span><br />
            abs(sin(PI * now() / 8))
          </div>
          <p class="mt-4 text-foreground">
            The generalised <em>remap</em> from <MathEquation tex="[-1, 1]" /> into any range
            <MathEquation tex="[\text{lo}, \text{hi}]" /> is a template worth memorising:
          </p>
          <MathEquation
            display
            tex="\text{remap}(s) = \text{lo} + (\text{hi} - \text{lo}) \cdot \tfrac{1}{2}\!\left(s + 1\right)"
          />

          <h3 class="mt-8 mb-2 text-lg font-semibold">Lissajous figures on two controls</h3>
          <p class="mb-3 text-foreground">
            Drive an X offset with <code>sin</code> and a Y offset with <code>cos</code> at different
            frequencies. Two control expressions, one orbit:
          </p>
          <div class="rounded border border-sidebar bg-sidebar p-4 font-mono text-sm leading-7 text-foreground">
            <span class="text-muted-foreground">// c:orbit_x</span><br />
            40 * sin(2 * PI * now() / 5)<br /><br />
            <span class="text-muted-foreground">// c:orbit_y (3:2 frequency ratio -&gt; a classic Lissajous)</span><br />
            40 * cos(2 * PI * now() / 7.5)
          </div>
        </section>

        <!-- 4. Sawtooth / fract -->
        <section class="mb-14" id="sawtooth">
          <h2 class="mb-4 text-2xl font-bold">4. Sawtooth, ramps, and <code>fract()</code></h2>
          <p class="mb-3 text-foreground">
            <code>fract(x) = x - floor(x)</code>. It discards the integer part and keeps the fraction.
            Feed it a rising quantity and you get a <em>sawtooth</em>: a 0 &rarr; 1 ramp that snaps back to
            zero forever.
          </p>
          <MathEquation
            display
            tex="\text{fract}(x) = x - \lfloor x \rfloor \quad\in [0,\,1)"
          />

          <div class="rounded border border-sidebar bg-sidebar p-4 font-mono text-sm leading-7 text-foreground">
            <span class="text-muted-foreground">// 10-second loop, ramps 0 -&gt; 1</span><br />
            fract(now() / 10)<br /><br />
            <span class="text-muted-foreground">// Same loop, reversed: 1 -&gt; 0</span><br />
            1 - fract(now() / 10)<br /><br />
            <span class="text-muted-foreground">// Triangle wave via abs of a shifted sawtooth: 0 -&gt; 1 -&gt; 0 every 4 s</span><br />
            abs(2 * fract(now() / 4) - 1)
          </div>

          <p class="mt-4 text-foreground">
            The triangle trick deserves its own line. Start with a sawtooth, scale it to
            <MathEquation tex="[0, 2]" />, subtract 1 to centre on zero, then take the absolute value.
            You just built a piecewise-linear tent function from two primitives.
          </p>
        </section>

        <!-- 5. Pseudo-random one-liner -->
        <section class="mb-14" id="prng">
          <h2 class="mb-4 text-2xl font-bold">5. Decoded: the pseudo-random one-liner</h2>
          <p class="mb-3 text-foreground">
            This expression returns a seemingly random integer from 1 to 9, changing twice per second:
          </p>
          <div class="rounded border border-sidebar bg-sidebar p-4 font-mono text-base text-foreground">
            floor(fract(sin(now() / 2) * 1000) * 9) + 1
          </div>
          <p class="mt-3 text-foreground">
            It is a variant of the classic shader-language pseudo-random trick
            <MathEquation tex="\text{fract}(\sin(x) \cdot k)" />. It is not cryptographic - do not roll
            dice in a contract with it - but for visual sparkle it is beautiful. Let us take it apart.
          </p>

          <ol class="mt-4 list-decimal space-y-3 pl-6 text-foreground">
            <li>
              <code>now() / 2</code> - time, but ticking in half-second units. Any monotonically-rising
              value works here. Dividing slows the churn.
            </li>
            <li>
              <code>sin(...)</code> - maps the growing input into <MathEquation tex="[-1, 1]" />. On its
              own, too smooth to be random.
            </li>
            <li>
              <code>... * 1000</code> - scales that smooth wave up. The <em>integer part</em> of the
              result is now big and varied; the <em>fractional part</em> is where the chaos lives.
              Multiplying by a large number amplifies how fast the fraction tumbles as <MathEquation tex="x" /> changes.
            </li>
            <li>
              <code>fract(...)</code> - throws away the integer part and keeps only the chaotic tail.
              The output is now in <MathEquation tex="[0, 1)" /> and, from the user's perspective, indistinguishable
              from noise.
            </li>
            <li>
              <code>... * 9</code> - stretches that unit-interval noise into <MathEquation tex="[0, 9)" />.
            </li>
            <li>
              <code>floor(...) + 1</code> - snaps to an integer in <MathEquation tex="\{0, 1, \ldots, 8\}" />,
              then shifts to <MathEquation tex="\{1, 2, \ldots, 9\}" />.
            </li>
          </ol>

          <p class="mt-5 text-foreground">
            Equivalent formulation, in case you prefer to read it in math:
          </p>
          <MathEquation
            display
            tex="r = \left\lfloor 9 \cdot \text{fract}\!\Big(1000 \cdot \sin\!\big(\tfrac{t}{2}\big)\Big) \right\rfloor + 1, \quad r \in \{1, \ldots, 9\}"
          />

          <h3 class="mt-8 mb-2 text-lg font-semibold">Variants</h3>
          <div class="rounded border border-sidebar bg-sidebar p-4 font-mono text-sm leading-7 text-foreground">
            <span class="text-muted-foreground">// Uniform-ish [0, 1) noise (no integer snap)</span><br />
            fract(sin(now()) * 43758.5453123)<br /><br />
            <span class="text-muted-foreground">// Roll a 20-sided die every 3 seconds</span><br />
            floor(fract(sin(floor(now() / 3)) * 9999) * 20) + 1<br /><br />
            <span class="text-muted-foreground">// "Pick one of three overlays" every 10 s, using mod</span><br />
            mod(floor(fract(sin(floor(now() / 10)) * 9999) * 3), 3)
          </div>
          <p class="mt-3 text-sm text-muted-foreground">
            Note the <code>floor(now() / N)</code> trick: quantising time before you sin it turns a
            continuously-changing value into a step function. The "random" output then stays stable for
            <em>N</em> seconds before jumping, which is what you actually want for most UI.
          </p>
        </section>

        <!-- 6. Modulo wheel -->
        <section class="mb-14" id="modulo">
          <h2 class="mb-4 text-2xl font-bold">6. The modulo wheel</h2>
          <p class="mb-3 text-foreground">
            <code>mod(a, b)</code> in Overlabels is <em>floor</em>-modulo, the one mathematicians wrote on the
            chalkboard:
          </p>
          <MathEquation
            display
            tex="\text{mod}(a, b) = a - b \cdot \left\lfloor \tfrac{a}{b} \right\rfloor"
          />
          <p class="mb-3 text-foreground">
            Always non-negative when <MathEquation tex="b > 0" />, even for negative <MathEquation tex="a" />.
            Contrast with the JS <code>%</code> operator, which preserves sign. Use <code>mod</code> when you
            are indexing something cyclic.
          </p>

          <div class="rounded border border-sidebar bg-sidebar p-4 font-mono text-sm leading-7 text-foreground">
            <span class="text-muted-foreground">// Cycle 0 -&gt; 1 -&gt; 2 -&gt; 0 every 5 s</span><br />
            mod(floor(now() / 5), 3)<br /><br />
            <span class="text-muted-foreground">// Cycle through the days of the year (day-of-year)</span><br />
            mod(floor(now() / 86400), 365)<br /><br />
            <span class="text-muted-foreground">// Ping-pong 0 -&gt; 1 -&gt; 0 smoothly: triangle then normalise</span><br />
            abs(2 * fract(now() / 6) - 1)
          </div>

          <p class="mt-4 text-foreground">
            Pair <code>mod</code> with a conditional to rotate overlay text:
          </p>
          <div class="rounded border border-sidebar bg-sidebar p-4 font-mono text-sm leading-7 text-foreground">
            <span class="text-muted-foreground">// c:banner_index =&gt;</span><br />
            mod(floor(now() / 8), 3)<br /><br />
            <span class="text-muted-foreground">// In HTML:</span><br />
            [[[if:c:banner_index = 0]]]Welcome, [[[channel_name]]]![[[endif]]]<br />
            [[[if:c:banner_index = 1]]]Follow to join [[[followers_total]]]+ friends.[[[endif]]]<br />
            [[[if:c:banner_index = 2]]]!commands for the full list.[[[endif]]]
          </div>
        </section>

        <!-- 7. Ranges -->
        <section class="mb-14" id="ranges">
          <h2 class="mb-4 text-2xl font-bold">7. Clamp, round, abs: the cleanup crew</h2>
          <p class="mb-3 text-foreground">
            The engine's cleanup functions exist so you can pipe raw inputs into CSS without worrying about
            extremes, floats, or negative values.
          </p>

          <h3 class="mt-4 mb-2 text-lg font-semibold">Clamp as a saturation limiter</h3>
          <div class="rounded border border-sidebar bg-sidebar p-4 font-mono text-sm leading-7 text-foreground">
            <span class="text-muted-foreground">// Hype meter: 0..100, never overshoots, never negative</span><br />
            clamp(0, c.cheer_bits / 100, 100)
          </div>

          <h3 class="mt-6 mb-2 text-lg font-semibold">Round for display, keep precision internally</h3>
          <p class="mb-3 text-foreground">
            Trig output has 15 decimal places you never want to show. Round at the edge of the UI.
          </p>
          <div class="rounded border border-sidebar bg-sidebar p-4 font-mono text-sm leading-7 text-foreground">
            <span class="text-muted-foreground">// Win rate as a clean percentage</span><br />
            round(c.wins / (c.wins + c.losses) * 100, 1)
          </div>

          <h3 class="mt-6 mb-2 text-lg font-semibold">abs(sin) as a one-sided wave</h3>
          <p class="text-foreground">
            Taking the absolute value of a sine folds the negative half up. You get twice the frequency
            visually and a lighthouse-style pulse that never dips below zero. Great for "intensity".
          </p>
        </section>

        <!-- 8. Winners -->
        <section class="mb-14" id="winners">
          <h2 class="mb-4 text-2xl font-bold">8. Winners and timestamp racing</h2>
          <p class="mb-3 text-foreground">
            This is the trick the rest of the streaming ecosystem does not have. Every control in Overlabels
            has an automatic <code>_at</code> companion that stores <em>the Unix timestamp of its last change</em>.
            That means you can race signals:
          </p>
          <MathEquation
            display
            tex="\text{most\_recent\_donor} = \underset{s \in \text{sources}}{\operatorname{argmax}}\ t_{s}"
          />

          <div class="rounded border border-sidebar bg-sidebar p-4 font-mono text-sm leading-7 text-foreground">
            <span class="text-muted-foreground">// Who tipped most recently - Ko-fi, Streamlabs, or StreamElements?</span><br />
            latest(<br />
            &nbsp;&nbsp;c.kofi.latest_donor_name_at, c.kofi.latest_donor_name,<br />
            &nbsp;&nbsp;c.streamlabs.latest_donor_name_at, c.streamlabs.latest_donor_name,<br />
            &nbsp;&nbsp;c.streamelements.latest_donor_name_at, c.streamelements.latest_donor_name<br />
            )
          </div>

          <p class="mt-4 text-foreground">
            The value at each odd position is a timestamp; the even position next to it is the label you want
            returned. <code>latest()</code> picks the biggest timestamp and returns its paired label.
            <code>oldest()</code> / <code>argmin()</code> do the opposite - perfect for "slowest response",
            "first to arrive", "longest since".
          </p>

          <h3 class="mt-8 mb-2 text-lg font-semibold">Sum across services</h3>
          <div class="rounded border border-sidebar bg-sidebar p-4 font-mono text-sm leading-7 text-foreground">
            <span class="text-muted-foreground">// Unified donation counter</span><br />
            c.kofi.donations_received + c.streamlabs.donations_received + c.streamelements.donations_received<br /><br />
            <span class="text-muted-foreground">// Unified total received amount</span><br />
            c.kofi.total_received + c.streamlabs.total_received + c.streamelements.total_received
          </div>

          <h3 class="mt-8 mb-2 text-lg font-semibold">"Is this subscriber actually a gift?"</h3>
          <p class="mb-3 text-foreground">
            Because <code>[[[subscribers_latest_is_gift]]]</code> is a boolean stamped by the
            <code>channel.subscribe</code> EventSub rule, you can build sentiment directly:
          </p>
          <div class="rounded border border-sidebar bg-sidebar p-4 font-mono text-sm leading-7 text-foreground">
            <span class="text-muted-foreground">// Who to thank for the most recent sub</span><br />
            c.subscribers_latest_is_gift<br />
            &nbsp;&nbsp;? c.subscribers_latest_gifter_name + " gifted a sub to " + c.subscribers_latest_user_name<br />
            &nbsp;&nbsp;: c.subscribers_latest_user_name + " just subscribed"
          </div>
          <p class="mt-2 text-sm text-muted-foreground">
            The ternary is your friend. Chain them for switch-like behaviour:
            <code>a ? x : b ? y : z</code>.
          </p>
        </section>

        <!-- 9. Mixing with template tags -->
        <section class="mb-14" id="tags">
          <h2 class="mb-4 text-2xl font-bold">9. Mixing with template tags</h2>
          <p class="mb-3 text-foreground">
            Static template tags interpolate <em>before</em> the overlay renders. Control expressions
            evaluate <em>live</em>. You can combine them when the static tag is stable for the lifetime
            of the render, like a username or a starting follower count.
          </p>

          <div class="rounded border border-sidebar bg-sidebar p-4 font-mono text-sm leading-7 text-foreground">
            <span class="text-muted-foreground">// Progress toward the next 1,000-follower milestone</span><br />
            <span class="text-muted-foreground">// [[[followers_total]]] is injected at render time</span><br />
            clamp(0, ([[[followers_total]]] - floor([[[followers_total]]] / 1000) * 1000) / 10, 100)<br /><br />
            <span class="text-muted-foreground">// Shout the channel name on every fourth second</span><br />
            mod(floor(now()), 4) = 0 ? "[[[channel_name]]] is live!" : ""<br /><br />
            <span class="text-muted-foreground">// Greet the latest follower with a fade-in over 2 s</span><br />
            <span class="text-muted-foreground">// c:greet_opacity =&gt;</span><br />
            clamp(0, (now() - c.followers_latest_date_at) / 2, 1)
          </div>
        </section>

        <!-- 10. Pitfalls -->
        <section class="mb-14" id="pitfalls">
          <h2 class="mb-4 text-2xl font-bold">10. Pitfalls and things that will not work</h2>

          <div class="space-y-4">
            <div class="rounded-lg border border-amber-500/30 bg-amber-500/5 p-5 text-foreground">
              <h3 class="mb-2 font-semibold text-amber-400">Expressions are reactive, not scheduled</h3>
              <p>
                An expression re-evaluates when any referenced control changes, not on a wall-clock interval.
                <code>sin(now())</code> <em>alone</em> references nothing reactive, so it evaluates once and
                sits there. If you want continuous motion driven by time, let CSS animate the visual part and
                use expressions for the discrete state that changes on events. Or hook a ticker control
                elsewhere in the overlay to force the cascade.
              </p>
            </div>

            <div class="rounded-lg border border-amber-500/30 bg-amber-500/5 p-5 text-foreground">
              <h3 class="mb-2 font-semibold text-amber-400">Radians, not degrees</h3>
              <p>
                <code>sin(90)</code> is not 1. It is <MathEquation tex="\sin(90 \text{ rad}) \approx 0.894" />.
                Use <MathEquation tex="\theta_{\text{rad}} = \theta_{\text{deg}} \cdot \pi / 180" />
                or just work in multiples of <code>PI</code> directly.
              </p>
            </div>

            <div class="rounded-lg border border-amber-500/30 bg-amber-500/5 p-5 text-foreground">
              <h3 class="mb-2 font-semibold text-amber-400">No exponentiation operator</h3>
              <p>
                <code>x ** 2</code> and <code>x ^ 2</code> do nothing useful. For <MathEquation tex="x^2" /> write
                <code>x * x</code>. For <MathEquation tex="x^3" /> write <code>x * x * x</code>. There is no
                <code>sqrt</code>, <code>log</code>, <code>exp</code>, or <code>tan</code> - the function whitelist
                is deliberately small for security and bundle size.
              </p>
            </div>

            <div class="rounded-lg border border-amber-500/30 bg-amber-500/5 p-5 text-foreground">
              <h3 class="mb-2 font-semibold text-amber-400">Division by zero returns zero</h3>
              <p>
                The engine swallows <MathEquation tex="x / 0" /> and returns <code>0</code> instead of
                <code>Infinity</code>. This is deliberate: an overlay should never crash on a zero denominator.
                Write defensively anyway - <code>c.wins / (c.wins + c.losses)</code> returns <code>0</code> on
                the fresh account, not the <MathEquation tex="\text{NaN}" /> you might expect.
              </p>
            </div>

            <div class="rounded-lg border border-amber-500/30 bg-amber-500/5 p-5 text-foreground">
              <h3 class="mb-2 font-semibold text-amber-400">Odd argument count in arg-family</h3>
              <p>
                <code>latest(a, b, c)</code> with three arguments returns the literal error string
                <code>"&#9888; Odd argument count - needs value, label pairs"</code>. The engine is telling you
                to pair every value with a label.
              </p>
            </div>

            <div class="rounded-lg border border-amber-500/30 bg-amber-500/5 p-5 text-foreground">
              <h3 class="mb-2 font-semibold text-amber-400">Floating-point sins</h3>
              <p>
                <MathEquation tex="0.1 + 0.2 = 0.30000000000000004" />. When rendering, round at the edge:
                <code>round(expr, 2)</code> or the <code>|round:2</code> pipe. Never compare floats with
                <code>==</code> - use <code>abs(a - b) &lt; 0.001</code>.
              </p>
            </div>
          </div>
        </section>

        <!-- Closing -->
        <div class="mt-16 rounded-lg border border-violet-500/30 bg-violet-500/5 p-6 text-foreground">
          <h2 class="mb-3 text-xl font-bold">Now go build something weird</h2>
          <p class="mb-2">
            The entire engine fits in one file -
            <code>resources/js/composables/useExpressionEngine.ts</code> - and the whole whitelist is readable
            in about ten seconds. Every function above is a primitive you can combine. The real power is in
            what you chain together.
          </p>
          <p>
            Want the companion pages?
            <Link href="/help/controls" class="text-violet-400 hover:underline">Controls</Link>,
            <Link href="/help/conditionals" class="text-violet-400 hover:underline">Conditionals</Link>,
            <Link href="/help/formatting" class="text-violet-400 hover:underline">Formatting Pipes</Link>.
          </p>
        </div>

      </div>
    </div>
  </AppLayout>
</template>
