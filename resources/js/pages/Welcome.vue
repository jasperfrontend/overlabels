<script setup lang="ts">
import LoginSocial from '@/components/LoginSocial.vue';
import DarkModeToggle from '@/components/DarkModeToggle.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { ArrowRight, Github, Zap, ToggleLeft, Timer, Hash, Type, Calendar, SlidersHorizontal, GitFork, Layers, Code2, Shield } from 'lucide-vue-next';
import { ref } from 'vue';

const syntaxTab = ref('static');

const controlTypes = [
  {
    type: 'text',
    icon: Type,
    description: 'Strings up to 1000 chars. HTML stripped on save.',
    example: '[[[c:myname]]] → JasperDiscovers',
  },
  {
    type: 'number',
    icon: Hash,
    description: 'Numeric values. Safely coerced, defaults to 0.',
    example: '[[[c:goal_target]]] → 500',
  },
  {
    type: 'counter',
    icon: SlidersHorizontal,
    description: 'Integer counter. Increment or decrement from the dashboard.',
    example: '[[[c:kill_count]]] → 14',
  },
  {
    type: 'timer',
    icon: Timer,
    description: 'Countup or countdown at 250ms resolution. State broadcast over WebSocket.',
    example: '[[[c:round_timer]]] → 4:32',
  },
  {
    type: 'datetime',
    icon: Calendar,
    description: 'ISO 8601 datetime. For scheduled events, stream start times.',
    example: '[[[c:next_event]]] → 2026-03-01T20:00:00Z',
  },
  {
    type: 'boolean',
    icon: ToggleLeft,
    description: 'Stores "1" or "0". Toggle overlay sections live from your dashboard.',
    example: '[[[if:c:show_goal]]] → show or hide',
  },
];

const twitchEvents = [
  { type: 'channel.follow', label: 'New Follower', tag: 'event.user_name' },
  { type: 'channel.subscribe', label: 'New Subscription', tag: 'event.tier' },
  { type: 'channel.subscription.gift', label: 'Gift Subscriptions', tag: 'event.total' },
  { type: 'channel.subscription.message', label: 'Resubscription', tag: 'event.message.text' },
  { type: 'channel.cheer', label: 'Bits Cheer', tag: 'event.bits' },
  { type: 'channel.raid', label: 'Incoming Raid', tag: 'event.viewers' },
  { type: 'channel.channel_points_custom_reward_redemption.add', label: 'Channel Points', tag: 'event.reward.title' },
  { type: 'stream.online', label: 'Stream Online', tag: 'event.type' },
  { type: 'stream.offline', label: 'Stream Offline', tag: '—' },
];

const alertPipelineSteps = [
  'Twitch sends a webhook POST to /api/twitch/webhook',
  'HMAC-SHA256 signature validated against your per-user webhook secret',
  'Mapping lookup finds the template assigned to the event type for your account',
  'Current overlay data merged with the event payload (event.viewers, event.user_name, etc.)',
  'Compiled alert broadcast to Pusher channel alerts.{twitch_id}',
  'Overlay receives the payload, renders into the alert DOM node, plays transition',
  'Auto-dismisses after configured duration. Static overlay continues uninterrupted.',
];
</script>

