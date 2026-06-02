<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Help', href: '/help' },
  { title: 'Lists in realtime', href: '/help/lists-realtime' },
];
</script>

<template>
  <Head>
    <title>Lists in realtime - Overlabels</title>
    <meta
      name="description"
      content="Build a live data page off an Overlabels List: get a token, read the list as JSON, render it, subscribe to live updates over WebSocket, and add it to OBS. A step-by-step guide."
    />

    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://overlabels.com/help/lists-realtime" />
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" content="Lists in realtime - Overlabels" />
    <meta
      property="og:description"
      content="Get a token, read a List as JSON, subscribe to live updates over WebSocket, and add it to OBS as a browser source. The full build guide."
    />
    <meta property="og:image"
          content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="Overlabels - build Twitch overlays with HTML, CSS, and live data" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Lists in realtime - Overlabels" />
    <meta
      name="twitter:description"
      content="Get a token, read a List as JSON, subscribe to live updates, and add it to OBS. The full build guide."
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
            title="Lists in realtime"
            title-class="text-4xl font-bold mb-4"
            description="Build a page that reads one of your Lists and updates live - a wheel, a leaderboard, a ticker - and drop it into OBS. Step by step."
          />
        </div>

        <div class="mb-12 border border-violet-500/40 bg-violet-500/5 p-6">
          <p class="text-foreground">
            This is the <strong>builder's guide</strong>. If you just want to create a List and use it from the
            dashboard or chat, start at <Link href="/help/lists" class="text-violet-400 hover:underline">the Lists
            page</Link>. This guide picks up where that leaves off: you have a List, and you want to render its data,
            live, in your own page.
          </p>
        </div>

        <!-- TOC -->
        <div class="mb-12 border border-sidebar-accent bg-card p-6">
          <h2 class="mb-4 text-xl font-bold" id="toc">Table of contents</h2>
          <ol class="list-decimal space-y-1 pl-6 text-foreground">
            <li><a href="#model" class="text-violet-400 hover:underline">The mental model (read this first)</a></li>
            <li><a href="#token" class="text-violet-400 hover:underline">Step 1 - Get a token</a></li>
            <li><a href="#read" class="text-violet-400 hover:underline">Step 2 - Read the list</a></li>
            <li><a href="#render" class="text-violet-400 hover:underline">Step 3 - Render it</a></li>
            <li><a href="#live" class="text-violet-400 hover:underline">Step 4 - Go live</a></li>
            <li><a href="#obs" class="text-violet-400 hover:underline">Step 5 - Put it in OBS</a></li>
            <li><a href="#example" class="text-violet-400 hover:underline">The complete page</a></li>
            <li><a href="#troubleshooting" class="text-violet-400 hover:underline">Troubleshooting</a></li>
            <li><a href="#limits" class="text-violet-400 hover:underline">Limits and honest caveats</a></li>
            <li><a href="#quickref" class="text-violet-400 hover:underline">Quick reference</a></li>
          </ol>
        </div>

        <!-- Mental model -->
        <section class="mb-14" id="model">
          <h2 class="mb-4 text-2xl font-bold">The mental model (read this first)</h2>
          <p class="mb-4 text-foreground">
            The single most important thing to understand: <strong>Overlabels overlays run no JavaScript.</strong> When
            you save an overlay template, we strip <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">&lt;script&gt;</code>,
            event handlers, and iframes. So you <em>cannot</em> build a wheel inside an overlay template - there's no way
            to run the code that would draw it.
          </p>
          <p class="mb-4 text-foreground">
            Instead, your live data page lives <strong>outside</strong> Overlabels: a normal HTML + JS page you host
            anywhere, added to OBS as <strong>its own</strong> Browser Source. It talks to your List over two rails:
          </p>
          <pre class="mb-4 overflow-x-auto border border-sidebar-border bg-sidebar-accent p-4 font-mono text-sm leading-relaxed text-foreground">  your page (hosted anywhere, JS allowed)
        |
        |  1. READ once     GET /api/lists/&lt;slug&gt;?token=...     -> current items + how to subscribe
        |  2. SUBSCRIBE      WebSocket: lists.&lt;twitch&gt;.&lt;slug&gt;     -> pushed on every change
        v
   Overlabels  ---- broadcasts on every append / draw / edit / age-out ---></pre>
          <p class="text-foreground">
            One token unlocks both rails. You read the current state once to draw the first frame, then subscribe so the
            page redraws itself whenever the List changes - no polling, no refresh. The rest of this guide is just
            wiring those two calls.
          </p>
        </section>

        <!-- Step 1: token -->
        <section class="mb-14" id="token">
          <h2 class="mb-4 text-2xl font-bold">Step 1 - Get a token</h2>
          <p class="mb-4 text-foreground">
            You authenticate with an <strong>Overlay Access Token</strong> - the same 64-character token your overlay
            URLs use. Generate one from your dashboard's
            <Link href="/tokens" class="text-violet-400 hover:underline">Overlay Access Tokens</Link> page and copy it.
          </p>
          <div class="border border-amber-500/40 bg-amber-500/5 p-6">
            <h3 class="mb-2 text-lg font-semibold">Treat the token like sharing your overlay URL</h3>
            <p class="text-foreground">
              The token identifies <em>you</em>, so it can only ever read <em>your</em> Lists - never anyone else's. But
              anyone who has it can read your Lists, and if you embed it in a public page's source, it's visible there.
              That's the same trust model as handing someone your overlay link. Don't paste it anywhere you wouldn't
              paste that.
            </p>
          </div>
        </section>

        <!-- Step 2: read -->
        <section class="mb-14" id="read">
          <h2 class="mb-4 text-2xl font-bold">Step 2 - Read the list</h2>
          <p class="mb-4 text-foreground">
            One GET request returns the List as JSON. Try it in a terminal first to see the shape:
          </p>
          <pre class="mb-4 overflow-x-auto border border-sidebar-border bg-sidebar-accent p-4 font-mono text-sm text-foreground">curl "https://overlabels.com/api/lists/wheel?token=YOUR_TOKEN"</pre>
          <p class="mb-4 text-foreground">
            Response (for a List with slug <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">wheel</code>):
          </p>
          <pre class="mb-4 overflow-x-auto border border-sidebar-border bg-sidebar-accent p-4 font-mono text-xs leading-relaxed text-foreground">{
  "slug": "wheel",
  "label": null,
  "count": 2,
  "items": [
    { "id": 1, "value": "Pizza", "added_at": 1730000000, "label": null, "weight": 1, "color": null },
    { "id": 2, "value": "Tacos", "added_at": 1730000060, "label": null, "weight": 1, "color": null }
  ],
  "disabled_at": null, "expires_at": null, "entry_ttl_seconds": null,
  "updated_at": 1730000060, "ts": 1730000061,
  "realtime": {
    "channel": "lists.&lt;your twitch id&gt;.wheel",
    "event": "list.updated",
    "auth_endpoint": "https://overlabels.com/api/overlay/broadcasting/auth",
    "key": "...", "host": "...", "port": 443, "scheme": "https"
  }
}</pre>
          <p class="mb-4 text-foreground">
            Two halves matter:
          </p>
          <ul class="mb-4 list-disc space-y-2 pl-6 text-foreground">
            <li><code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">items</code> - your data, as full
              objects. <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">id</code> is a stable,
              never-reused key (two items with the same value still differ);
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">value</code> is the content;
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">added_at</code> is Unix seconds.</li>
            <li><code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">realtime</code> - everything you need
              for Step 4. You don't hardcode any of it; you read it from here.</li>
          </ul>
        </section>

        <!-- Step 3: render -->
        <section class="mb-14" id="render">
          <h2 class="mb-4 text-2xl font-bold">Step 3 - Render it</h2>
          <p class="mb-4 text-foreground">
            A minimal page that fetches once and draws the items. Key your elements by
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">item.id</code> - it's the whole point of
            the objects, and it makes the live step painless.
          </p>
          <pre class="mb-4 overflow-x-auto border border-sidebar-border bg-sidebar-accent p-4 font-mono text-xs leading-relaxed text-foreground">&lt;div id="wheel"&gt;&lt;/div&gt;
&lt;script&gt;
  const TOKEN = 'YOUR_TOKEN';
  const SLUG = 'wheel';
  const BASE = 'https://overlabels.com';

  function render(items) {
    document.getElementById('wheel').replaceChildren(...items.map(it =&gt; {
      const el = document.createElement('div');
      el.dataset.id = it.id;        // stable key
      el.textContent = it.value;
      return el;
    }));
  }

  fetch(`${BASE}/api/lists/${SLUG}?token=${TOKEN}`)
    .then(r =&gt; r.json())
    .then(data =&gt; render(data.items));
&lt;/script&gt;</pre>
          <p class="text-foreground">
            That's a working (static) page already. Open it in a browser and you'll see your items. Now make it live.
          </p>
        </section>

        <!-- Step 4: live -->
        <section class="mb-14" id="live">
          <h2 class="mb-4 text-2xl font-bold">Step 4 - Go live</h2>
          <p class="mb-4 text-foreground">
            Subscribe to the List's channel and re-render on every push. We use
            <a href="https://github.com/pusher/pusher-js" class="text-violet-400 hover:underline">pusher-js</a> (Reverb
            speaks the Pusher protocol). Load it from a CDN - your page can, it's not an overlay:
          </p>
          <pre class="mb-4 overflow-x-auto border border-sidebar-border bg-sidebar-accent p-4 font-mono text-xs leading-relaxed text-foreground">&lt;script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"&gt;&lt;/script&gt;</pre>
          <p class="mb-4 text-foreground">
            Then, using the <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">realtime</code> block from
            Step 2:
          </p>
          <pre class="mb-4 overflow-x-auto border border-sidebar-border bg-sidebar-accent p-4 font-mono text-xs leading-relaxed text-foreground">const rt = data.realtime;

const pusher = new Pusher(rt.key, {
  wsHost: rt.host,
  wsPort: rt.port,
  wssPort: rt.port,
  forceTLS: rt.scheme === 'https',
  enabledTransports: ['ws', 'wss'],
  cluster: 'mt1', // unused by self-hosted Reverb, but pusher-js wants a value
  // Authorize the private channel with your token (this is what proves
  // you're allowed to read this list's stream):
  authorizer: (channel) =&gt; ({
    authorize: (socketId, cb) =&gt; {
      fetch(rt.auth_endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          socket_id: socketId,
          channel_name: channel.name,
          token: TOKEN,
          slug: SLUG,
        }),
      }).then(r =&gt; r.json()).then(d =&gt; cb(null, d)).catch(e =&gt; cb(e, null));
    },
  }),
});

