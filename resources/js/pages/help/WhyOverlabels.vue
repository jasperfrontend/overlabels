<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Help', href: '/help' },
  { title: 'Why Overlabels', href: '/help/why-overlabels' },
];
</script>

<template>
  <Head>
    <title>Why Overlabels - Overlabels</title>
    <meta
      name="description"
      content="Overlabels is a third-party data normalization engine for Twitch. Donations, subs, bits, followers - it all becomes math you can work with."
    />

    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://overlabels.com/help/why-overlabels" />
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" content="Why Overlabels - Overlabels" />
    <meta
      property="og:description"
      content="Overlabels is a third-party data normalization engine for Twitch. Donations, subs, bits, followers - it all becomes math you can work with."
    />
    <meta property="og:image"
          content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="Overlabels - build Twitch overlays with HTML, CSS, and live data" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Why Overlabels - Overlabels" />
    <meta
      name="twitter:description"
      content="Overlabels is a third-party data normalization engine for Twitch. Donations, subs, bits, followers - it all becomes math you can work with."
    />
    <meta name="twitter:image"
          content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta name="twitter:image:alt" content="Overlabels - build Twitch overlays with HTML, CSS, and live data" />
  </Head>

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="min-h-screen bg-background">
      <div class="mx-auto max-w-4xl p-6">
        <!-- Hero -->
        <div class="mb-10">
          <h1 class="text-4xl font-extrabold tracking-tight">Why Overlabels</h1>
          <p class="mt-4 text-lg text-foreground">
            Overlabels is a third-party data normalization engine for Twitch and the services around it.
            Donations, subs, bits, followers, GPS pings, chat - they all come in through different APIs with
            different shapes, and by the time they hit your overlay they're just numbers, strings, and
            booleans you can do math with.
          </p>
          <p class="mt-4 text-lg text-foreground">
            If that sentence excited you, you're in the right place. Strap in.
          </p>
        </div>

        <!-- Why third-party matters -->
        <section class="mb-12 space-y-4">
          <h2 class="text-2xl font-bold">Why being third-party matters a lot</h2>
          <p class="text-foreground">
            Overlabels doesn't care where your donation came from. StreamElements, StreamLabs, Ko-fi,
            Fourthwall, Twitch Bits - they're all just <em>money that showed up</em>. We normalize every payload into a common
            shape and expose it as a Control you can reference anywhere in your template.
          </p>
          <p class="text-foreground">
            Overlabels <em>loves</em> numbers. Because numbers mean math, and math means you can actually
            <em>do</em> something with your data instead of just rendering whatever opinionated widget your
            alert provider decided to ship this quarter.
          </p>
        </section>

        <!-- latest() demo -->
        <section class="mb-12 space-y-4">
          <h2 class="text-2xl font-bold">Who was the last person to donate, across <em>every</em> service?</h2>
          <p class="text-foreground">
            One function:
          </p>
          <div class="border border-sidebar-border bg-sidebar-accent p-4">
            <pre class="overflow-x-auto text-sm font-mono text-foreground"><code>latest(
  c.streamlabs.latest_donor_name_at,     c.streamlabs.latest_donor_name,
  c.kofi.latest_donor_name_at,           c.kofi.latest_donor_name,
  c.streamelements.latest_donor_name_at, c.streamelements.latest_donor_name,
  c.fourthwall.latest_donor_name_at,     c.fourthwall.latest_donor_name,
  c.twitch.latest_cheerer_name_at,       c.twitch.latest_cheerer_name
)</code></pre>
          </div>
          <p class="text-foreground">
            Calmly outputs the name of the last person who donated through five different services. No
            other overlay or alert service on the market does this, and with Overlabels it's just another
            function. (And we're adding more integrations all the time.)
          </p>
          <p class="text-foreground">
            Every control Overlabels tracks gets an automatic
            <code class="border border-sidebar-border bg-sidebar p-0.5 px-1 font-mono">_at</code> companion - a Unix timestamp of
            when that value last updated. <code class="border border-sidebar-border bg-sidebar p-0.5 px-1 font-mono">latest()</code> walks
            through (timestamp, value) pairs and returns the value whose timestamp is the largest. Same
            mechanism gives you
            <code class="border border-sidebar-border bg-sidebar p-0.5 px-1 font-mono">oldest()</code>,
            <code class="border border-sidebar-border bg-sidebar p-0.5 px-1 font-mono">max()</code>, and
            <code class="border border-sidebar-border bg-sidebar p-0.5 px-1 font-mono">min()</code>.
          </p>
        </section>

        <!-- Math demo -->
        <section class="mb-12 space-y-4">
          <h2 class="text-2xl font-bold">Add up every donation this stream (and make sense of Bits)</h2>
          <p class="text-foreground">
            Twitch Bits are 100 Bits = 1 USD, which is a math problem everyone else seems to just... give
            up on. Drop an Expression Control into your overlay and unleash:
          </p>
          <div class="border border-sidebar-border bg-sidebar-accent p-4">
            <pre class="overflow-x-auto text-sm font-mono text-foreground"><code>c.kofi.donations_received +
c.streamlabs.donations_received +
c.streamelements.donations_received +
c.fourthwall.donations_received +
(c.twitch.cheers_this_stream / 100)</code></pre>
          </div>
          <p class="text-foreground">
            That's a workable USD number, composed from five providers and a currency conversion, updating
            live as events arrive. You could keep going - weight each source, compute a 1-hour rolling
            average, trigger a goal celebration when the combined total crosses a threshold. It's just
            math.
          </p>
        </section>

        <!-- Controls pitch -->
        <section class="mb-12 space-y-4">
          <h2 class="text-2xl font-bold">Controls: the soul of the machine</h2>
          <p class="text-foreground">
            There's a SICK implementation of Controls in Overlabels. A Control is a named, typed, live-updating
            value you can reference anywhere in your template. Seven types:
          </p>
          <ul class="ml-6 list-disc space-y-1 text-foreground">
            <li><strong>Text</strong> - strings you can update from the dashboard or chat</li>
            <li><strong>Number</strong> - integers or floats with min/max constraints</li>
            <li><strong>Boolean</strong> - toggles for show/hide logic</li>
            <li><strong>Counter</strong> - increment/decrement with chat commands or API</li>
            <li><strong>Timer</strong> - countup / countdown / countto, ticks locally on the overlay</li>
            <li><strong>Random</strong> - picks a new value every N ms within a range</li>
            <li><strong>Expression</strong> - the big one. Math over every other value in the system.</li>
          </ul>
          <p class="text-foreground">
            Service-managed Controls (Ko-fi, StreamLabs, StreamElements, Fourthwall, Overlabels Mobile...) auto-update
            from their source and cannot be manually edited - the data is <em>real</em>. User Controls are
            yours to fiddle with. Either way, every Control can be read by any Expression and targeted by
            any conditional.
          </p>
          <p class="text-foreground">
            Want to compare your follower count to your subscriber count? Want to fire an alert when the
            combined StreamElements + Ko-fi total crosses €100 in a single stream session? Want to flash
            a boolean ON when your bits-per-minute spikes above your stream average? You have the building
            blocks. Read <a href="/help/controls" class="text-violet-400 hover:underline">/help/controls</a>
            for the full list.
          </p>
        </section>

        <!-- Conditionals -->
        <section class="mb-12 space-y-4">
          <h2 class="text-2xl font-bold">But wait, what about <code>[[[if:</code>?</h2>
          <p class="text-foreground">
            If? If not? If larger? If smaller? Oh yeah, we gotchu. Conditional engine: built in.
          </p>
          <div class="border border-sidebar-border bg-sidebar-accent p-4">
            <pre class="overflow-x-auto text-sm font-mono text-foreground"><code>[[[if:followers_total &gt;= 1000]]]
  &lt;div class="milestone"&gt;1K+ followers!&lt;/div&gt;
[[[elseif:followers_total &gt;= 100]]]
  &lt;div&gt;Growing strong with [[[followers_total]]] followers&lt;/div&gt;
[[[else]]]
  &lt;div&gt;Help us reach 100 followers!&lt;/div&gt;
[[[endif]]]</code></pre>
          </div>
          <p class="text-foreground">
            Full comparison operators, boolean logic, nested branches, event-scoped conditions, even
            conditional styling inside <code class="border border-sidebar-border bg-sidebar-accent px-1 font-mono">&lt;style&gt;</code>
            blocks. Read all about it at
            <a href="/help/conditionals" class="text-violet-400 hover:underline">/help/conditionals</a>.
          </p>
        </section>

        <!-- Formatting -->
        <section class="mb-12 space-y-4">
          <h2 class="text-2xl font-bold">But data is ugly!</h2>
          <p class="text-foreground">
            Yes. It really is. Ever typed <code class="border border-sidebar-border bg-sidebar p-1 px-0.5 font-mono">0.1 + 0.2</code>
            into any modern programming language and expected
            <code class="border border-sidebar-border bg-sidebar p-1 px-0.5 font-mono">0.3</code>? Yeah - you got
            <code class="border border-sidebar-border bg-sidebar p-1 px-0.5 font-mono">0.30000000000000004</code>. Floats are cursed. When
            rendering, round at the edge:
            <code class="border border-sidebar-border bg-sidebar p-1 px-0.5 font-mono">round(expr, 2)</code> or the
            <code class="border border-sidebar-border bg-sidebar p-1 px-0.5 font-mono">|round:2</code> pipe. Never compare floats with
            <code class="border border-sidebar-border bg-sidebar p-1 px-0.5 font-mono">==</code> - use
            <code class="border border-sidebar-border bg-sidebar p-1 px-0.5 font-mono">abs(a - b) &lt; 0.001</code>.
          </p>
          <p class="text-foreground">
            Overlabels ships with <code>round</code>, <code>number</code>, <code>currency</code>,
            <code>duration</code>, <code>date</code>, <code>distance</code>, <code>speed</code>, and upper /
            lowercase formatters. Pipe any value into a formatter and boom - readable.
          </p>
          <div class="border border-sidebar-border bg-sidebar-accent p-4">
            <pre class="overflow-x-auto text-sm font-mono text-foreground"><code>[[[c:event_date|date]]]</code></pre>
          </div>
          <p class="text-foreground">
            Renders <code class="border border-sidebar-border bg-sidebar-accent px-1 font-mono">Apr 5, 2026, 7:00 PM</code> when your
            locale is US. Renders <code class="border border-sidebar-border bg-sidebar-accent px-1 font-mono">5 apr 2026, 19:00</code>
            when you're Dutch. Every pipe formatter honors your locale setting.
            See <a href="/help/formatting" class="text-violet-400 hover:underline">/help/formatting</a>.
          </p>
        </section>

        <!-- Settings -->
        <section class="mb-12 space-y-4">
          <h2 class="text-2xl font-bold">Settings?</h2>
          <p class="text-foreground">
            Yeah, we got settings. Overlabels is basically a math engine wrapped as an overlay tool, so we
            need some flexibility on how you display your data (more custom locale settings coming soon!).
            For now you can set:
          </p>
          <ul class="ml-6 list-disc space-y-1 text-foreground">
            <li><strong>Theme</strong> - Light / Dark / System, affects the dashboard only (your overlays stay transparent)</li>
            <li><strong>Locale</strong> - drives every formatter pipe system-wide</li>
            <li>
              <strong>Foreach caps</strong> - very important. Controls how many entries Overlabels streams
              down for <code class="border border-sidebar-border bg-sidebar-accent px-1 font-mono">subscribers</code>,
              <code class="border border-sidebar-border bg-sidebar-accent px-1 font-mono">channel_followers</code>,
              <code class="border border-sidebar-border bg-sidebar-accent px-1 font-mono">followed_channels</code>, and goals. Higher caps
              = more rows in your loops = bigger payload. Tune to your overlay's needs.
            </li>
          </ul>
        </section>

        <!-- Foreach -->
        <section class="mb-12 space-y-4">
          <h2 class="text-2xl font-bold">A foreach loop, you say?</h2>
          <p class="text-foreground">
            Overlabels lets you iterate over array data: your last followers, subscribers, goals, channels
            you follow, and live data from polls, predictions, and hype train contributions. Render a
            poll as animated bars. Render the last 10 subs as avatars. Render hype-train contributors as a
            leaderboard. The loop is just markup:
          </p>
          <div class="border border-sidebar-border bg-sidebar-accent p-4">
            <pre class="overflow-x-auto text-sm font-mono text-foreground"><code>[[[foreach:event.choices as choice]]]
  &lt;li data-key="[[[choice.id]]]"&gt;
    [[[choice.title]]] - [[[choice.votes]]]
  &lt;/li&gt;
[[[endforeach]]]</code></pre>
          </div>
          <p class="text-foreground">
            Read the full loop docs at
            <a href="/help/conditionals#foreach-loops" class="text-violet-400 hover:underline">/help/conditionals#foreach-loops</a>.
          </p>
        </section>

        <!-- Math engine -->
        <section class="mb-12 space-y-4">
          <h2 class="text-2xl font-bold">You said you did maths?</h2>
          <p class="text-foreground">
            We do maths. So much maths. All the maths. More maths than you'll ever need, probably. Jokes
            aside - Overlabels ships with a substantial math evaluator built on
            <a href="https://github.com/EricSmekens/jsep" target="_blank" rel="noopener" class="text-violet-400 hover:underline">EricSmekens/jsep</a>,
            and it can evaluate a frankly absurd array of expressions. The math engine lives inside
            Expression Controls and is powerful enough that we can only explain the basics - the rest is
            up to you.
          </p>
          <p class="text-foreground">
            Once you understand that every value flowing through Overlabels is just a string, number, or
            boolean, and that you can compose them with any combination of arithmetic, comparisons,
            logical operators, ternaries, and ~30 built-in functions (<code>sum</code>, <code>avg</code>,
            <code>clamp</code>, <code>sin</code>, <code>mod</code>, <code>lerp</code>, <code>floor</code>,
            <code>abs</code>, and many more)... you really start to grasp what this thing can do.
          </p>
          <p class="text-foreground">
            Read <a href="/help/math" class="text-violet-400 hover:underline">/help/math</a> in full if you
            like maths. If you don't, skip to the next section.
          </p>
        </section>

        <!-- Kits -->
        <section class="mb-12 space-y-4">
          <h2 class="text-2xl font-bold">"I'm a coder but also lazy"</h2>
          <p class="text-foreground">
            Respectable. Overlabels has Kits - collections of ready-made overlays and alerts you can copy
            into your account with one click. Copying a Kit gives you a working starting point you can
            tear apart, rewire, and make your own. It's how most people learn what the system can do.
          </p>
          <p class="text-foreground">
            Browse the current Kits (after logging in) at
            <a href="/kits" class="text-violet-400 hover:underline">/kits</a>. And yes, you can build your
            own Kit - create a bunch of overlays and alerts, wrap them together, share them with the
            community. We'd love that.
          </p>
        </section>

        <!-- CTA -->
        <section class="mb-12 space-y-4">
          <h2 class="text-2xl font-bold">So why are you still using Streamlabs overlays?</h2>
          <p class="text-foreground">
            Bro, god knows. If you know a line or two of HTML and CSS, start using Overlabels. Copy a Kit,
            pick it apart, see how it's wired. It'll click fast.
          </p>
          <p class="text-foreground">
            And if it doesn't? Send me an email on <a href="mailto:jasper@emailjasper.com" class="text-violet-400 hover:underline">jasper@emailjasper.com</a>.
            I actually reply!
          </p>
        </section>

        <!-- Vision -->
        <div class="border border-sidebar-border bg-card p-6">
          <h2 class="mb-3 text-xl font-bold">The vision</h2>
          <p class="text-foreground">
            I want to make Overlabels the best third-party data normalization service there is for Twitch
            and its ecosystem. No other system has come this far at turning any payload - from any source -
            into plain math you can actually work with. I'm proud of that, and I hope by using Overlabels
            you start to see it too.
          </p>
          <p class="mt-3 text-foreground">
            Peace. Thank you. Go nerd out.
          </p>
          <p class="mt-3 text-foreground">
            <a href="https://twitch.tv/JasperDiscovers" class="text-violet-400 hover:underline">/JasperDiscovers</a>
          </p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