<template>
  <div class="min-h-screen bg-background text-foreground">
    <Head>
      <title>Overlabels — A live overlay DSL for Twitch streamers</title>
      <meta
        name="description"
        content="Write HTML and CSS. Bind live Twitch data with triple-bracket tags. React to every Twitch event. Free, open source overlay engine for OBS."
      />

      <!-- Open Graph -->
      <meta property="og:type" content="website" />
      <meta property="og:url" content="https://overlabels.com/" />
      <meta property="og:site_name" content="Overlabels" />
      <meta property="og:title" content="Overlabels &bull; A live overlay DSL for Twitch streamers" />
      <meta
        property="og:description"
        content="Write HTML and CSS. Bind live Twitch data with triple-bracket tags. React to every Twitch event. Free, open source overlay engine for OBS."
      />
      <meta property="og:image" content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
      <meta property="og:image:width" content="1200" />
      <meta property="og:image:height" content="630" />
      <meta property="og:image:alt" content="Overlabels &bull; write HTML and CSS, bind live Twitch data with triple-bracket tags" />

      <!-- Twitter / X -->
      <meta name="twitter:card" content="summary_large_image" />
      <meta name="twitter:title" content="Overlabels &bull; A live overlay DSL for Twitch streamers" />
      <meta
        name="twitter:description"
        content="Write HTML and CSS. Bind live Twitch data with triple-bracket tags. React to every Twitch event. Free, open source overlay engine for OBS."
      />
      <meta name="twitter:image" content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
      <meta name="twitter:image:alt" content="Overlabels &bull; write HTML and CSS, bind live Twitch data with triple-bracket tags" />
    </Head>

    <!-- Navigation -->
    <nav class="sticky top-0 z-50 border-b border-border/50 bg-background/80 backdrop-blur-lg">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
          <div class="flex items-center gap-2.5">
            <img src="/favicon.png" alt="" class="h-8 w-8" />
            <span class="text-lg font-bold tracking-tight">Overlabels</span>
            <Badge variant="outline" class="text-xs">Beta</Badge>
          </div>
          <div class="flex items-center gap-6">
            <Link href="/help" class="hidden text-sm text-muted-foreground transition-colors hover:text-foreground sm:block">Docs</Link>
            <Link href="/manifesto" class="hidden text-sm text-muted-foreground transition-colors hover:text-foreground sm:block">Manifesto</Link>
            <DarkModeToggle />
            <Link v-if="$page.props.auth.user" :href="route('dashboard.index')" class="btn btn-primary text-sm">
              Dashboard <ArrowRight class="ml-1.5 h-4 w-4" />
            </Link>
            <LoginSocial v-else />
          </div>
        </div>
      </div>
    </nav>

    <!-- Hero -->
    <section class="border-b border-border/50 py-24 sm:py-36">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
          <div class="mb-6 flex flex-wrap items-center gap-3">
            <Badge variant="outline" class="border-sky-500/40 px-3 py-1 font-mono text-xs text-sky-500"> DSL · State-driven · Deterministic </Badge>
            <Badge variant="outline" class="px-3 py-1 text-xs">Free forever · Open source</Badge>
          </div>

          <h1 class="mb-6 text-5xl leading-[1.05] font-bold tracking-tight sm:text-6xl md:text-7xl">
            A live overlay DSL<br />
            <span class="text-sky-500">for Twitch.</span>
          </h1>

          <p class="mb-4 max-w-2xl text-xl leading-relaxed text-muted-foreground">
            Write HTML and CSS. Bind live Twitch data with a triple-bracket tag syntax. React to every Twitch event. Mutate overlay state from your
            dashboard in real time.
          </p>
          <p class="mb-14 max-w-2xl text-base text-muted-foreground">
            No drag-and-drop. No proprietary format. No lock-in. Your overlay is a webpage.
            <span class="text-sky-500">Overlabels is the engine that keeps it alive.</span>
          </p>

          <!-- Hero code blocks -->
          <div class="mb-14 grid gap-4 lg:grid-cols-2">
            <div class="overflow-hidden rounded-md border border-border">
              <div class="flex items-center gap-2 border-b border-border bg-muted/50 px-4 py-2.5">
                <span class="font-mono text-xs text-muted-foreground">overlay.html</span>
              </div>
              <div class="overflow-x-auto bg-zinc-950 p-5 font-mono text-sm leading-7">
                <div>
                  <span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-400">"stat-bar"</span
                  ><span class="text-zinc-500">&gt;</span>
                </div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-500">&lt;span&gt;</span><span class="text-amber-400">[[[followers_total]]]</span
                  ><span class="text-zinc-500">&lt;/span&gt;</span>
                </div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-500">&lt;small&gt;</span><span class="text-zinc-300">followers</span
                  ><span class="text-zinc-500">&lt;/small&gt;</span>
                </div>
                <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
                <div class="mt-2"></div>
                <div><span class="text-sky-400">[[[if:followers_total &gt;= 1000]]]</span></div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-400">"milestone"</span
                  ><span class="text-zinc-500">&gt;</span>
                </div>
                <div>&nbsp;&nbsp;&nbsp;&nbsp;<span class="text-zinc-300">four digits. let's go.</span></div>
                <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;/div&gt;</span></div>
                <div><span class="text-sky-400">[[[endif]]]</span></div>
              </div>
            </div>

            <div class="overflow-hidden rounded-md border border-emerald-500/30">
              <div class="flex items-center gap-2 border-b border-emerald-500/20 bg-emerald-950/30 px-4 py-2.5">
                <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>
                <span class="font-mono text-xs text-emerald-400">live in OBS</span>
              </div>
              <div class="overflow-x-auto bg-zinc-950 p-5 font-mono text-sm leading-7">
                <div>
                  <span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-400">"stat-bar"</span
                  ><span class="text-zinc-500">&gt;</span>
                </div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-500">&lt;span&gt;</span><span class="text-emerald-300">1,342</span
                  ><span class="text-zinc-500">&lt;/span&gt;</span>
                </div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-500">&lt;small&gt;</span><span class="text-zinc-300">followers</span
                  ><span class="text-zinc-500">&lt;/small&gt;</span>
                </div>
                <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
                <div class="mt-2"></div>
                <div>
                  <span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-400">"milestone"</span
                  ><span class="text-zinc-500">&gt;</span>
                </div>
                <div>&nbsp;&nbsp;&nbsp;&nbsp;<span class="text-zinc-300">four digits. let's go.</span></div>
                <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
              </div>
            </div>
          </div>

          <div class="flex flex-wrap gap-4">
            <Link v-if="!$page.props.auth.user" href="#get-started">
              <button class="btn btn-primary">Get started free <ArrowRight class="ml-2 h-4 w-4" /></button>
            </Link>
            <Link v-else :href="route('dashboard.index')">
              <button class="btn btn-primary">Go to dashboard <ArrowRight class="ml-2 h-4 w-4" /></button>
            </Link>
            <Link href="/manifesto">
              <button class="btn btn-secondary">Read the manifesto</button>
            </Link>
          </div>
        </div>
      </div>
    </section>

    <!-- 01 — The Syntax -->
    <section class="border-b border-border/50 py-24">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
          <Badge variant="outline" class="mb-4 px-3 py-1 font-mono text-xs">01 — The Syntax</Badge>
          <h2 class="mb-4 text-3xl font-bold sm:text-4xl">Triple brackets. That's it.</h2>
          <p class="mb-12 max-w-2xl text-lg text-muted-foreground">
            The syntax is deliberately collision-resistant — too distinctive to ever conflict with HTML, CSS, or any template engine, and simple
            enough to read without a tutorial. Tags resolve to live Twitch data. They work in HTML, in CSS, and inside conditional blocks.
          </p>

          <!-- Tabs -->
          <div class="mb-8 flex gap-0 border-b border-border">
            <button
              @click="syntaxTab = 'static'"
              :class="[
                '-mb-px cursor-pointer border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                syntaxTab === 'static' ? 'border-sky-500 text-sky-500' : 'border-transparent text-muted-foreground hover:text-foreground',
              ]"
            >
              Static data
            </button>
            <button
              @click="syntaxTab = 'css'"
              :class="[
                '-mb-px cursor-pointer border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                syntaxTab === 'css' ? 'border-sky-500 text-sky-500' : 'border-transparent text-muted-foreground hover:text-foreground',
              ]"
            >
              In CSS
            </button>
            <button
              @click="syntaxTab = 'events'"
              :class="[
                '-mb-px cursor-pointer border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                syntaxTab === 'events' ? 'border-sky-500 text-sky-500' : 'border-transparent text-muted-foreground hover:text-foreground',
              ]"
            >
              Event alerts
            </button>
          </div>

          <div v-show="syntaxTab === 'static'">
            <div class="mb-4 overflow-hidden rounded-md border border-border">
              <div class="border-b border-border bg-muted/50 px-4 py-2.5">
                <span class="font-mono text-xs text-muted-foreground">Static overlay — subscriber bar</span>
              </div>
              <div class="overflow-x-auto bg-zinc-950 p-5 font-mono text-sm leading-7">
                <div>
                  <span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-400">"sub-bar"</span
                  ><span class="text-zinc-500">&gt;</span>
                </div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-500">&lt;span&gt;</span><span class="text-amber-400">[[[subscribers_total]]]</span
                  ><span class="text-zinc-300"> subs</span><span class="text-zinc-500">&lt;/span&gt;</span>
                </div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-500">&lt;span&gt;</span><span class="text-zinc-300">Latest: </span
                  ><span class="text-amber-400">[[[subscribers_latest_user_name]]]</span><span class="text-zinc-500">&lt;/span&gt;</span>
                </div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-500">&lt;span&gt;</span><span class="text-amber-400">[[[channel_game]]]</span
                  ><span class="text-zinc-300"> — </span><span class="text-amber-400">[[[channel_title]]]</span
                  ><span class="text-zinc-500">&lt;/span&gt;</span>
                </div>
                <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
              </div>
            </div>
            <p class="text-sm text-muted-foreground">
              Available tags span user data, channel info, followers, subscribers, goals, and more.
              <Link href="/help" class="text-sky-500 hover:underline">Browse all template tags →</Link>
            </p>
          </div>

          <div v-show="syntaxTab === 'css'">
            <div class="mb-4 overflow-hidden rounded-md border border-border">
              <div class="border-b border-border bg-muted/50 px-4 py-2.5">
                <span class="font-mono text-xs text-muted-foreground">overlay.css — tags resolve before CSS injects into the document head</span>
              </div>
              <div class="overflow-x-auto bg-zinc-950 p-5 font-mono text-sm leading-7">
                <div><span class="text-sky-400">.follower-bar</span><span class="text-zinc-500"> &#123;</span></div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-400">width</span><span class="text-zinc-500">: calc(</span
                  ><span class="text-amber-400">[[[followers_total]]]</span><span class="text-zinc-500"> / </span
                  ><span class="text-amber-400">[[[goals_latest_target]]]</span><span class="text-zinc-500"> * 100%);</span>
                </div>
                <div><span class="text-zinc-500">&#125;</span></div>
                <div class="mt-3"></div>
                <div><span class="text-sky-400">.stream-title::before</span><span class="text-zinc-500"> &#123;</span></div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-400">content</span><span class="text-zinc-500">: </span><span class="text-emerald-400">"</span
                  ><span class="text-amber-400">[[[channel_title]]]</span><span class="text-emerald-400">"</span><span class="text-zinc-500">;</span>
                </div>
                <div><span class="text-zinc-500">&#125;</span></div>
              </div>
            </div>
            <p class="text-sm text-muted-foreground">
              Dynamic widths, generated content, colour values driven by data — anything a CSS value can express, a tag can provide.
            </p>
          </div>

          <div v-show="syntaxTab === 'events'">
            <div class="mb-4 overflow-hidden rounded-md border border-border">
              <div class="border-b border-border bg-muted/50 px-4 py-2.5">
                <span class="font-mono text-xs text-muted-foreground">Alert template — channel.cheer</span>
              </div>
              <div class="overflow-x-auto bg-zinc-950 p-5 font-mono text-sm leading-7">
                <div>
                  <span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-400">"cheer-alert"</span
                  ><span class="text-zinc-500">&gt;</span>
                </div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-500">&lt;h1&gt;</span><span class="text-amber-400">[[[event.user_name]]]</span
                  ><span class="text-zinc-300"> cheered!</span><span class="text-zinc-500">&lt;/h1&gt;</span>
                </div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-500">&lt;p&gt;</span><span class="text-amber-400">[[[event.bits]]]</span
                  ><span class="text-zinc-300"> bits — total subs: </span><span class="text-amber-400">[[[subscribers_total]]]</span
                  ><span class="text-zinc-500">&lt;/p&gt;</span>
                </div>
                <div>&nbsp;&nbsp;<span class="text-sky-400">[[[if:event.bits &gt;= 1000]]]</span></div>
                <div>
                  &nbsp;&nbsp;&nbsp;&nbsp;<span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-400">"whale"</span
                  ><span class="text-zinc-500">&gt;</span><span class="text-zinc-300">big cheer</span><span class="text-zinc-500">&lt;/div&gt;</span>
                </div>
                <div>&nbsp;&nbsp;<span class="text-sky-400">[[[endif]]]</span></div>
                <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
              </div>
            </div>
            <p class="text-sm text-muted-foreground">
              Event tags are merged with your static overlay data at render time. All static tags remain available inside alert templates — mix them
              freely.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- 02 — Controls -->
    <section class="border-b border-border/50 bg-muted/20 py-24">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
          <Badge variant="outline" class="mb-4 px-3 py-1 font-mono text-xs">02 — Controls</Badge>
          <h2 class="mb-4 text-3xl font-bold sm:text-4xl">Typed, mutable overlay state.</h2>
          <p class="mb-3 max-w-2xl text-lg text-muted-foreground">
            Controls are named, typed values you define per template and update from your dashboard while the overlay is live in OBS. Change a value
            and every connected overlay re-renders that tag instantly — no page reload, no OBS interaction required.
          </p>
          <p class="mb-12 max-w-2xl text-muted-foreground">
            Reference any control with <code class="rounded bg-zinc-900 px-1.5 py-0.5 font-mono text-sm text-amber-400">[[[c:key]]]</code> — in HTML,
            in CSS, and in conditional blocks.
            <Link href="/help/controls" class="ml-1 text-sky-500 hover:underline">Full controls reference →</Link>
          </p>

          <!-- Control types grid -->
          <div class="mb-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="ctrl in controlTypes" :key="ctrl.type" class="rounded-md border border-border bg-background p-4">
              <div class="mb-2 flex items-center gap-2">
                <component :is="ctrl.icon" class="h-4 w-4 shrink-0 text-sky-500" />
                <span class="font-mono text-sm font-semibold">{{ ctrl.type }}</span>
              </div>
              <p class="mb-3 text-sm text-muted-foreground">{{ ctrl.description }}</p>
              <div class="overflow-x-auto rounded bg-zinc-950 px-3 py-1.5 font-mono text-xs text-amber-400">{{ ctrl.example }}</div>
            </div>
          </div>

          <!-- Power combo -->
          <div class="overflow-hidden rounded-md border border-sky-500/20">
            <div class="border-b border-sky-500/20 bg-sky-950/20 px-4 py-2.5">
              <span class="font-mono text-xs text-sky-400">Power combo — boolean control + countdown timer + conditional class binding</span>
            </div>
            <div class="overflow-x-auto bg-zinc-950 p-5 font-mono text-sm leading-7">
              <div>
                <span class="text-xs text-zinc-600">// "show_timer" → boolean → "1" &nbsp;&nbsp; "round_timer" → timer → countdown, 300s base</span>
              </div>
              <div class="mt-2"></div>
              <div><span class="text-sky-400">[[[if:c:show_timer]]]</span></div>
              <div>
                &nbsp;&nbsp;<span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-400">"timer </span
                ><span class="text-sky-400">[[[if:c:round_timer &lt;= 10]]]</span><span class="text-emerald-400">danger</span
                ><span class="text-sky-400">[[[endif]]]</span><span class="text-emerald-400">"</span><span class="text-zinc-500">&gt;</span>
              </div>
              <div>&nbsp;&nbsp;&nbsp;&nbsp;<span class="text-amber-400">[[[c:round_timer]]]</span></div>
              <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;/div&gt;</span></div>
              <div><span class="text-sky-400">[[[endif]]]</span></div>
            </div>
          </div>
          <p class="mt-3 text-sm text-muted-foreground">
            The timer ticks at 250ms resolution. The <code class="rounded bg-zinc-900 px-1 text-xs text-amber-400">danger</code> class applies
            automatically when the countdown reaches 10 seconds. Flip the boolean from the dashboard to show or hide the block — live.
          </p>
        </div>
      </div>
    </section>

    <!-- 03 — Conditionals -->
    <section class="border-b border-border/50 py-24">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
          <Badge variant="outline" class="mb-4 px-3 py-1 font-mono text-xs">03 — Conditional Rendering</Badge>
          <h2 class="mb-4 text-3xl font-bold sm:text-4xl">A comparison engine in your template.</h2>
          <p class="mb-12 max-w-2xl text-lg text-muted-foreground">
            Any tag — Twitch data, control value, or event payload — can drive a conditional block. Evaluated client-side in the overlay with no
            server round-trips and no <code class="rounded bg-muted px-1.5 text-sm">eval()</code>. Nesting supported up to 10 levels deep.
            <Link href="/help" class="ml-1 text-sky-500 hover:underline">Full syntax reference →</Link>
          </p>

          <div class="mb-10 grid gap-8 lg:grid-cols-2">
            <div>
              <h3 class="mb-4 text-xs font-semibold tracking-widest text-muted-foreground uppercase">Syntax</h3>
              <div class="overflow-hidden rounded-md border border-border">
                <div class="bg-zinc-950 p-5 font-mono text-sm leading-7">
                  <div><span class="text-sky-400">[[[if:variable operator value]]]</span></div>
                  <div>&nbsp;&nbsp;<span class="text-zinc-500">...</span></div>
                  <div><span class="text-sky-400">[[[elseif:variable operator value]]]</span></div>
                  <div>&nbsp;&nbsp;<span class="text-zinc-500">...</span></div>
                  <div><span class="text-sky-400">[[[else]]]</span></div>
                  <div>&nbsp;&nbsp;<span class="text-zinc-500">...</span></div>
                  <div><span class="text-sky-400">[[[endif]]]</span></div>
                </div>
              </div>
            </div>

            <div>
              <h3 class="mb-4 text-xs font-semibold tracking-widest text-muted-foreground uppercase">Operators</h3>
              <div class="divide-y divide-border overflow-hidden rounded-md border border-border">
                <div
                  v-for="[op, label] in [
                    ['=', 'Equal'],
                    ['!=', 'Not equal'],
                    ['>', 'Greater than'],
                    ['<', 'Less than'],
                    ['>=', 'Greater than or equal'],
                    ['<=', 'Less than or equal'],
                  ]"
                  :key="op"
                  class="flex items-center gap-4 bg-background px-4 py-2.5"
                >
                  <code class="w-8 shrink-0 font-mono text-sm text-amber-400">{{ op }}</code>
                  <span class="text-sm text-muted-foreground">{{ label }}</span>
                </div>
              </div>
              <p class="mt-3 text-xs text-muted-foreground">
                Numeric comparisons are numeric. Truthy check treats <code class="rounded bg-muted px-1">"0"</code>,
                <code class="rounded bg-muted px-1">"false"</code>, and empty string as false.
              </p>
            </div>
          </div>

          <div class="overflow-hidden rounded-md border border-border">
            <div class="border-b border-border bg-muted/50 px-4 py-2.5">
              <span class="font-mono text-xs text-muted-foreground">Language-aware overlay + milestone block</span>
            </div>
            <div class="overflow-x-auto bg-zinc-950 p-5 font-mono text-sm leading-7">
              <div><span class="text-sky-400">[[[if:channel_language = en]]]</span></div>
              <div>
                &nbsp;&nbsp;<span class="text-zinc-500">&lt;p&gt;</span><span class="text-zinc-300">Welcome to the stream</span
                ><span class="text-zinc-500">&lt;/p&gt;</span>
              </div>
              <div><span class="text-sky-400">[[[elseif:channel_language = es]]]</span></div>
              <div>
                &nbsp;&nbsp;<span class="text-zinc-500">&lt;p&gt;</span><span class="text-zinc-300">Bienvenidos al stream</span
                ><span class="text-zinc-500">&lt;/p&gt;</span>
              </div>
              <div><span class="text-sky-400">[[[else]]]</span></div>
              <div>
                &nbsp;&nbsp;<span class="text-zinc-500">&lt;p&gt;</span><span class="text-zinc-300">Welcome</span
                ><span class="text-zinc-500">&lt;/p&gt;</span>
              </div>
              <div><span class="text-sky-400">[[[endif]]]</span></div>
              <div class="mt-3"></div>
              <div><span class="text-sky-400">[[[if:followers_total &gt;= 10000]]]</span></div>
              <div>
                &nbsp;&nbsp;<span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-400">"tenk-badge"</span
                ><span class="text-zinc-500">&gt;</span><span class="text-zinc-300">10K club</span><span class="text-zinc-500">&lt;/div&gt;</span>
              </div>
              <div><span class="text-sky-400">[[[endif]]]</span></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 04 — Events -->
    <section class="border-b border-border/50 bg-muted/20 py-24">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
          <Badge variant="outline" class="mb-4 px-3 py-1 font-mono text-xs">04 — Event Alerts</Badge>
          <h2 class="mb-4 text-3xl font-bold sm:text-4xl">Every Twitch event. One syntax.</h2>
          <p class="mb-12 max-w-2xl text-lg text-muted-foreground">
            Assign an alert template to any EventSub event. When the event fires, Overlabels renders the template with the payload merged into the tag
            context, broadcasts the compiled alert to your overlay over WebSocket, and displays it with a configured transition and duration — all
            without any interaction from you.
          </p>

          <!-- Events grid -->
          <div class="mb-12 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="evt in twitchEvents" :key="evt.type" class="rounded-md border border-border bg-background p-4">
              <div class="mb-1 text-sm font-semibold">{{ evt.label }}</div>
              <div class="mb-3 truncate font-mono text-xs text-muted-foreground">{{ evt.type }}</div>
              <div v-if="evt.tag !== '—'" class="rounded bg-zinc-950 px-2.5 py-1.5 font-mono text-xs text-amber-400">[[[{{ evt.tag }}]]]</div>
              <div v-else class="rounded bg-zinc-950 px-2.5 py-1.5 font-mono text-xs text-zinc-600">no payload</div>
            </div>
          </div>

          <!-- Alert pipeline -->
          <div class="overflow-hidden rounded-md border border-border">
            <div class="border-b border-border bg-muted/50 px-4 py-2.5">
              <span class="font-mono text-xs text-muted-foreground">What happens when a raid fires</span>
            </div>
            <div class="divide-y divide-border/50">
              <div
                v-for="(step, i) in alertPipelineSteps"
                :key="i"
                class="flex items-start gap-4 bg-background px-5 py-3.5 transition-colors hover:bg-muted/30"
              >
                <span class="mt-0.5 shrink-0 font-mono text-xs text-sky-500/70">{{ String(i + 1).padStart(2, '0') }}</span>
                <span class="text-sm text-foreground">{{ step }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 05 — Kits & Forking -->
    <section class="border-b border-border/50 py-24">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
          <Badge variant="outline" class="mb-4 px-3 py-1 font-mono text-xs">05 — Kits &amp; Forking</Badge>
          <h2 class="mb-4 text-3xl font-bold sm:text-4xl">Good design compounds.</h2>
          <p class="mb-12 max-w-2xl text-lg text-muted-foreground">
            Any public template or kit can be forked. One click, one copy, fully yours to modify, extend, or break. An Overlay Kit is a collection of
            templates — a static overlay, a follower alert, a subscription alert, a raid alert — designed as a cohesive visual system. Fork the kit,
            get everything at once.
          </p>

          <div class="grid gap-6 sm:grid-cols-3">
            <div class="rounded-md border border-border bg-background p-6">
              <GitFork class="mb-4 h-8 w-8 text-sky-500" />
              <h3 class="mb-2 font-semibold">Fork anything public</h3>
              <p class="text-sm text-muted-foreground">
                Every public template is a starting point. Fork it, own it, ship it. The original is always untouched.
              </p>
            </div>
            <div class="rounded-md border border-border bg-background p-6">
              <Layers class="mb-4 h-8 w-8 text-sky-500" />
              <h3 class="mb-2 font-semibold">Overlay Kits</h3>
              <p class="text-sm text-muted-foreground">
                Collections of templates sharing a visual language. Fork the kit, get the whole system. No assembly required.
              </p>
            </div>
            <div class="rounded-md border border-border bg-background p-6">
              <Shield class="mb-4 h-8 w-8 text-sky-500" />
              <h3 class="mb-2 font-semibold">Controls carry over</h3>
              <p class="text-sm text-muted-foreground">
                Forking a template with controls opens the Import Wizard. Pick which controls come with the fork.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 06 — Zero to Overlay -->
    <section class="border-b border-border/50 bg-muted/20 py-24">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
          <Badge variant="outline" class="mb-4 px-3 py-1 font-mono text-xs">06 — Zero to Overlay</Badge>
          <h2 class="mb-4 text-3xl font-bold sm:text-4xl">From signup to OBS in minutes.</h2>
          <p class="mb-12 max-w-2xl text-lg text-muted-foreground">
            When you sign up, Overlabels runs an automated onboarding pipeline: generates your per-user webhook secret, forks the starter kit into
            your account, auto-assigns alert templates to event types, and generates your full personalised tag set from your live Twitch data. You
            arrive to a working overlay.
          </p>

          <div class="grid gap-10 sm:grid-cols-2">
            <div>
              <h3 class="mb-5 flex items-center gap-2 text-sm font-semibold tracking-widest text-muted-foreground uppercase">
                <Zap class="h-4 w-4 text-sky-500" /> Automated on signup
              </h3>
              <ul class="space-y-4 text-sm">
                <li class="flex items-start gap-3">
                  <span class="mt-0.5 shrink-0 font-mono text-xs text-sky-500">01</span>
                  <span class="text-muted-foreground">Per-user webhook secret generated for HMAC-SHA256 validation</span>
                </li>
                <li class="flex items-start gap-3">
                  <span class="mt-0.5 shrink-0 font-mono text-xs text-sky-500">02</span>
                  <span class="text-muted-foreground">Starter kit forked directly into your account</span>
                </li>
                <li class="flex items-start gap-3">
                  <span class="mt-0.5 shrink-0 font-mono text-xs text-sky-500">03</span>
                  <span class="text-muted-foreground">Alert templates auto-assigned to event types by keyword matching</span>
                </li>
                <li class="flex items-start gap-3">
                  <span class="mt-0.5 shrink-0 font-mono text-xs text-sky-500">04</span>
                  <span class="text-muted-foreground">Full tag set generated from your live Twitch channel data</span>
                </li>
              </ul>
            </div>

            <div>
              <h3 class="mb-5 flex items-center gap-2 text-sm font-semibold tracking-widest text-muted-foreground uppercase">
                <Code2 class="h-4 w-4 text-sky-500" /> Personalised testing page
              </h3>
              <p class="mb-4 text-sm text-muted-foreground">
                The <code class="rounded bg-zinc-900 px-1.5 py-0.5 font-mono text-xs text-sky-400">/testing</code> page gives you ready-to-run
                <a href="https://github.com/twitchdev/twitch-cli" target="_blank" rel="noopener" class="text-sky-500 hover:underline">Twitch CLI</a>
                commands for every event type, pre-filled with your credentials.
              </p>
              <div class="overflow-x-auto rounded-md bg-zinc-950 p-4 font-mono text-xs leading-6">
                <div><span class="text-zinc-500">$ twitch event trigger channel.follow \</span></div>
                <div><span class="text-zinc-500">&nbsp;&nbsp;--transport=webhook \</span></div>
                <div>
                  <span class="text-zinc-500">&nbsp;&nbsp;-F </span><span class="text-emerald-400">https://overlabels.com/api/twitch/webhook</span
                  ><span class="text-zinc-500"> \</span>
                </div>
                <div>
                  <span class="text-zinc-500">&nbsp;&nbsp;-s </span><span class="text-amber-400">your_webhook_secret</span
                  ><span class="text-zinc-500"> \</span>
                </div>
                <div><span class="text-zinc-500">&nbsp;&nbsp;--to-user </span><span class="text-amber-400">your_twitch_id</span></div>
              </div>
              <p class="mt-3 text-xs text-muted-foreground">Commands are blurred by default and revealed on hover. No secrets exposed in source.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section id="get-started" class="border-b border-border/50 py-24">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
          <h2 class="mb-6 text-4xl font-bold tracking-tight sm:text-5xl">
            Ship your overlay.<br />
            <span class="text-sky-500">Free. Forever.</span>
          </h2>
          <p class="mx-auto mb-10 max-w-lg text-lg text-muted-foreground">
            No paywalls. No tiers. No artificial limits. Everything you create is yours. The whole thing is open source.
          </p>

          <div v-if="$page.props.auth.user" class="flex flex-col items-center gap-4">
            <Link :href="route('dashboard.index')" class="btn btn-primary px-8 text-base"> Go to Dashboard <ArrowRight class="ml-2 h-5 w-5" /> </Link>
          </div>
          <div v-else class="flex flex-col items-center gap-6">
            <LoginSocial />
            <p class="text-xs text-muted-foreground">
              Authenticate with Twitch. You must have an email address attached to your account before you can login. Revoke access anytime from your
              Twitch settings.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="py-12">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-8 flex flex-col items-center justify-between gap-4 sm:flex-row">
          <div class="flex items-center gap-2.5">
            <img src="/favicon.png" alt="" class="h-8 w-8" />
            <span class="font-semibold">Overlabels</span>
            <Badge variant="outline" class="text-xs">Beta</Badge>
          </div>
          <div class="flex flex-wrap items-center gap-6 text-sm text-muted-foreground">
            <Link href="/help" class="transition-colors hover:text-foreground">Docs</Link>
            <Link href="/help/controls" class="transition-colors hover:text-foreground">Controls</Link>
            <Link href="/manifesto" class="transition-colors hover:text-foreground">Manifesto</Link>
            <Link href="/terms" class="transition-colors hover:text-foreground">Terms</Link>
            <Link href="/privacy" class="transition-colors hover:text-foreground">Privacy</Link>
            <a
              href="https://github.com/jasperfrontend/overlabels"
              target="_blank"
              rel="noopener"
              class="flex items-center gap-1.5 transition-colors hover:text-foreground"
            >
              GitHub <Github class="h-4 w-4" />
            </a>
          </div>
        </div>
        <div class="border-t border-border/50 pt-8 text-center text-xs text-muted-foreground">
          Made by JasperDiscovers for the Twitch streaming community. Forever free, forever open.<br />
          FAQ: Will you support Kick.com? No.
        </div>
      </div>
    </footer>
  </div>
</template>