const channel = pusher.subscribe('private-' + rt.channel);
channel.bind(rt.event, (payload) =&gt; render(payload.items)); // payload.items = the new objects</pre>
          <p class="text-foreground">
            That's it. A chatter runs <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!raffle</code>,
            the List grows, the broadcast fires, your <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">render()</code>
            runs with the new items - instantly, no polling. Because you key by
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">id</code>, the survivor of a draw keeps its
            element and you can animate the difference.
          </p>
        </section>

        <!-- Step 5: OBS -->
        <section class="mb-14" id="obs">
          <h2 class="mb-4 text-2xl font-bold">Step 5 - Put it in OBS</h2>
          <ol class="mb-4 list-decimal space-y-2 pl-6 text-foreground">
            <li><strong>Host the page over https.</strong> GitHub Pages, tiiny.host, Netlify, your own server -
              anywhere that serves https. (Browser sources and the API both require https; a local
              <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">file://</code> page often can't fetch
              it.)</li>
            <li><strong>Add a Browser Source</strong> in OBS pointing at your page's url. Set the width/height to
              whatever your wheel needs.</li>
            <li><strong>Keep the token out of the public url if you can</strong> - prefer hardcoding it in the page you
              control over passing it in a shareable link. Either way, it's a read-only key for your own lists.</li>
          </ol>
          <p class="text-foreground">
            This source is separate from your Overlabels overlay source. You can run both - your themed overlay from
            Overlabels, and your custom wheel from your own page - side by side in the same scene.
          </p>
        </section>

        <!-- Complete example -->
        <section class="mb-14" id="example">
          <h2 class="mb-4 text-2xl font-bold">The complete page</h2>
          <p class="mb-4 text-foreground">
            A full, copy-paste-ready bootstrap-then-subscribe page lives in the repo at
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">docs/examples/list-data-consumer.html</code>.
            It reads slug + token from the page url so you can reuse one file for any List:
          </p>
          <pre class="mb-4 overflow-x-auto border border-sidebar-border bg-sidebar-accent p-4 font-mono text-xs text-foreground">your-page.html?base=https://overlabels.com&amp;slug=wheel&amp;token=YOUR_TOKEN</pre>
          <p class="text-foreground">
            Start from that, swap the <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">render()</code>
            for your own drawing (an SVG wheel, a canvas, an animated list), and you have a live, custom overlay driven
            by chat.
          </p>
        </section>

        <!-- Troubleshooting -->
        <section class="mb-14" id="troubleshooting">
          <h2 class="mb-4 text-2xl font-bold">Troubleshooting</h2>
          <div class="overflow-x-auto border border-sidebar-accent bg-card">
            <table class="w-full text-sm">
              <thead class="border-b border-sidebar text-left">
                <tr>
                  <th class="p-3 font-semibold">Symptom</th>
                  <th class="p-3 font-semibold">Likely cause and fix</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-sidebar">
                <tr>
                  <td class="p-3 text-foreground">Fetch fails / blocked by mixed content</td>
                  <td class="p-3 text-foreground">Your page is served over <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">http</code> (or <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">file://</code>). Host it over <strong>https</strong>. The API and the WebSocket both require it.</td>
                </tr>
                <tr>
                  <td class="p-3 text-foreground"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">401</code> from the read endpoint</td>
                  <td class="p-3 text-foreground">Token missing, not 64 chars, or revoked. Regenerate it on the <Link href="/tokens" class="text-violet-400 hover:underline">Overlay Access Tokens</Link> page and update your page.</td>
                </tr>
                <tr>
                  <td class="p-3 text-foreground"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">404</code> from the read endpoint</td>
                  <td class="p-3 text-foreground">No List with that slug on your account. Check the slug exactly (lowercase, underscores) on your <Link href="/dashboard/lists" class="text-violet-400 hover:underline">Lists page</Link>.</td>
                </tr>
                <tr>
                  <td class="p-3 text-foreground">Loads once, never updates</td>
                  <td class="p-3 text-foreground">The subscribe step isn't connecting. Check the browser console: is <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">pusher.subscribe</code> firing <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">pusher:subscription_succeeded</code>? Confirm you used <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">key/host/port</code> from the <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">realtime</code> block, and prefixed the channel with <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">private-</code>.</td>
                </tr>
                <tr>
                  <td class="p-3 text-foreground"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">403</code> "Channel not permitted"</td>
                  <td class="p-3 text-foreground">The token's account doesn't match the channel's twitch id, or the slug is malformed. Use the exact <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">realtime.channel</code> string from the read response, and the same token.</td>
                </tr>
                <tr>
                  <td class="p-3 text-foreground">Works in a browser, not in OBS</td>
                  <td class="p-3 text-foreground">OBS browser sources are stricter: the page must be https, and some setups cache aggressively - right-click the source and Refresh after changes.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Limits -->
        <section class="mb-14" id="limits">
          <h2 class="mb-4 text-2xl font-bold">Limits and honest caveats</h2>
          <div class="space-y-6">
            <div class="border border-sidebar-accent bg-card p-6">
              <h3 class="mb-2 text-lg font-semibold"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">label</code>, <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">weight</code>, and <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">color</code> are reserved</h3>
              <p class="text-foreground">
                They're in the data shape, but there's no way to <em>set</em> them yet - every item is
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">label: null</code>,
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">weight: 1</code>,
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">color: null</code>. Build against
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">id</code>,
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">value</code>, and
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">added_at</code> today. When per-item
                editing ships, your page reads the new fields with no other change.
              </p>
            </div>
            <div class="border border-sidebar-accent bg-card p-6">
              <h3 class="mb-2 text-lg font-semibold">Very large lists and the payload cap</h3>
              <p class="text-foreground">
                A live update carries the whole list's current state. For normal lists that's tiny, but a list with
                hundreds of long entries can exceed the broadcast size cap, in which case an update may not arrive. If
                you're pushing a List that big, fall back to re-fetching the read endpoint on a timer, or split the data.
              </p>
            </div>
            <div class="border border-sidebar-accent bg-card p-6">
              <h3 class="mb-2 text-lg font-semibold">No live updates needed? Just poll</h3>
              <p class="text-foreground">
                Step 4 is optional. If a few seconds of latency is fine, skip the WebSocket entirely and call the read
                endpoint on an interval. The static page from Step 3 plus a
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">setInterval</code> is a complete,
                simpler solution.
              </p>
            </div>
          </div>
        </section>

        <!-- Quick reference -->
        <section class="mb-14" id="quickref">
          <h2 class="mb-4 text-2xl font-bold">Quick reference</h2>
          <pre class="overflow-x-auto border border-sidebar-border bg-sidebar-accent p-4 font-mono text-sm leading-relaxed text-foreground">Read (one-shot state + how to subscribe)
  GET https://overlabels.com/api/lists/&lt;slug&gt;?token=&lt;overlay token&gt;

  -> { slug, label, count, items: [ {id,value,added_at,label,weight,color} ],
       disabled_at, expires_at, entry_ttl_seconds, updated_at, ts,
       realtime: { channel, event, auth_endpoint, key, host, port, scheme } }

Subscribe (live)
  pusher-js -> key/host/port from realtime{}
  authorizer POSTs { socket_id, channel_name, token, slug } to realtime.auth_endpoint
  channel = 'private-' + realtime.channel        (e.g. private-lists.&lt;twitch&gt;.&lt;slug&gt;)
  event   = realtime.event                        ('list.updated')
  payload.items = the new array of item objects

Reference consumer
  docs/examples/list-data-consumer.html</pre>
        </section>

        <p class="mb-12 text-sm text-muted-foreground">
          New to Lists themselves? Start with
          <Link href="/help/lists" class="text-violet-400 hover:underline">the Lists guide</Link>. For numeric
          computation on List data, see
          <Link href="/help/expressions" class="text-violet-400 hover:underline">Expression Controls</Link>.
        </p>
      </div>
    </div>
  </AppLayout>
</template>
